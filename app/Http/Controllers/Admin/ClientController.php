<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\SecurityAudit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        Gate::authorize('clients.manage');

        $clients = DB::table('clients')->orderByDesc('id')->paginate(20);

        return view('admin.clients', [
            'clients' => $clients,
            'keys' => DB::table('api_keys')
                ->whereIn('client_id', $clients->getCollection()->pluck('id'))
                ->orderByDesc('id')->get()->groupBy('client_id'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('clients.manage');
        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'slug' => ['required', 'alpha_dash:ascii', 'max:100', Rule::unique('clients', 'slug')],
        ]);

        DB::transaction(function () use ($data, $request): void {
            $id = DB::table('clients')->insertGetId([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'status' => 'active',
                'created_at' => now()->utc(),
                'updated_at' => now()->utc(),
            ]);
            SecurityAudit::record('client.created', 'high', (int) $request->user()->getKey(), [
                'client_id' => $id,
            ], $id, $request->ip());
        });

        return back()->with('status', 'Client created.');
    }
}
