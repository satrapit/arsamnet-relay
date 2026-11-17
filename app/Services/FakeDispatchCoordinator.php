<?php

namespace App\Services;

use App\DTO\OutboundProviderRequest;
use App\Enums\ProviderAttemptOutcome;
use App\Enums\ProviderCapability;
use App\Providers\Adapters\FakeOutboundProviderAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Deliberately local/test-only, fake-only, and NOT registered with the worker.
 *
 * Exercises the storage/lease/attempt seams without contacting a provider.
 * Real adapters must be implemented separately with explicit approval.
 */
final class FakeDispatchCoordinator
{
    public function __construct(
        private readonly DeliveryLeaseManager $leases,
        private readonly DeliveryAttemptManager $attempts,
        private readonly ProviderRouteSelector $routes,
    ) {}

    /**
     * Select one first-attempt provider only, without automatic failover.
     *
     * @return array{attempt_id: int, provider_ref: string}|null
     */
    public function dispatchFirstEligible(int $clientId, string $deliveryId): ?array
    {
        if (! app()->environment('local', 'testing') || $clientId < 1) {
            return null;
        }

        $accountId = $this->routes->select($clientId, $deliveryId);

        return $accountId === null ? null : $this->dispatch($clientId, $deliveryId, $accountId);
    }

    /**
     * Use the no-network fake adapter to accept one staged delivery.
     *
     * @return array{attempt_id: int, provider_ref: string}|null
     */
    public function dispatch(int $clientId, string $deliveryId, int $providerAccountId): ?array
    {
        if (! app()->environment('local', 'testing') || $clientId < 1 || $providerAccountId < 1) {
            return null;
        }

        // Validate the fake provider and route BEFORE taking a lease, to
        // avoid stranding an otherwise valid delivery on obvious failures.
        $provider = DB::table('provider_accounts')
            ->where('id', $providerAccountId)
            ->where('enabled', true)
            ->where('adapter_code', 'fake-only')
            ->first();

        $delivery = DB::table('deliveries')
            ->where('id', $deliveryId)
            ->where('client_id', $clientId)
            ->where('state', 'awaiting_provider')
            ->first(['target_id', 'message_id']);

        if (! $provider || ! $delivery
            || ($provider->client_id !== null && (int) $provider->client_id !== $clientId)
            || ! DB::table('clients')->where('id', $clientId)->where('status', 'active')->exists()) {
            return null;
        }

        $target = DB::table('delivery_targets')
            ->where('id', $delivery->target_id)
            ->where('client_id', $clientId)
            ->where('enabled', true)
            ->first(['channel', 'address_ciphertext']);

        if (! $target || $target->channel !== $provider->channel
            || ! DB::table('client_provider_routes')
                ->where('client_id', $clientId)
                ->where('provider_account_id', $providerAccountId)
                ->where('channel', $target->channel)
                ->where('enabled', true)->exists()) {
            return null;
        }

        $message = DB::table('messages')
            ->where('id', $delivery->message_id)
            ->where('client_id', $clientId)
            ->first(['payload_ciphertext', 'scheduled_at']);

        if (! $message || ($message->scheduled_at !== null
            && $message->scheduled_at > now()->utc()->toDateTimeString())) {
            return null;
        }

        // Keep decrypted destination/content in process memory only.
        // This fake adapter performs no transport and no logging.
        try {
            $config = json_decode(Crypt::decryptString((string) $provider->credentials_ciphertext),
                true, 512, JSON_THROW_ON_ERROR);
            $destination = Crypt::decryptString((string) $target->address_ciphertext);
            $content = Crypt::decryptString((string) $message->payload_ciphertext);
            $request = new OutboundProviderRequest(
                $deliveryId, (string) $target->channel, $destination, $content,
            );
            $adapter = new FakeOutboundProviderAdapter((string) $target->channel);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($config) || ! is_string($config['callback_secret'] ?? null)
            || strlen($config['callback_secret']) < 32
            || $adapter->code() !== $provider->adapter_code
            || ! in_array(ProviderCapability::Send, $adapter->capabilities(), true)) {
            return null;
        }

        $token = $this->leases->claim($clientId, $deliveryId, 60);
        if ($token === null) {
            return null;
        }

        // This is a durable DB record, not an external network request.
        $attemptId = $this->attempts->begin($clientId, $deliveryId, $token, $providerAccountId);
        if ($attemptId === null) {
            // Never auto-release an uncertain lease for retry.
            return null;
        }

        // Provider calls happen only AFTER the durable attempt begins.
        // A fake adapter never uses network I/O. If a future fault occurs,
        // quarantine the attempt rather than blindly retrying.
        try {
            $result = $adapter->send($request);
        } catch (Throwable) {
            $this->attempts->finish(
                $clientId, $deliveryId, $token, $attemptId,
                ProviderAttemptOutcome::Ambiguous,
            );

            return null;
        }

        if ($result->outcome === ProviderAttemptOutcome::Accepted
            && $result->providerReference === null) {
            $this->attempts->finish(
                $clientId, $deliveryId, $token, $attemptId,
                ProviderAttemptOutcome::Ambiguous,
            );

            return null;
        }

        if (! $this->attempts->finish(
            $clientId, $deliveryId, $token, $attemptId,
            $result->outcome, $result->httpStatus, $result->providerReference,
        )) {
            return null;
        }

        // Accepted is not delivered; only a separately verified fake receipt
        // may transition this delivery to delivered.
        if ($result->outcome !== ProviderAttemptOutcome::Accepted) {
            return null;
        }

        return ['attempt_id' => $attemptId, 'provider_ref' => $result->providerReference];
    }
}
