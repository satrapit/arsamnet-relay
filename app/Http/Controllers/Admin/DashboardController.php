<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

final class DashboardController extends Controller
{
    public function __invoke(): View
    {
        Gate::authorize('dashboard.view');

        $counts = [
            'clients' => DB::table('clients')->where('status', 'active')->count(),
            'messages' => DB::table('messages')->count(),
            'accepted' => DB::table('deliveries')->where('state', 'accepted')->count(),
            'review' => DB::table('deliveries')->where('state', 'review_required')->count(),
            'delivered' => DB::table('deliveries')->where('state', 'delivered')->count(),
            'today' => DB::table('deliveries')->where('created_at', '>=', now()->utc()->startOfDay())->count(),
        ];

        $flags = [
            'publishing' => Feature::globally()->active('message-publishing'),
            'sending' => Feature::globally()->active('kavenegar-sending'),
            'status' => Feature::globally()->active('provider-status-queries'),
        ];

        // Read-only operational overview; no provider network access.
        $recent = DB::table('deliveries as d')
            ->join('clients as c', 'c.id', '=', 'd.client_id')
            ->whereIn('d.state', ['review_required', 'accepted', 'delivered'])
            ->orderByDesc('d.updated_at')
            ->limit(7)
            ->get(['d.id', 'd.client_id', 'c.name as client_name', 'd.state', 'd.updated_at']);

        return view('dashboard.index', compact('counts', 'flags', 'recent'));
    }
}
