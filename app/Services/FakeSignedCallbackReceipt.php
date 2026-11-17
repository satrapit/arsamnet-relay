<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

/**
 * Internal signed callback fixture for a fake provider only.
 *
 * No HTTP route or real provider adapter uses this prototype. A real
 * provider needs its own reviewed signature and delivery-proof contract.
 */
final class FakeSignedCallbackReceipt
{
    public function receive(int $providerAccountId, string $timestamp, string $rawBody, string $signature): bool
    {
        if (! app()->environment('local', 'testing')
            || $providerAccountId < 1 || strlen($rawBody) > 4096 || $rawBody === ''
            || ! preg_match('/\A[0-9]{10}\z/D', $timestamp)
            || abs(time() - (int) $timestamp) > 300
            || ! preg_match('/\Asha256=[a-f0-9]{64}\z/D', $signature)) {
            return false;
        }

        $provider = DB::table('provider_accounts')->where('id', $providerAccountId)
            ->where('enabled', true)->first();

        if (! $provider || $provider->adapter_code !== 'fake-only') {
            return false;
        }

        try {
            $credentials = json_decode(Crypt::decryptString((string) $provider->credentials_ciphertext),
                true, 512, JSON_THROW_ON_ERROR);
            $event = json_decode($rawBody, true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return false;
        }

        $secret = is_array($credentials) ? ($credentials['callback_secret'] ?? null) : null;
        if (! is_string($secret) || strlen($secret) < 32
            || ! hash_equals('sha256='.hash_hmac('sha256',
                $providerAccountId."\n".$timestamp."\n".$rawBody, $secret), $signature)
            || ! is_array($event) || array_is_list($event)
            || count($event) !== 3) {
            return false;
        }

        $keys = array_keys($event);
        sort($keys);
        if ($keys !== ['event_id', 'provider_ref', 'status']
            || ! is_string($event['event_id'])
            || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{15,127}\z/D', $event['event_id'])
            || ! is_string($event['provider_ref'])
            || ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $event['provider_ref'])
            || $event['status'] !== 'delivered') {
            return false;
        }

        $eventHash = hash_hmac('sha256', $event['event_id'],
            'relay-fake-receipt|'.$providerAccountId.'|'.config('app.key'));

        return DB::transaction(function () use ($provider, $providerAccountId, $event, $eventHash): bool {
            // Correlation must be unambiguous: no provider reference may
            // identify more than one accepted attempt for this account.
            $matches = DB::table('delivery_attempts')
                ->where('provider_account_id', $providerAccountId)
                ->where('provider_ref', $event['provider_ref'])
                ->where('outcome', 'accepted')
                ->limit(2)->get();

            if ($matches->count() !== 1) {
                return false;
            }

            $attempt = $matches->first();
            $delivery = DB::table('deliveries')
                ->where('id', $attempt->delivery_id)
                ->lockForUpdate()->first();

            if (! $delivery || $delivery->state !== 'accepted'
                || (int) $delivery->attempt_count !== 1
                || ! DB::table('clients')->where('id', $delivery->client_id)
                    ->where('status', 'active')->exists()
                || ($provider->client_id !== null
                    && (int) $provider->client_id !== (int) $delivery->client_id)
                || ! DB::table('delivery_targets')
                    ->where('id', $delivery->target_id)
                    ->where('client_id', $delivery->client_id)
                    ->where('channel', $provider->channel)->exists()
                || ! DB::table('client_provider_routes')
                    ->where('client_id', $delivery->client_id)
                    ->where('provider_account_id', $providerAccountId)
                    ->where('channel', $provider->channel)
                    ->where('enabled', true)->exists()) {
                return false;
            }

            // One row per signed event and provider, never the raw body or
            // unredacted event ID. The unique index protects concurrent replay.
            if (DB::table('callback_receipts')
                ->where('provider_account_id', $providerAccountId)
                ->where('event_id_hmac', $eventHash)->exists()) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            DB::table('callback_receipts')->insert([
                'provider_account_id' => $providerAccountId,
                'event_id_hmac' => $eventHash,
                'delivery_attempt_id' => $attempt->id,
                'status' => 'delivered',
                'received_at' => $now,
                'processed_at' => $now,
            ]);

            return DB::table('deliveries')
                ->where('id', $delivery->id)->where('client_id', $delivery->client_id)
                ->where('state', 'accepted')
                ->update(['state' => 'delivered', 'updated_at' => $now]) === 1;
        }, 3);
    }
}
