<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

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

        return view('admin.delivery-reviews', compact('owner', 'deliveries'));
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
