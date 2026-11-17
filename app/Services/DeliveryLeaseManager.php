<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Internal, provider-free lease/fencing primitive for future delivery workers.
 *
 * A stale lease is NOT automatically reclaimed: a network request may have
 * reached a provider before a worker died. Expired leases require review.
 */
final class DeliveryLeaseManager
{
    private const MIN_TTL = 15;

    private const MAX_TTL = 120;

    /**
     * Claim only a staged delivery owned by this client. Return a random
     * fencing token, or null when the delivery cannot be claimed.
     */
    public function claim(int $clientId, string $deliveryId, int $ttlSeconds = 45): ?string
    {
        self::checkTtl($ttlSeconds);

        return DB::transaction(function () use ($clientId, $deliveryId, $ttlSeconds): ?string {
            $delivery = DB::table('deliveries')
                ->where('client_id', $clientId)
                ->where('id', $deliveryId)
                ->lockForUpdate()
                ->first();

            if (! $delivery || $delivery->state !== 'awaiting_provider'
                || $delivery->lease_token !== null || $delivery->lease_expires_at !== null) {
                return null;
            }

            $now = CarbonImmutable::now('UTC');
            $message = DB::table('messages')
                ->where('client_id', $clientId)
                ->where('id', $delivery->message_id)
                ->first(['expires_at']);

            if (! $message) {
                return null;
            }

            $expiry = $message->expires_at === null
                ? null : CarbonImmutable::parse($message->expires_at, 'UTC');

            if ($expiry !== null && $expiry->lessThanOrEqualTo($now)) {
                DB::table('deliveries')->where('client_id', $clientId)
                    ->where('id', $deliveryId)->where('state', 'awaiting_provider')
                    ->update(['state' => 'expired', 'updated_at' => $now]);

                return null;
            }

            if (! $this->activeOwnership($clientId, (int) $delivery->target_id)) {
                return null;
            }

            $deadline = $now->addSeconds($ttlSeconds);
            if ($expiry !== null && $expiry->lessThan($deadline)) {
                $deadline = $expiry;
            }

            $token = (string) Str::uuid();
            $updated = DB::table('deliveries')->where('client_id', $clientId)
                ->where('id', $deliveryId)->where('state', 'awaiting_provider')
                ->update([
                    'state' => 'leased',
                    'lease_token' => $token,
                    'lease_expires_at' => $deadline,
                    'updated_at' => $now,
                ]);

            if ($updated !== 1) {
                throw new \LogicException('A staged delivery was not claimed exactly once.');
            }

            return $token;
        }, 3);
    }

    /**
     * Extend only the live lease owned by this exact token. Even a worker
     * holding an old token cannot extend a reassigned or expired lease.
     */
    public function renew(int $clientId, string $deliveryId, string $token, int $ttlSeconds = 45): bool
    {
        self::checkTtl($ttlSeconds);

        return DB::transaction(function () use ($clientId, $deliveryId, $token, $ttlSeconds): bool {
            $delivery = DB::table('deliveries')->where('client_id', $clientId)
                ->where('id', $deliveryId)->lockForUpdate()->first();

            if (! $delivery || $delivery->state !== 'leased'
                || ! is_string($delivery->lease_token)
                || ! hash_equals($delivery->lease_token, $token)
                || $delivery->lease_expires_at === null) {
                return false;
            }

            $now = CarbonImmutable::now('UTC');
            if (CarbonImmutable::parse($delivery->lease_expires_at, 'UTC')->lessThanOrEqualTo($now)) {
                return false;
            }

            $message = DB::table('messages')->where('client_id', $clientId)
                ->where('id', $delivery->message_id)->first(['expires_at']);
            if (! $message || ! $this->activeOwnership($clientId, (int) $delivery->target_id)) {
                return false;
            }

            $deadline = $now->addSeconds($ttlSeconds);
            if ($message->expires_at !== null) {
                $expiry = CarbonImmutable::parse($message->expires_at, 'UTC');
                if ($expiry->lessThanOrEqualTo($now)) {
                    return false;
                }
                if ($expiry->lessThan($deadline)) {
                    $deadline = $expiry;
                }
            }

            return DB::table('deliveries')->where('client_id', $clientId)
                ->where('id', $deliveryId)->where('state', 'leased')
                ->where('lease_token', $token)->where('lease_expires_at', '>', $now)
                ->update(['lease_expires_at' => $deadline, 'updated_at' => $now]) === 1;
        }, 3);
    }

    /**
     * Quarantine a timed-out lease for operator review, never for automatic
     * retry. This method does not send, acknowledge or mark delivered.
     */
    public function quarantineExpired(int $clientId, string $deliveryId): bool
    {
        return DB::table('deliveries')->where('client_id', $clientId)
            ->where('id', $deliveryId)->where('state', 'leased')
            ->whereNotNull('lease_token')->whereNotNull('lease_expires_at')
            ->where('lease_expires_at', '<=', CarbonImmutable::now('UTC'))
            ->update([
                'state' => 'review_required',
                'lease_token' => null,
                'lease_expires_at' => null,
                'last_error_code' => 'LEASE_EXPIRED_UNCERTAIN',
                'updated_at' => CarbonImmutable::now('UTC'),
            ]) === 1;
    }

    private function activeOwnership(int $clientId, int $targetId): bool
    {
        return DB::table('clients')->where('id', $clientId)
            ->where('status', 'active')->exists()
            && DB::table('delivery_targets')->where('client_id', $clientId)
                ->where('id', $targetId)->where('enabled', true)->exists();
    }

    private static function checkTtl(int $seconds): void
    {
        if ($seconds < self::MIN_TTL || $seconds > self::MAX_TTL) {
            throw new InvalidArgumentException('Lease TTL must be between 15 and 120 seconds.');
        }
    }
}
