<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\JsonResponse;
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

        $groupDefinitions = [
            'Message delivery' => [
                'icon' => 'send',
                'description' => 'Control message intake, scheduling and provider network access.',
                'keys' => ['message-publishing', 'scheduled-messages', 'kavenegar-sending'],
            ],
            'Providers & integrations' => [
                'icon' => 'plug',
                'description' => 'Manage provider configuration and optional delivery services.',
                'keys' => ['client-provider-overrides', 'provider-status-queries', 'webhook-delivery'],
            ],
        ];

        $byKey = collect($flags)->keyBy('key');
        $groups = collect($groupDefinitions)->map(fn (array $group) => [
            'icon' => $group['icon'],
            'description' => $group['description'],
            'flags' => collect($group['keys'])->map(fn (string $key) => $byKey->get($key))
                ->filter()->values()->all(),
        ]);

        return view('admin.feature-flags', [
            'groups' => $groups,
            'hourlyLimit' => (int) Feature::globally()->value('kavenegar-hourly-limit'),
        ]);
    }

    public function update(Request $request, string $key): JsonResponse|RedirectResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Setting updated successfully.',
                'key' => $key,
                'enabled' => $enabled,
            ], 200, ['Cache-Control' => 'no-store']);
        }

        return back()->with('status', 'Setting updated successfully.');
    }

    public function updateHourlyLimit(Request $request): JsonResponse|RedirectResponse
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

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Hourly SMS limit saved.',
                'hourly_limit' => $limit,
            ], 200, ['Cache-Control' => 'no-store']);
        }

        return back()->with('status', 'Hourly SMS limit saved.');
    }
}
