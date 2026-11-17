<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ClientApiKeyController extends Controller
{
    public function store(Request $request, int $client): Response
    {
        Gate::authorize('clients.manage');
        $data = $request->validate([
            'label' => ['required', 'string', 'max:100'],
            'scopes' => ['required', 'array', 'min:1'],
            'scopes.*' => ['required', 'string', Rule::in(['messages.publish', 'messages.read'])],
            'ttl_days' => ['required', 'integer', Rule::in([7, 30, 90, 365])],
        ]);
        $owner = DB::table('clients')->where('id', $client)->where('status', 'active')->first();
        abort_unless($owner, 404);

        $token = 'relay_'.bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $client, $token, $data): void {
            $id = DB::table('api_keys')->insertGetId([
                'client_id' => $client,
                'created_by_user_id' => $request->user()->getKey(),
                'label' => $data['label'],
                'key_prefix' => substr($token, 0, 16),
                'key_hash' => hash('sha256', $token),
                'scopes' => json_encode(array_values(array_unique($data['scopes'])), JSON_THROW_ON_ERROR),
                'expires_at' => now()->utc()->addDays((int) $data['ttl_days']),
                'created_at' => now()->utc(),
            ]);
            SecurityAudit::record('api.key.issued', 'high', (int) $request->user()->getKey(), [
                'client_id' => $client, 'key_id' => $id,
            ], $client, $request->ip());
        });

        return $this->oneTimeResponse($owner, $token);
    }

    public function rotate(Request $request, int $client, int $key): Response
    {
        Gate::authorize('clients.manage');
        $owner = DB::table('clients')->where('id', $client)->where('status', 'active')->first();
        abort_unless($owner, 404);

        $token = 'relay_'.bin2hex(random_bytes(32));
        DB::transaction(function () use ($request, $client, $key, $token): void {
            $old = DB::table('api_keys')->where('id', $key)->where('client_id', $client)
                ->lockForUpdate()->first();
            abort_unless($old && !$old->revoked_at, 404);

            DB::table('api_keys')->where('id', $key)->where('client_id', $client)
                ->update(['revoked_at' => now()->utc()]);

            $newId = DB::table('api_keys')->insertGetId([
                'client_id' => $client,
                'created_by_user_id' => $request->user()->getKey(),
                'label' => $old->label,
                'key_prefix' => substr($token, 0, 16),
                'key_hash' => hash('sha256', $token),
                'scopes' => $old->scopes,
                'expires_at' => now()->utc()->addDays(90),
                'created_at' => now()->utc(),
            ]);
            SecurityAudit::record('api.key.rotated', 'high', (int) $request->user()->getKey(), [
                'client_id' => $client,
                'key_id' => $newId,
                'previous_key_id' => $key,
            ], $client, $request->ip());
        });

        return $this->oneTimeResponse($owner, $token);
    }

    public function revoke(Request $request, int $client, int $key): RedirectResponse
    {
        Gate::authorize('clients.manage');
        DB::transaction(function () use ($request, $client, $key): void {
            $updated = DB::table('api_keys')->where('id', $key)->where('client_id', $client)
                ->whereNull('revoked_at')->update(['revoked_at' => now()->utc()]);
            abort_unless($updated === 1, 404);
            SecurityAudit::record('api.key.revoked', 'high', (int) $request->user()->getKey(), [
                'client_id' => $client, 'key_id' => $key,
            ], $client, $request->ip());
        });

        return back()->with('status', 'API key revoked.');
    }

    private function oneTimeResponse(object $owner, string $token): Response
    {
        return response()->view('admin.api-key-created', ['owner' => $owner, 'token' => $token])
            ->header('Cache-Control', 'no-store, private')
            ->header('Pragma', 'no-cache');
    }
}
