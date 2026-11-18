<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\KavenegarDeliveryStatus;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

/**
 * Privileged, read-only delivery triage with a signed acknowledgement.
 * No delivery state changes, outbound calls, retries or operator overrides.
 */
final class DeliveryReviewController extends Controller
{
    public function index(int $client): View
    {
        Gate::authorize('clients.manage');
        $owner = DB::table('clients')->where('id', $client)->first(['id', 'name', 'slug']);
        abort_unless($owner, 404);

        $deliveries = DB::table('deliveries')
            ->where('client_id', $client)
            ->where('state', 'review_required')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(25, [
                'id', 'message_id', 'attempt_count', 'last_error_code', 'updated_at',
            ]);

        $accepted = DB::table('deliveries as d')
            ->join('delivery_attempts as a', 'a.delivery_id', '=', 'd.id')
            ->join('provider_accounts as p', 'p.id', '=', 'a.provider_account_id')
            ->where('d.client_id', $client)->where('d.state', 'accepted')
            ->where('a.outcome', 'accepted')->where('a.attempt_no', 1)
            ->where('p.adapter_code', 'kavenegar')
            ->whereNotNull('a.provider_ref')
            ->orderByDesc('d.updated_at')->limit(25)
            ->get(['d.id', 'd.updated_at']);

        return view('admin.delivery-reviews', compact('owner', 'deliveries', 'accepted'));
    }

    public function history(int $client, string $delivery): View
    {
        Gate::authorize('clients.manage');
        abort_unless(preg_match('/\\A[0-9A-HJKMNP-TV-Z]{26}\\z/D', $delivery) === 1, 404);

        $owner = DB::table('clients')->where('id', $client)->first(['id', 'name', 'slug']);
        abort_unless($owner, 404);
        $record = DB::table('deliveries')
            ->where('client_id', $client)
            ->where('id', $delivery)
            ->first(['id', 'state', 'attempt_count', 'last_error_code']);
        abort_unless($record, 404);

        // Audit data stays tenant scoped. JSON path checks the exact opaque
        // delivery ID, not an unsafe substring of a serialized event.
        $events = DB::table('security_events')
            ->where('client_id', $client)
            ->whereIn('event_code', [
                'delivery.review_acknowledged',
                'delivery.provider_status_queried',
            ])
            ->where('context_json->delivery_id', $delivery)
            ->orderByDesc('id')
            ->paginate(25, [
                'id', 'actor_user_id', 'event_code', 'context_json',
                'created_at', 'severity', 'client_id', 'source_ip_hmac', 'record_hmac',
            ]);

        $events->getCollection()->transform(function (object $event): object {
            $verified = SecurityAudit::verifyRecord($event);
            $context = $verified ? json_decode((string) $event->context_json, true) : [];

            return (object) [
                'id' => $event->id,
                'created_at' => $event->created_at,
                'actor_user_id' => $verified ? $event->actor_user_id : null,
                'event_code' => $verified ? $event->event_code : 'integrity_failed',
                'review_action' => $verified ? ($context['review_action'] ?? null) : null,
                'provider_status_code' => $verified ? ($context['provider_status_code'] ?? null) : null,
                'verified' => $verified,
            ];
        });

        return view('admin.delivery-review-history', compact('owner', 'record', 'events'));
    }

    public function checkStatus(
        Request $request,
        int $client,
        string $delivery,
        KavenegarDeliveryStatus $statuses,
    ): RedirectResponse {
        Gate::authorize('clients.manage');
        abort_unless(preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $delivery) === 1, 404);
        abort_unless(DB::table('clients')->where('id', $client)->exists(), 404);

        // This is a manual, separate network gate. It never sends messages,
        // changes attempts or marks a delivery as delivered.
        if (! Feature::globally()->active('provider-status-queries')) {
            return back()->with('status', 'Provider status queries are disabled.');
        }

        $result = $statuses->query($client, $delivery);
        if ($result['state'] === 'unavailable') {
            abort(404);
        }

        DB::transaction(function () use ($request, $client, $delivery, $result): void {
            SecurityAudit::record(
                'delivery.provider_status_queried',
                'high',
                (int) $request->user()->getKey(),
                [
                    'delivery_id' => $delivery,
                    'review_action' => $result['state'],
                    'provider_status_code' => $result['provider_code'],
                ],
                $client,
                $request->ip(),
            );
        }, 3);

        return back()->with(
            'status',
            'Kavenegar status: '.$result['state']
                .'. Delivery state unchanged. No resend performed.'
        );
    }

    public function acknowledge(Request $request, int $client, string $delivery): RedirectResponse
    {
        Gate::authorize('clients.manage');

        // Only an opaque delivery ID is accepted; no free-form notes, SMS
        // contents or provider error bodies enter this endpoint or audit log.
        abort_unless(preg_match('/\A[0-9A-HJKMNP-TV-Z]{26}\z/D', $delivery) === 1, 404);

        DB::transaction(function () use ($request, $client, $delivery): void {
            $row = DB::table('deliveries')
                ->where('id', $delivery)
                ->where('client_id', $client)
                ->lockForUpdate()
                ->first(['id', 'state']);

            abort_unless($row && $row->state === 'review_required', 404);

            SecurityAudit::record(
                'delivery.review_acknowledged',
                'high',
                (int) $request->user()->getKey(),
                ['delivery_id' => $delivery, 'review_action' => 'investigating'],
                $client,
                $request->ip(),
            );
        }, 3);

        return redirect()->route('clients.delivery-reviews.index', $client)
            ->with('status', 'Review acknowledged. Delivery remains review required; no message was sent.');
    }
}
