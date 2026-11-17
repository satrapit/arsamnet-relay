<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Read-only provider routing hint for a staged, never-attempted delivery.
 *
 * Lower route priority numbers win. This does NOT lease the delivery,
 * authorize a send, or permit retry/failover after an uncertain attempt.
 * The claim/attempt coordinator MUST revalidate ownership and route state
 * under its own transactional fencing checks before any provider I/O.
 */
final class ProviderRouteResolver
{
    public function resolveForDelivery(int $clientId, string $deliveryId): ?int
    {
        if ($clientId < 1 || ! preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $deliveryId)) {
            return null;
        }

        $delivery = DB::table('deliveries')
            ->where('client_id', $clientId)
            ->where('id', $deliveryId)
            ->where('state', 'awaiting_provider')
            ->where('attempt_count', 0)
            ->whereNull('lease_token')
            ->whereNull('lease_expires_at')
            ->first(['message_id', 'target_id']);

        if (! $delivery || ! DB::table('clients')
            ->where('id', $clientId)->where('status', 'active')->exists()) {
            return null;
        }

        $target = DB::table('delivery_targets')
            ->where('id', $delivery->target_id)
            ->where('client_id', $clientId)
            ->where('enabled', true)
            ->first(['channel']);

        if (! $target || ! in_array($target->channel, ['sms', 'email', 'webhook'], true)) {
            return null;
        }

        $message = DB::table('messages')
            ->where('id', $delivery->message_id)
            ->where('client_id', $clientId)
            ->first(['expires_at', 'scheduled_at']);

        if (! $message) {
            return null;
        }

        $now = CarbonImmutable::now('UTC');

        if (($message->expires_at !== null
                && CarbonImmutable::parse($message->expires_at, 'UTC')->lessThanOrEqualTo($now))
            || ($message->scheduled_at !== null
                && CarbonImmutable::parse($message->scheduled_at, 'UTC')->greaterThan($now))) {
            return null;
        }

        $query = DB::table('client_provider_routes as routes')
            ->join('provider_accounts as accounts', 'accounts.id', '=', 'routes.provider_account_id')
            ->where('routes.client_id', $clientId)
            ->where('routes.channel', $target->channel)
            ->where('routes.enabled', true)
            ->where('accounts.channel', $target->channel)
            ->where('accounts.enabled', true)
            ->where(function ($query) use ($clientId): void {
                $query->whereNull('accounts.client_id')
                    ->orWhere('accounts.client_id', $clientId);
            });

        // A local fake adapter must never be an eligible production route.
        if (! app()->environment('local', 'testing')) {
            $query->where('accounts.adapter_code', '!=', 'fake-only');
        }

        $accountId = $query
            ->orderBy('routes.priority')
            ->orderBy('routes.id')
            ->value('accounts.id');

        return $accountId === null ? null : (int) $accountId;
    }
}
