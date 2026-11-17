<?php

namespace App\Services;

use App\Enums\ProviderAttemptOutcome;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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
    ) {}

    /**
     * Simulate a provider accepting one staged delivery.
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
            ->first(['target_id']);

        if (! $provider || ! $delivery
            || ($provider->client_id !== null && (int) $provider->client_id !== $clientId)
            || ! DB::table('clients')->where('id', $clientId)->where('status', 'active')->exists()) {
            return null;
        }

        $target = DB::table('delivery_targets')
            ->where('id', $delivery->target_id)
            ->where('client_id', $clientId)
            ->where('enabled', true)
            ->first(['channel']);

        if (! $target || $target->channel !== $provider->channel
            || ! DB::table('client_provider_routes')
                ->where('client_id', $clientId)
                ->where('provider_account_id', $providerAccountId)
                ->where('channel', $target->channel)
                ->where('enabled', true)->exists()) {
            return null;
        }

        try {
            $config = json_decode(Crypt::decryptString((string) $provider->credentials_ciphertext),
                true, 512, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null;
        }

        if (! is_array($config) || ! is_string($config['callback_secret'] ?? null)
            || strlen($config['callback_secret']) < 32) {
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

        $providerRef = 'fake-'.(string) Str::ulid();
        if (! $this->attempts->finish(
            $clientId, $deliveryId, $token, $attemptId,
            ProviderAttemptOutcome::Accepted, 202, $providerRef,
        )) {
            return null;
        }

        // Accepted is not delivered; only a separately verified fake receipt
        // may transition this delivery to delivered.
        return ['attempt_id' => $attemptId, 'provider_ref' => $providerRef];
    }
}
