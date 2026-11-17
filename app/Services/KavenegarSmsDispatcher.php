<?php

namespace App\Services;

use App\DTO\OutboundProviderResult;
use App\Enums\ProviderAttemptOutcome;
use App\Jobs\StageDeliveryJob;
use App\Providers\Adapters\KavenegarSmsAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Bounded synchronous SMS dispatch. No retry, no failover and no queue
 * payload containing credentials, receptor, OTP or plaintext message.
 *
 * Every delivery must have its own durable started attempt before the
 * single provider HTTP call (including sendarray) can occur.
 */
final class KavenegarSmsDispatcher
{
    public function __construct(
        private readonly ProviderRouteSelector $routes,
        private readonly DeliveryLeaseManager $leases,
        private readonly DeliveryAttemptManager $attempts,
    ) {}

    /**
     * @return array{state:string,deliveries:list<array{id:string,state:string,error_code:?string,provider_ref:?string}>}
     */
    public function dispatch(int $clientId, string $messageId): array
    {
        if (! config('relay.publish_enabled', false)
            || ! config('relay.kavenegar_send_enabled', false)) {
            return ['state' => 'disabled', 'deliveries' => []];
        }

        $message = DB::table('messages')
            ->where('id', $messageId)->where('client_id', $clientId)->first();

        if (! $message || ($message->expires_at !== null && $message->expires_at <= now()->utc()->toDateTimeString())
            || ($message->scheduled_at !== null && $message->scheduled_at > now()->utc()->toDateTimeString())) {
            return ['state' => 'unavailable', 'deliveries' => []];
        }

        try {
            $payload = json_decode(Crypt::decryptString((string) $message->payload_ciphertext),
                true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return ['state' => 'unavailable', 'deliveries' => []];
        }

        if (! is_array($payload) || ($payload['provider'] ?? null) !== 'kavenegar'
            || ! in_array($payload['mode'] ?? null, ['single', 'bulk', 'otp'], true)) {
            return ['state' => 'unavailable', 'deliveries' => []];
        }

        $deliveries = DB::table('deliveries')
            ->where('message_id', $messageId)->where('client_id', $clientId)
            ->orderBy('id')->get();
        $count = $deliveries->count();
        if ($count < 1 || $count > 25
            || ($payload['mode'] !== 'bulk' && $count !== 1)) {
            return ['state' => 'unavailable', 'deliveries' => []];
        }

        // Stage only rows belonging to this message; native queue workers
        // subsequently observe a non-queued state and never send anything.
        foreach ($deliveries as $delivery) {
            (new StageDeliveryJob((string) $delivery->id))->handle();
        }

        $accountId = null;
        $recipients = [];
        foreach ($deliveries as $delivery) {
            $selected = $this->routes->select($clientId, (string) $delivery->id);
            if ($selected === null || ($accountId !== null && $selected !== $accountId)) {
                return ['state' => 'unavailable', 'deliveries' => []];
            }
            $accountId = $selected;

            $target = DB::table('delivery_targets')
                ->where('id', $delivery->target_id)->where('client_id', $clientId)
                ->where('channel', 'sms')->where('enabled', true)->first();
            if (! $target) {
                return ['state' => 'unavailable', 'deliveries' => []];
            }

            try {
                $receptor = Crypt::decryptString((string) $target->address_ciphertext);
            } catch (Throwable) {
                return ['state' => 'unavailable', 'deliveries' => []];
            }

            $recipients[] = [
                'id' => (string) $delivery->id,
                'target_id' => (int) $delivery->target_id,
                'receptor' => $receptor,
            ];
        }

        $account = DB::table('provider_accounts')
            ->where('id', $accountId)->where('adapter_code', 'kavenegar')
            ->where('channel', 'sms')->where('enabled', true)
            ->where(function ($q) use ($clientId): void {
                $q->whereNull('client_id')->orWhere('client_id', $clientId);
            })->first();

        if (! $account) {
            return ['state' => 'unavailable', 'deliveries' => []];
        }

        try {
            $credentials = json_decode(
                Crypt::decryptString((string) $account->credentials_ciphertext),
                true, 512, JSON_THROW_ON_ERROR,
            );
            $adapter = new KavenegarSmsAdapter(
                $credentials['api_key'],
                $credentials['sender'],
            );
            $requests = [];
            foreach ($recipients as $entry) {
                $content = $payload['mode'] === 'bulk'
                    ? ($payload['messages'][(string) $entry['target_id']] ?? null)
                    : ($payload['message'] ?? null);
                if ($payload['mode'] !== 'otp'
                    && (! is_string($content) || $content === '' || strlen($content) > 4096)) {
                    return ['state' => 'unavailable', 'deliveries' => []];
                }
                $requests[] = [
                    'receptor' => $entry['receptor'],
                    'message' => $content,
                ];
            }
        } catch (Throwable) {
            return ['state' => 'unavailable', 'deliveries' => []];
        }

        $begun = [];
        foreach ($recipients as $recipient) {
            $token = $this->leases->claim($clientId, $recipient['id'], 45);
            $attemptId = $token === null ? null : $this->attempts->begin(
                $clientId, $recipient['id'], $token, (int) $accountId,
            );

            if ($attemptId === null) {
                // Do not send a partial bulk request. Quarantine any
                // attempt already opened, without retrying a provider.
                foreach ($begun as $started) {
                    $this->attempts->finish(
                        $clientId, $started['id'], $started['token'], $started['attempt_id'],
                        ProviderAttemptOutcome::Ambiguous,
                    );
                }

                return ['state' => 'review_required', 'deliveries' => $this->snapshot($clientId, $messageId)];
            }

            $begun[] = [
                'id' => $recipient['id'],
                'token' => $token,
                'attempt_id' => $attemptId,
            ];
        }

        try {
            if ($payload['mode'] === 'bulk') {
                $results = $adapter->sendBulk($requests);
            } elseif ($payload['mode'] === 'otp') {
                $results = [$adapter->sendOtp(
                    $recipients[0]['receptor'],
                    (string) $payload['template'],
                    (string) $payload['token'],
                    isset($payload['token2']) ? (string) $payload['token2'] : null,
                    isset($payload['token3']) ? (string) $payload['token3'] : null,
                )];
            } else {
                $results = [$adapter->send(new \App\DTO\OutboundProviderRequest(
                    $recipients[0]['id'], 'sms', $recipients[0]['receptor'],
                    $requests[0]['message'],
                ))];
            }
        } catch (Throwable) {
            $results = [];
        }

        foreach ($begun as $index => $started) {
            $result = $results[$index] ?? new OutboundProviderResult(ProviderAttemptOutcome::Ambiguous);
            $this->attempts->finish(
                $clientId, $started['id'], $started['token'], $started['attempt_id'],
                $result->outcome, $result->httpStatus, $result->providerReference,
            );
        }

        $rows = $this->snapshot($clientId, $messageId);
        $state = 'accepted';
        foreach ($rows as $row) {
            if ($row['state'] !== 'accepted') {
                $state = 'review_required';
                break;
            }
        }

        return ['state' => $state, 'deliveries' => $rows];
    }

    /** @return list<array{id:string,state:string,error_code:?string,provider_ref:?string}> */
    private function snapshot(int $clientId, string $messageId): array
    {
        $rows = DB::table('deliveries as deliveries')
            ->leftJoin('delivery_attempts as attempts', 'attempts.delivery_id', '=', 'deliveries.id')
            ->where('deliveries.client_id', $clientId)
            ->where('deliveries.message_id', $messageId)
            ->orderBy('deliveries.id')
            ->get([
                'deliveries.id', 'deliveries.state', 'deliveries.last_error_code',
                'attempts.provider_ref',
            ]);

        return $rows->map(fn (object $row): array => [
            'id' => (string) $row->id,
            'state' => (string) $row->state,
            'error_code' => $row->last_error_code,
            'provider_ref' => $row->provider_ref,
        ])->all();
    }
}
