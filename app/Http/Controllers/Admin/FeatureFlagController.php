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

        return view('admin.feature-flags', ['flags' => $flags]);
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
            ]);
        });

        return back()->with('status', 'Feature flag updated.');
    }
}
