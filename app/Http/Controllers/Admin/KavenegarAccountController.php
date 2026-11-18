<?php

namespace App\Http\Controllers\Admin;

use Laravel\Pennant\Feature;
use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Privileged provider configuration; credentials never go back to browsers.
 */
final class KavenegarAccountController extends Controller
{
    public function index(Request $request, int $client): View
    {
        $this->authorizeConfiguration($request, $client);

        $owner = DB::table('clients')->where('id', $client)->first();
        abort_unless($owner, 404);

        $account = DB::table('provider_accounts')
            ->where('client_id', $client)->where('adapter_code', 'kavenegar')
            ->where('channel', 'sms')->orderBy('id')->first();
        $route = $account ? DB::table('client_provider_routes')
            ->where('client_id', $client)->where('provider_account_id', $account->id)->first() : null;

        // Configuration only; exclude secrets and encrypted ciphertext.
        $attempts = $account ? DB::table('delivery_attempts as attempts')
            ->join('deliveries as deliveries', 'deliveries.id', '=', 'attempts.delivery_id')
            ->where('attempts.provider_account_id', $account->id)
            ->where('deliveries.client_id', $client)
            ->orderByDesc('attempts.id')
            ->limit(30)->get([
                'attempts.id', 'attempts.outcome', 'attempts.provider_ref',
                'attempts.http_status', 'attempts.error_code',
                'attempts.created_at', 'deliveries.id as delivery_id',
            ]) : collect();

        $safe = $account ? (object) [
            'id' => (int) $account->id,
            'display_name' => (string) $account->display_name,
            'enabled' => (bool) $account->enabled && (bool) ($route?->enabled ?? false),
            'priority' => (int) ($route?->priority ?? 100),
        ] : null;

        return view('admin.kavenegar', [
            'client' => $owner,
            'account' => $safe,
            'attempts' => $attempts,
            'configurationRoute' => $request->routeIs('account.clients.providers.kavenegar.*')
                ? 'account.clients.providers.kavenegar.save' : 'clients.providers.kavenegar.save',
            'isClientSelfService' => $request->routeIs('account.clients.providers.kavenegar.*'),
            'publishEnabled' => (bool) Feature::globally()->active('message-publishing'),
            'sendEnabled' => (bool) Feature::globally()->active('kavenegar-sending'),
        ]);
    }

    public function save(Request $request, int $client): RedirectResponse
    {
        $this->authorizeConfiguration($request, $client);
        $validated = $request->validate([
            'display_name' => ['required', 'string', 'min:2', 'max:100'],
            'api_key' => ['nullable', 'string', 'regex:/\A[A-Za-z0-9_-]{16,128}\z/D'],
            'sender' => ['required', 'string', 'regex:/\A[A-Za-z0-9]{2,32}\z/D'],
            'priority' => ['required', 'integer', 'between:1,65535'],
            'enabled' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($request, $client, $validated): void {
            $owner = DB::table('clients')->where('id', $client)->lockForUpdate()->first();
            abort_unless($owner && $owner->status === 'active', 404);

            $account = DB::table('provider_accounts')
                ->where('client_id', $client)->where('adapter_code', 'kavenegar')
                ->where('channel', 'sms')->lockForUpdate()->first();

            if (! $account && empty($validated['api_key'])) {
                throw ValidationException::withMessages([
                    'api_key' => 'An API key is required for the first setup.',
                ]);
            }

            $priority = (int) $validated['priority'];
            $occupied = DB::table('client_provider_routes')
                ->where('client_id', $client)->where('channel', 'sms')
                ->where('priority', $priority)
                ->when($account, fn ($query) => $query->where('provider_account_id', '!=', $account->id))
                ->exists();

            if ($occupied) {
                throw ValidationException::withMessages([
                    'priority' => 'The priority is already used by another SMS provider.',
                ]);
            }

            $credentials = [];
            // Supplying a replacement key must recover even when existing
            // encrypted settings are corrupt or were encrypted by an old key.
            if ($account && empty($validated['api_key'])) {
                try {
                    $credentials = json_decode(
                        Crypt::decryptString((string) $account->credentials_ciphertext),
                        true, 512, JSON_THROW_ON_ERROR,
                    );
                } catch (\Throwable) {
                    throw ValidationException::withMessages([
                        'api_key' => 'Existing credentials could not be read. Supply a new API key.',
                    ]);
                }
            }

            $apiKey = $validated['api_key'] ?? ($credentials['api_key'] ?? null);
            if (! is_string($apiKey) || ! preg_match('/\A[A-Za-z0-9_-]{16,128}\z/D', $apiKey)) {
                throw ValidationException::withMessages([
                    'api_key' => 'A valid Kavenegar API key is required.',
                ]);
            }

            $now = now()->utc();
            $data = [
                'display_name' => $validated['display_name'],
                'credentials_ciphertext' => Crypt::encryptString(json_encode([
                    'api_key' => $apiKey, 'sender' => $validated['sender'],
                ], JSON_THROW_ON_ERROR)),
                'enabled' => (bool) $validated['enabled'],
                'updated_at' => $now,
            ];

            if ($account) {
                DB::table('provider_accounts')->where('id', $account->id)
                    ->where('client_id', $client)->update($data);
                $accountId = (int) $account->id;
            } else {
                $accountId = DB::table('provider_accounts')->insertGetId([
                    ...$data,
                    'client_id' => $client,
                    'adapter_code' => 'kavenegar',
                    'channel' => 'sms',
                    'created_at' => $now,
                ]);
            }

            DB::table('client_provider_routes')->updateOrInsert([
                'client_id' => $client, 'provider_account_id' => $accountId, 'channel' => 'sms',
            ], [
                'priority' => $priority,
                'enabled' => (bool) $validated['enabled'],
                'created_at' => $now,
            ]);

            SecurityAudit::record(
                'provider.kavenegar.configuration_changed',
                'high',
                (int) $request->user()->getKey(),
                ['client_id' => $client],
                $client,
                $request->ip(),
            );
        }, 3);

        $redirectRoute = $request->routeIs('account.clients.providers.kavenegar.*')
            ? 'account.clients.providers.kavenegar.index' : 'clients.providers.kavenegar.index';

        return redirect()->route($redirectRoute, $client)
            ->with('status', 'Kavenegar settings saved. API key and sender are not shown again.');
    }

    private function authorizeConfiguration(Request $request, int $client): void
    {
        if ($request->routeIs('account.clients.providers.kavenegar.*')) {
            Gate::authorize('kavenegar.client-configure', $client);

            return;
        }

        Gate::authorize('clients.manage');
    }
}
