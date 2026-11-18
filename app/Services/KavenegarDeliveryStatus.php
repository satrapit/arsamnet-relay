<?php

namespace App\Services;

use App\Providers\Adapters\KavenegarSmsAdapter;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Laravel\Pennant\Feature;
use Throwable;

/**
 * Read-only provider-status lookup for exactly one tenant-owned, accepted
 * attempt. Does not send, retry, settle or change delivery state.
 */
final class KavenegarDeliveryStatus
{
    /** @return array{state:string,provider_code:?int} */
    public function query(int $clientId, string $deliveryId): array
    {
        if (! Feature::globally()->active('provider-status-queries')) {
            return ['state' => 'disabled', 'provider_code' => null];
        }

        $row = DB::table('deliveries as d')
            ->join('delivery_attempts as a', 'a.delivery_id', '=', 'd.id')
            ->join('provider_accounts as p', 'p.id', '=', 'a.provider_account_id')
            ->join('clients as c', 'c.id', '=', 'd.client_id')
            ->where('d.id', $deliveryId)
            ->where('d.client_id', $clientId)
            ->whereIn('d.state', ['accepted', 'review_required'])
            ->where('a.attempt_no', 1)
            ->where('a.outcome', 'accepted')
            ->where('p.adapter_code', 'kavenegar')
            ->where('p.channel', 'sms')
            ->where('p.enabled', true)
            ->where('c.status', 'active')
            ->where(function ($query) use ($clientId): void {
                $query->whereNull('p.client_id')->orWhere('p.client_id', $clientId);
            })
            ->first(['a.provider_ref', 'p.credentials_ciphertext']);

        if (! $row || ! is_string($row->provider_ref)
            || ! preg_match('/\Akv-([0-9]{1,18})\z/D', $row->provider_ref, $matches)) {
            return ['state' => 'unavailable', 'provider_code' => null];
        }

        try {
            $credentials = json_decode(
                Crypt::decryptString((string) $row->credentials_ciphertext),
                true, 512, JSON_THROW_ON_ERROR
            );
            if (! is_array($credentials)) {
                return ['state' => 'unavailable', 'provider_code' => null];
            }
            $adapter = new KavenegarSmsAdapter(
                $credentials['api_key'] ?? '',
                $credentials['sender'] ?? '',
            );

            return $adapter->queryStatus($matches[1]);
        } catch (Throwable) {
            return ['state' => 'unknown', 'provider_code' => null];
        }
    }
}
