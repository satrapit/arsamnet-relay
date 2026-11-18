<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\IpCidr;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientIpAllowlistController extends Controller
{
    public function index(int $client): View
    {
        Gate::authorize('clients.manage');
        $owner = DB::table('clients')->where('id', $client)->first();
        abort_unless($owner, 404);

        return view('admin.client-ips', [
            'owner' => $owner,
            'rules' => DB::table('client_ip_allowlist')
                ->where('client_id', $client)->orderBy('cidr')->get(),
        ]);
    }

    public function store(Request $request, int $client): RedirectResponse
    {
        Gate::authorize('clients.manage');
        $validated = $request->validate(['cidr' => ['required', 'string', 'max:49']]);
        $normalized = IpCidr::normalize($validated['cidr']);
        if ($normalized === null) {
            throw ValidationException::withMessages([
                'cidr' => 'Enter a valid IPv4 or IPv6 CIDR with a nonzero prefix.',
            ]);
        }

        DB::transaction(function () use ($request, $client, $normalized): void {
            // Serialize edits per client to prevent duplicate inserts and cap bypasses.
            $owner = DB::table('clients')->where('id', $client)->lockForUpdate()->first();
            abort_unless($owner && $owner->status === 'active', 404);

            if (DB::table('client_ip_allowlist')
                ->where('client_id', $client)->where('cidr', $normalized)->exists()) {
                throw ValidationException::withMessages(['cidr' => 'This network is already allowed.']);
            }
            if (DB::table('client_ip_allowlist')->where('client_id', $client)->count() >= 64) {
                throw ValidationException::withMessages(['cidr' => 'Maximum 64 networks per client.']);
            }

            $id = DB::table('client_ip_allowlist')->insertGetId([
                'client_id' => $client,
                'cidr' => $normalized,
                'enabled' => true,
                'created_at' => now()->utc(),
            ]);
            SecurityAudit::record('client.ip_allowlist.created', 'high', (int) $request->user()->getKey(), [
                'client_id' => $client, 'ip_rule_id' => $id,
            ], $client, $request->ip());
        });

        return back()->with('status', 'Allowed network added.');
    }

    public function destroy(Request $request, int $client, int $rule): RedirectResponse
    {
        Gate::authorize('clients.manage');
        DB::transaction(function () use ($request, $client, $rule): void {
            $owner = DB::table('clients')->where('id', $client)->lockForUpdate()->first();
            abort_unless($owner, 404);

            $existing = DB::table('client_ip_allowlist')
                ->where('client_id', $client)->where('id', $rule)->first();
            abort_unless($existing, 404);

            $activeCount = DB::table('client_ip_allowlist')
                ->where('client_id', $client)->where('enabled', true)->count();
            if ($existing->enabled && $activeCount === 1 && $request->input('confirmation') !== 'UNRESTRICT') {
                throw ValidationException::withMessages([
                    'confirmation' => 'Removing the last allowed network permits requests from any IP. Type UNRESTRICT to confirm.',
                ]);
            }

            DB::table('client_ip_allowlist')
                ->where('client_id', $client)->where('id', $rule)->delete();
            SecurityAudit::record('client.ip_allowlist.deleted', 'high', (int) $request->user()->getKey(), [
                'client_id' => $client, 'ip_rule_id' => $rule,
            ], $client, $request->ip());
        });

        return redirect()->route('clients.ips.index', $client)
            ->with('status', 'Allowed network removed.');
    }
}
