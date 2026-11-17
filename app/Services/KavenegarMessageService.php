<?php

namespace App\Services;

use App\Support\InvalidPublishTargets;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Tenant-bound SMS intake: normalized recipients, encrypted targets and
 * existing atomic message/queue publisher. No provider I/O in this class.
 */
final class KavenegarMessageService
{
    public function __construct(private readonly MessagePublisher $publisher) {}

    /**
     * @param list<array{receptor:string,message?:string}> $items
     * @param array<string, mixed> $meta
     * @return array{message_id:string,delivery_ids:list<string>,replayed:bool}
     */
    public function publish(int $clientId, int $apiKeyId, string $idempotencyKey, string $mode, array $items, array $meta): array
    {
        if (! in_array($mode, ['single', 'bulk', 'otp'], true)
            || count($items) < 1 || count($items) > 25) {
            throw new InvalidPublishTargets('Invalid SMS operation or batch size.');
        }

        $phones = [];
        foreach ($items as $item) {
            $phone = self::normalizeReceptor($item['receptor']);
            if (in_array($phone, $phones, true)) {
                throw ValidationException::withMessages([
                    'receptor' => 'Duplicate recipients in one request are not allowed.',
                ]);
            }
            $phones[] = $phone;
        }

        // Serialize quota accounting with publication for this client. The
        // same idempotency key must not increment delivery counts on replay.
        return DB::transaction(function () use (
            $clientId, $apiKeyId, $idempotencyKey, $mode, $items, $meta, $phones
        ): array {
            $client = DB::table('clients')->where('id', $clientId)->lockForUpdate()->first();
            if (! $client || $client->status !== 'active' || ! $this->enabledRoute($clientId)) {
                throw new InvalidPublishTargets('Kavenegar is unavailable for this client.');
            }

            // Conservative per-client quota counts all delivery records, not
            // only this provider. This avoids new mutable counters/DDL.
            $now = now()->utc();
            $hourly = DB::table('deliveries')
                ->where('client_id', $clientId)
                ->where('created_at', '>=', $now->copy()->subHour())
                ->count();

            if ($hourly + count($phones) > (int) config('relay.kavenegar_hourly_limit', 100)) {
                throw ValidationException::withMessages([
                    'receptor' => 'Client hourly SMS submission limit has been reached.',
                ]);
            }

            $targetIds = [];
            foreach ($phones as $phone) {
                $hmac = hash_hmac('sha256', $phone, 'relay-sms-target|'.config('app.key'));
                $targetId = DB::table('delivery_targets')
                    ->where('client_id', $clientId)->where('channel', 'sms')
                    ->where('address_hmac', $hmac)->value('id');

                if ($targetId === null) {
                    $targetId = DB::table('delivery_targets')->insertGetId([
                        'client_id' => $clientId,
                        'channel' => 'sms',
                        'address_ciphertext' => Crypt::encryptString($phone),
                        'address_hmac' => $hmac,
                        'enabled' => true,
                        'created_at' => $now,
                    ]);
                }

                $targetIds[] = (int) $targetId;
            }

            $payload = ['provider' => 'kavenegar', 'mode' => $mode];
            if ($mode === 'single') {
                $payload['message'] = $items[0]['message'];
            } elseif ($mode === 'bulk') {
                $payload['messages'] = [];
                foreach ($targetIds as $index => $id) {
                    $payload['messages'][(string) $id] = $items[$index]['message'];
                }
            } else {
                // Tokens are ciphertext at rest and are never returned,
                // traced, logged or placed in queue payloads.
                $payload['template'] = $meta['template'];
                $payload['token'] = $meta['token'];
                if (isset($meta['token2'])) {
                    $payload['token2'] = $meta['token2'];
                }
                if (isset($meta['token3'])) {
                    $payload['token3'] = $meta['token3'];
                }
            }

            return $this->publisher->publish(
                $clientId,
                $apiKeyId,
                $idempotencyKey,
                $targetIds,
                $payload,
                $mode === 'otp' ? 'high' : 'normal',
                $mode === 'otp' ? 300 : 3600,
            );
        }, 3);
    }

    public static function normalizeReceptor(string $raw): string
    {
        $phone = trim($raw);
        if (preg_match('/\A\+989[0-9]{9}\z/D', $phone)) {
            $phone = '09'.substr($phone, 4);
        } elseif (preg_match('/\A989[0-9]{9}\z/D', $phone)) {
            $phone = '09'.substr($phone, 3);
        }

        if (! preg_match('/\A09[0-9]{9}\z/D', $phone)) {
            throw ValidationException::withMessages([
                'receptor' => 'A valid Iranian mobile number is required.',
            ]);
        }

        return $phone;
    }

    private function enabledRoute(int $clientId): bool
    {
        return DB::table('provider_accounts as accounts')
            ->join('client_provider_routes as routes', 'routes.provider_account_id', '=', 'accounts.id')
            ->where('routes.client_id', $clientId)
            ->where('routes.channel', 'sms')
            ->where('routes.enabled', true)
            ->where('accounts.adapter_code', 'kavenegar')
            ->where('accounts.channel', 'sms')
            ->where('accounts.enabled', true)
            ->where(function ($query) use ($clientId): void {
                $query->where('accounts.client_id', $clientId)
                    ->orWhereNull('accounts.client_id');
            })->exists();
    }
}
