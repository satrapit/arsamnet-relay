<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Read-only first-hop provider selection for a fresh, staged delivery.
 *
 * This is NOT retry/failover policy. Never select a second provider after
 * an attempt has begun or after an ambiguous provider response.
 * No credentials are decrypted and no network call or DB write occurs.
 */
final class ProviderRouteSelector
{
    /**
     * Return the highest-priority eligible account ID, or null.
     *
     * Disabled routes/accounts are excluded up front. A higher priority
     * number denotes a lower preference. Route priority is unique within
     * each client/channel, so selection is deterministic.
     */
    public function select(int $clientId, string $deliveryId): ?int
    {
        if ($clientId < 1 || $deliveryId === '') {
            return null;
        }

        $accountId = DB::table('deliveries as deliveries')
            ->join('clients as clients', 'clients.id', '=', 'deliveries.client_id')
            ->join('messages as messages', function ($join): void {
                $join->on('messages.id', '=', 'deliveries.message_id')
                    ->on('messages.client_id', '=', 'deliveries.client_id');
            })
            ->join('delivery_targets as targets', function ($join): void {
                $join->on('targets.id', '=', 'deliveries.target_id')
                    ->on('targets.client_id', '=', 'deliveries.client_id');
            })
            ->join('client_provider_routes as routes', function ($join): void {
                $join->on('routes.client_id', '=', 'deliveries.client_id')
                    ->on('routes.channel', '=', 'targets.channel');
            })
            ->join('provider_accounts as accounts', function ($join): void {
                $join->on('accounts.id', '=', 'routes.provider_account_id')
                    ->on('accounts.channel', '=', 'targets.channel');
            })
            ->where('deliveries.id', $deliveryId)
            ->where('deliveries.client_id', $clientId)
            ->where('deliveries.state', 'awaiting_provider')
            ->where('deliveries.attempt_count', 0)
            ->whereNull('deliveries.lease_token')
            ->whereNull('deliveries.lease_expires_at')
            ->where('clients.status', 'active')
            ->where('targets.enabled', true)
            ->where('routes.enabled', true)
            ->where('accounts.enabled', true)
            ->where(function ($query) use ($clientId): void {
                $query->whereNull('accounts.client_id')
                    ->orWhere('accounts.client_id', $clientId);
            })
            ->where(function ($query): void {
                $query->whereNull('messages.expires_at')
                    ->orWhere('messages.expires_at', '>', now()->utc());
            })
            ->orderBy('routes.priority')
            ->orderBy('routes.id')
            ->value('accounts.id');

        return $accountId === null ? null : (int) $accountId;
    }
}
