<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Laravel\Pennant\Feature;

class FeatureFlagController extends Controller
{
    public function index(): View
    {
        Gate::authorize('feature-flags.view');

        $flags = [];
        foreach (config('relay.flags') as $key => $description) {
            $flags[] = [
                'key' => $key,
                'description' => $description,
                'enabled' => Feature::globally()->active($key),
            ];
        }

        return view('admin.feature-flags', [
            'flags' => $flags,
            'hourlyLimit' => (int) Feature::globally()->value('kavenegar-hourly-limit'),
        ]);
    }

    public function update(Request $request, string $key): RedirectResponse
    {
        Gate::authorize('feature-flags.write');
        abort_unless(array_key_exists($key, config('relay.flags')), 404);

        $validated = $request->validate(['enabled' => ['required', 'boolean']]);
        $enabled = (bool) $validated['enabled'];

        DB::transaction(function () use ($key, $enabled, $request): void {
            $previous = Feature::globally()->active($key);
            if ($enabled) {
                Feature::globally()->activate($key);
            } else {
                Feature::globally()->deactivate($key);
            }

            SecurityAudit::record('feature_flag.changed', 'high', (int) $request->user()->getKey(), [
                'flag_key' => $key,
                'old_state' => $previous,
                'new_state' => $enabled,
                'scope' => 'global',
            ], null, $request->ip());
        });

        return back()->with('status', 'Feature flag updated.');
    }
    public function updateHourlyLimit(Request $request): RedirectResponse
    {
        Gate::authorize('feature-flags.write');
        $validated = $request->validate([
            'hourly_limit' => ['required', 'integer', 'between:1,10000'],
        ]);
        $limit = (int) $validated['hourly_limit'];

        DB::transaction(function () use ($request, $limit): void {
            $previous = (int) Feature::globally()->value('kavenegar-hourly-limit');
            Feature::globally()->activate('kavenegar-hourly-limit', $limit);
            SecurityAudit::record('feature_flag.changed', 'high', (int) $request->user()->getKey(), [
                'flag_key' => 'kavenegar-hourly-limit',
                'old_state' => $previous,
                'new_state' => $limit,
                'scope' => 'global',
            ], null, $request->ip());
        });

        return back()->with('status', 'Kavenegar hourly limit updated.');
    }

}
