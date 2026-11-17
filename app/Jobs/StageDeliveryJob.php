<?php

namespace App\Jobs;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Deliberately does NOT call a provider. Milestone 3 only stages a durable
 * delivery for a future adapter. Serialized job data contains an opaque ID.
 */
class StageDeliveryJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public readonly string $deliveryId)
    {
        $this->onConnection('database');
        $this->onQueue('default');
    }

    public function handle(): void
    {
        DB::transaction(function (): void {
            $delivery = DB::table('deliveries')
                ->join('messages', function ($join): void {
                    $join->on('deliveries.message_id', '=', 'messages.id')
                        ->on('deliveries.client_id', '=', 'messages.client_id');
                })
                ->where('deliveries.id', $this->deliveryId)
                ->select('deliveries.id', 'deliveries.state', 'messages.expires_at')
                ->lockForUpdate()
                ->first();

            if (! $delivery || $delivery->state !== 'queued') {
                return;
            }

            $expired = $delivery->expires_at !== null
                && CarbonImmutable::parse($delivery->expires_at)->lessThanOrEqualTo(now());

            DB::table('deliveries')->where('id', $this->deliveryId)->where('state', 'queued')->update([
                'state' => $expired ? 'expired' : 'awaiting_provider',
                'updated_at' => now(),
            ]);
        });
    }
}
