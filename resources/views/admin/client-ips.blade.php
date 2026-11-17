@extends('layouts.app')
@section('title', 'Allowed networks')
@section('content')
<div class="mb-7">
    <a class="text-sm font-medium text-blue-700 hover:underline" href="{{ route('clients.index') }}">Clients</a>
    <h1 class="mt-2 text-2xl font-bold text-slate-900">Allowed networks — {{ $owner->name }}</h1>
    <p class="mt-2 text-sm text-slate-600">Machine API keys for this client are accepted only from enabled networks listed below.</p>
</div>
@if($rules->where('enabled', true)->isEmpty())
    <p role="alert" class="mb-6 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
        No active IP restrictions. Valid machine keys may be used from any source IP.
    </p>
@endif
@if($errors->any())
    <ul role="alert" class="mb-6 list-inside list-disc rounded-xl bg-red-50 p-4 text-sm text-red-700">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
@endif
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="mb-4 text-lg font-semibold">Add an IPv4 / IPv6 network</h2>
    <form method="POST" action="{{ route('clients.ips.store', $owner->id) }}" class="flex flex-wrap gap-3" data-submit-once>
        @csrf
        <label class="min-w-0 flex-1 text-sm font-medium" for="allowed-cidr">CIDR notation
            <input id="allowed-cidr" name="cidr" required maxlength="49" placeholder="192.0.2.0/24 or 2001:db8::/32"
                   value="{{ old('cidr') }}" class="mt-2 w-full rounded-lg border border-slate-300 p-3 font-mono text-sm">
        </label>
        <button type="submit" class="self-end rounded-lg bg-blue-600 px-5 py-3 text-sm font-semibold text-white">Add network</button>
    </form>
    <p class="mt-3 text-xs text-slate-500">Networks are normalized automatically; /0 is not allowed. Maximum 64 networks per client.</p>
</section>
<div class="grid gap-3">
    @forelse($rules as $rule)
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <code class="break-all text-sm font-semibold">{{ $rule->cidr }}</code>
                    <p class="mt-1 text-xs text-slate-500">{{ $rule->enabled ? 'Enabled' : 'Disabled' }}</p>
                </div>
                <form method="POST" action="{{ route('clients.ips.destroy', [$owner->id, $rule->id]) }}"
                      data-submit-once data-confirm="Remove this allowed network?">
                    @csrf
                    @method('DELETE')
                    @if($rule->enabled && $rules->where('enabled', true)->count() === 1)
                        <label class="mb-2 block text-xs font-semibold text-amber-800">
                            This is the last active network. Type UNRESTRICT to allow any IP:
                            <input name="confirmation" type="text" required pattern="UNRESTRICT" autocomplete="off"
                                   class="mt-1 block rounded-lg border border-amber-300 p-2 font-mono text-xs">
                        </label>
                    @endif
                    <button type="submit" class="rounded-lg border border-red-200 px-3 py-2 text-sm font-semibold text-red-700">Remove</button>
                </form>
            </div>
        </section>
    @empty
        <p class="rounded-xl border border-slate-200 bg-white p-6 text-sm text-slate-500">No saved networks.</p>
    @endforelse
</div>
@endsection
