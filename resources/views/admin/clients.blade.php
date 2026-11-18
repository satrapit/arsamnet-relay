@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="mb-7"><p class="text-sm font-semibold text-blue-600">Platform</p><h1 class="mt-1 text-3xl font-bold">Clients</h1><p class="mt-2 text-sm text-slate-600">Create isolated customer workspaces.</p></div>
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="mb-4 font-semibold">Create client</h2>
    @if($errors->any()) <ul class="mb-4 list-disc pl-5 text-sm text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul> @endif
    <form method="POST" action="{{ route('clients.store') }}" class="grid gap-3 sm:grid-cols-3" data-submit-once>
        @csrf
        <input name="name" required maxlength="191" placeholder="Client name" class="rounded-lg border border-slate-300 p-3">
        <input name="slug" required maxlength="100" pattern="[a-zA-Z0-9_-]+" placeholder="client-slug" class="rounded-lg border border-slate-300 p-3">
        <button type="submit" class="rounded-lg bg-blue-600 p-3 font-semibold text-white">Create</button>
    </form>
</section>
<div class="grid gap-4">
    @foreach($clients as $client)
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div><h3 class="font-semibold">{{ $client->name }}</h3><p class="text-sm text-slate-500">{{ $client->slug }} · #{{ $client->id }}</p></div>
            <div class="flex items-center gap-3">
                <a href="{{ route('clients.providers.kavenegar.index', $client->id) }}" class="text-sm font-semibold text-blue-700 hover:underline">SMS provider</a>
                <a href="{{ route('clients.delivery-reviews.index', $client->id) }}" class="text-sm font-semibold text-blue-700 hover:underline">Delivery reviews</a>
                <a href="{{ route('clients.ips.index', $client->id) }}" class="text-sm font-semibold text-blue-700 hover:underline">IP allowlist</a>
                <span class="text-xs text-slate-500">{{ $client->status }}</span>
            </div>
        </div>
        <form method="POST" action="{{ route('clients.keys.store', $client->id) }}" class="flex flex-wrap gap-2" data-submit-once>
            @csrf
            <input name="label" required maxlength="100" aria-label="Key label" placeholder="Key label" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2 text-sm">
            <label class="flex items-center gap-2 text-xs"><input name="scopes[]" type="checkbox" value="messages.publish" checked>Publish</label>
            <label class="flex items-center gap-2 text-xs"><input name="scopes[]" type="checkbox" value="messages.read">Read</label>
            <label class="flex items-center gap-2 text-xs">Expires
                <select name="ttl_days" aria-label="Key expiration" class="rounded-lg border border-slate-300 bg-white p-2">
                    <option value="7">7 days</option><option value="30">30 days</option>
                    <option value="90" selected>90 days</option><option value="365">365 days</option>
                </select>
            </label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Issue API key</button>
        </form>
        @foreach($keys->get($client->id, collect()) as $key)
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 rounded-lg bg-slate-50 p-3 text-xs">
            <span class="break-all">{{ $key->label }} · {{ $key->key_prefix }}… ·
                {{ $key->revoked_at ? 'Revoked' : (($key->expires_at && \Illuminate\Support\Carbon::parse($key->expires_at)->isPast()) ? 'Expired' : 'Active') }}
                · Expires: {{ $key->expires_at ?? 'Never' }} UTC</span>
            @unless($key->revoked_at)
                <div class="flex flex-wrap items-center gap-3">
                    <form method="POST" action="{{ route('clients.keys.rotate', [$client->id, $key->id]) }}"
                          data-confirm="Rotate this key? The previous token will become invalid immediately." data-submit-once>
                        @csrf
                        <button type="submit" class="font-semibold text-blue-700">Rotate (90 days)</button>
                    </form>
                    <form method="POST" action="{{ route('clients.keys.revoke', [$client->id, $key->id]) }}"
                          data-confirm="Permanently revoke this API key?" data-submit-once>
                        @csrf
                        <button type="submit" class="font-semibold text-red-700">Revoke key</button>
                    </form>
                </div>
            @endunless
        </div>
        @endforeach
    </section>
    @endforeach
    {{ $clients->links() }}
</div>
@endsection
