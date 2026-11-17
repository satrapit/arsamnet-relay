<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SecurityMonitorController extends Controller
{
    public function index(): View
    {
        Gate::authorize('security.monitor');

        $since = now()->utc()->subMinutes(15);
        $events = DB::table('security_events')
            ->where('created_at', '>=', $since)
            ->selectRaw('event_code, COUNT(*) as total')
            ->groupBy('event_code')
            ->orderByDesc('total')
            ->get();

        $lastRun = DB::table('operation_heartbeats')
            ->where('name', 'scheduler')
            ->value('last_success_at');

        return view('admin.security-monitor', [
            'events' => $events,
            'lastRun' => $lastRun,
            'schedulerStale' => !$lastRun || Carbon::parse($lastRun, 'UTC')->lt(now()->utc()->subMinutes(3)),
            'timeWindow' => '15 minutes',
        ]);
    }
}
