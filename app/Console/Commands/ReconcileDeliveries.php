<?php

namespace App\Console\Commands;

use App\Services\DeliveryLeaseManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Bounded, provider-free reconciliation. Never starts an attempt or invokes
 * an outbound adapter. Dry-run by default; safe for short-lived cPanel CLI.
 */
class ReconcileDeliveries extends Command
{
    protected $signature = 'relay:reconcile-deliveries
        {--limit=100 : Maximum candidate deliveries (1-500)}
        {--apply : Persist safe state transitions instead of previewing}';

    protected $description = 'Stage never-attempted queued deliveries and quarantine expired leases without sending';

    public function handle(DeliveryLeaseManager $leases): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        if ($limit === false || $limit < 1 || $limit > 500) {
            $this->error('The --limit value must be between 1 and 500.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $now = now()->utc();
        $candidates = DB::table('deliveries as d')
            ->join('messages as m', function ($join): void {
                $join->on('m.id', '=', 'd.message_id')
                    ->on('m.client_id', '=', 'd.client_id');
            })
            ->where(function ($query) use ($now): void {
                $query->where('d.state', 'queued')
                    ->orWhere(function ($lease) use ($now): void {
                        $lease->where('d.state', 'leased')
                            ->whereNotNull('d.lease_expires_at')
                            ->where('d.lease_expires_at', '<=', $now);
                    });
            })
            ->orderBy('d.created_at')->orderBy('d.id')
            ->limit($limit)
            ->get(['d.id', 'd.client_id', 'd.state', 'd.attempt_count',
                'd.lease_token', 'd.lease_expires_at', 'm.expires_at', 'm.scheduled_at']);

        $counts = ['staged' => 0, 'expired' => 0, 'quarantined' => 0, 'skipped' => 0];
        foreach ($candidates as $candidate) {
            if ($candidate->state === 'leased') {
                if ($apply && $leases->quarantineExpired((int) $candidate->client_id, (string) $candidate->id)) {
                    $counts['quarantined']++;
                } elseif (! $apply) {
                    $counts['quarantined']++;
                } else {
                    $counts['skipped']++;
                }

                continue;
            }

            // A queued row with any evidence of a previous attempt must
            // never become dispatchable through automatic reconciliation.
            if ((int) $candidate->attempt_count !== 0
                || $candidate->lease_token !== null
                || $candidate->lease_expires_at !== null
                || DB::table('delivery_attempts')->where('delivery_id', $candidate->id)->exists()) {
                $counts['skipped']++;

                continue;
            }

            if ($candidate->scheduled_at !== null && $candidate->scheduled_at > $now->toDateTimeString()) {
                $counts['skipped']++;

                continue;
            }

            $expired = $candidate->expires_at !== null
                && $candidate->expires_at <= $now->toDateTimeString();
            if (! $apply) {
                $counts[$expired ? 'expired' : 'staged']++;

                continue;
            }

            // The conditional update is idempotent with the existing
            // StageDeliveryJob and cannot reclaim an attempted delivery.
            $changed = DB::table('deliveries')
                ->where('id', $candidate->id)
                ->where('client_id', $candidate->client_id)
                ->where('state', 'queued')
                ->where('attempt_count', 0)
                ->whereNull('lease_token')
                ->whereNull('lease_expires_at')
                ->whereNotExists(function ($query) use ($candidate): void {
                    $query->selectRaw('1')->from('delivery_attempts')
                        ->where('delivery_id', $candidate->id);
                })
                ->update(['state' => $expired ? 'expired' : 'awaiting_provider', 'updated_at' => now()->utc()]);
            $counts[$changed === 1 ? ($expired ? 'expired' : 'staged') : 'skipped']++;
        }

        $this->info(($apply ? 'Applied' : 'Preview').' (max '.$limit.'): '.json_encode($counts, JSON_THROW_ON_ERROR));
        $this->line('No provider calls, retries or delivery acknowledgments performed.');

        return self::SUCCESS;
    }
}
