<?php

namespace App\Services;

use App\Enums\ProviderAttemptOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Provider-free durability boundary. Only explicit, routed provider accounts
 * can be assigned to a leased delivery, before a future adapter is invoked.
 *
 * This milestone permits exactly ONE attempt and never automatically retries.
 * No HTTP, SMTP, SMS, callback or external call is made by this service.
 */
final class DeliveryAttemptManager
{
    /**
     * Atomically create a durable "started" record before a possible send.
     * Return null if tenant, fencing, expiry or provider routing is invalid.
     */
    public function begin(int $clientId, string $deliveryId, string $leaseToken, int $providerAccountId): ?int
    {
        return DB::transaction(function () use (
            $clientId, $deliveryId, $leaseToken, $providerAccountId
        ): ?int {
            $delivery = $this->lockedLease($clientId, $deliveryId, $leaseToken);
            if (! $delivery || (int) $delivery->attempt_count !== 0) {
                return null;
            }

            $now = CarbonImmutable::now('UTC');
            $target = DB::table('delivery_targets')
                ->where('id', $delivery->target_id)
                ->where('client_id', $clientId)
                ->where('enabled', true)->first(['channel']);
            $message = DB::table('messages')
                ->where('id', $delivery->message_id)
                ->where('client_id', $clientId)
                ->first(['expires_at']);
            if (! $target || ! $message || ! $this->activeClient($clientId)
                || ($message->expires_at !== null
                    && CarbonImmutable::parse($message->expires_at, 'UTC')->lessThanOrEqualTo($now))) {
                return null;
            }

            $providerAllowed = DB::table('provider_accounts as accounts')
                ->join('client_provider_routes as routes', 'routes.provider_account_id', '=', 'accounts.id')
                ->where('accounts.id', $providerAccountId)
                ->where('accounts.enabled', true)
                ->where('accounts.channel', $target->channel)
                ->where('routes.client_id', $clientId)
                ->where('routes.channel', $target->channel)
                ->where('routes.enabled', true)
                ->where(function ($query) use ($clientId): void {
                    $query->whereNull('accounts.client_id')
                        ->orWhere('accounts.client_id', $clientId);
                })->exists();

            if (! $providerAllowed) {
                return null;
            }

            $attemptId = DB::table('delivery_attempts')->insertGetId([
                'delivery_id' => $deliveryId,
                'provider_account_id' => $providerAccountId,
                'attempt_no' => 1,
                'outcome' => 'started',
                'created_at' => $now,
            ]);
            DB::table('deliveries')->where('id', $deliveryId)->where('client_id', $clientId)
                ->where('state', 'leased')->where('lease_token', $leaseToken)
                ->update(['attempt_count' => 1, 'updated_at' => $now]);

            return $attemptId;
        }, 3);
    }

    /**
     * Record one sanitized outcome using a live fenced lease.
     *
     * "accepted" means acknowledged by provider, NOT delivered. A retryable
     * failure or uncertain network result goes to manual review; never
     * automatically resubmit or fail over (especially for OTP traffic).
     */
    public function finish(
        int $clientId,
        string $deliveryId,
        string $leaseToken,
        int $attemptId,
        ProviderAttemptOutcome $outcome,
        ?int $httpStatus = null,
        ?string $providerRef = null,
    ): bool {
        if ($httpStatus !== null && ($httpStatus < 100 || $httpStatus > 599)) {
            throw new InvalidArgumentException('HTTP status must be 100–599 or null.');
        }

        if ($providerRef !== null && ! preg_match('/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/D', $providerRef)) {
            throw new InvalidArgumentException('Provider reference must be a non-PII opaque identifier.');
        }

        if ($outcome !== ProviderAttemptOutcome::Accepted && $providerRef !== null) {
            throw new InvalidArgumentException('Provider reference is only recorded for accepted outcomes.');
        }

        return DB::transaction(function () use (
            $clientId, $deliveryId, $leaseToken, $attemptId, $outcome, $httpStatus, $providerRef
        ): bool {
            $delivery = $this->lockedLease($clientId, $deliveryId, $leaseToken);
            if (! $delivery || (int) $delivery->attempt_count !== 1) {
                return false;
            }

            $attempt = DB::table('delivery_attempts')
                ->where('id', $attemptId)
                ->where('delivery_id', $deliveryId)
                ->where('attempt_no', 1)
                ->where('outcome', 'started')
                ->lockForUpdate()->first();

            if (! $attempt) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            $changed = DB::table('delivery_attempts')
                ->where('id', $attemptId)->where('delivery_id', $deliveryId)
                ->where('outcome', 'started')
                ->update([
                    'outcome' => $outcome->value,
                    'provider_ref' => $providerRef,
                    'http_status' => $httpStatus,
                    'error_code' => $outcome->safeErrorCode(),
                ]);

            if ($changed !== 1) {
                throw new \LogicException('Provider attempt could not be finalized exactly once.');
            }

            $deliveryChanged = DB::table('deliveries')->where('id', $deliveryId)
                ->where('client_id', $clientId)->where('state', 'leased')
                ->where('lease_token', $leaseToken)
                ->update([
                    'state' => $outcome->deliveryState(),
                    'last_error_code' => $outcome->safeErrorCode(),
                    'lease_token' => null,
                    'lease_expires_at' => null,
                    'updated_at' => $now,
                ]);

            if ($deliveryChanged !== 1) {
                throw new \LogicException('Delivery outcome could not be finalized exactly once.');
            }

            return true;
        }, 3);
    }

    private function lockedLease(int $clientId, string $deliveryId, string $leaseToken): ?object
    {
        $delivery = DB::table('deliveries')
            ->where('id', $deliveryId)->where('client_id', $clientId)
            ->lockForUpdate()->first();

        if (! $delivery || $delivery->state !== 'leased'
            || ! is_string($delivery->lease_token)
            || ! hash_equals($delivery->lease_token, $leaseToken)
            || $delivery->lease_expires_at === null
            || CarbonImmutable::parse($delivery->lease_expires_at, 'UTC')
                ->lessThanOrEqualTo(CarbonImmutable::now('UTC'))) {
            return null;
        }

        return $delivery;
    }

    private function activeClient(int $clientId): bool
    {
        return DB::table('clients')->where('id', $clientId)
            ->where('status', 'active')->exists();
    }
}
