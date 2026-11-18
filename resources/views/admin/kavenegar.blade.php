@extends('layouts.app')
@section('title', 'Kavenegar provider')
@section('content')
<div class="mb-6">
    @unless($isClientSelfService)
    <a href="{{ route('clients.index') }}" class="text-sm text-blue-700 hover:underline">Clients</a>
    @endunless
    <h1 class="mt-2 text-3xl font-bold">Kavenegar SMS</h1>
    <p class="mt-2 text-sm text-slate-600">Client: {{ $client->name }} ({{ $client->slug }}). Credentials are encrypted and never displayed here.</p>
</div>
<div class="mb-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    This account may be configured without enabling real sends.
    Publishing: <strong>{{ $publishEnabled ? 'ON' : 'OFF' }}</strong> ·
    Kavenegar network gate: <strong>{{ $sendEnabled ? 'ON' : 'OFF' }}</strong>.
    Account activation alone cannot override either global gate.
</div>
@if($errors->any())
    <ul role="alert" class="mb-5 list-disc rounded-xl bg-red-50 px-8 py-4 text-sm text-red-700">
        @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
    </ul>
@endif
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <p class="text-xs font-semibold uppercase tracking-widest text-blue-700">Provider readiness</p>
            <h2 class="mt-1 text-lg font-semibold text-slate-950">First live SMS checklist</h2>
            <p class="mt-1 text-sm text-slate-500">Use one owned test number and a short non-sensitive message. Never start with bulk or OTP.</p>
        </div>
        <span class="rounded-full px-3 py-1.5 text-xs font-semibold {{ $account?->enabled && $publishEnabled && $sendEnabled ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
            {{ $account?->enabled && $publishEnabled && $sendEnabled ? 'Live route enabled' : 'Live send not ready' }}
        </span>
    </div>
    <div class="mt-5 grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <span class="text-xs text-slate-500">1 · Provider account & route</span>
            <p class="mt-2 text-sm font-semibold {{ $account?->enabled ? 'text-emerald-700' : 'text-slate-700' }}">{{ $account?->enabled ? 'Enabled' : 'Not enabled' }}</p>
        </div>
        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <span class="text-xs text-slate-500">2 · Message publishing</span>
            <p class="mt-2 text-sm font-semibold {{ $publishEnabled ? 'text-emerald-700' : 'text-slate-700' }}">{{ $publishEnabled ? 'Enabled' : 'Disabled' }}</p>
        </div>
        <div class="rounded-xl border border-slate-100 bg-slate-50 p-4">
            <span class="text-xs text-slate-500">3 · Kavenegar sending</span>
            <p class="mt-2 text-sm font-semibold {{ $sendEnabled ? 'text-amber-700' : 'text-slate-700' }}">{{ $sendEnabled ? 'Enabled' : 'Disabled' }}</p>
        </div>
    </div>
    <div class="mt-5 flex flex-wrap items-center gap-3">
        @can('feature-flags.write')
            <a class="inline-flex min-h-11 items-center rounded-xl bg-blue-700 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-800" href="{{ route('settings.index') }}">Settings</a>
        @endcan
        @unless($isClientSelfService)
            <a class="inline-flex min-h-11 items-center rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50" href="{{ route('clients.index') }}">Issue scoped test API key</a>
        @endunless
    </div>
    <p class="mt-4 text-xs leading-5 text-slate-500">A test uses the normal authenticated `/api/v1/sms/send` machine endpoint, idempotency key and durable attempt log. The web console never exposes the provider key or sends a message automatically. Switch off live sending after the trial.</p>
</section>
<section class="mb-7 rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="mb-5 text-lg font-semibold">{{ $account ? 'Update provider' : 'Configure provider' }}</h2>
    <form method="POST" action="{{ route($configurationRoute, $client->id) }}" class="grid gap-4 sm:grid-cols-2" data-submit-once>
        @csrf
        <label class="grid gap-1 text-sm">Display name
            <input class="rounded-lg border border-slate-300 p-3" name="display_name" value="{{ old('display_name', $account?->display_name ?? 'Kavenegar') }}" maxlength="100" required autocomplete="off">
        </label>
        <label class="grid gap-1 text-sm">API key {{ $account ? '(leave empty to keep current key)' : '' }}
            <input class="rounded-lg border border-slate-300 p-3" name="api_key" type="password" maxlength="128" autocomplete="new-password" {{ $account ? '' : 'required' }} placeholder="{{ $account ? 'Unchanged' : 'Paste API key' }}">
        </label>
        <label class="grid gap-1 text-sm">Sender number / sender ID
            <input class="rounded-lg border border-slate-300 p-3" name="sender" value="{{ old('sender') }}" maxlength="32" required autocomplete="off" placeholder="Your Kavenegar sender">
            <span class="text-xs text-slate-500">Re-enter sender on updates. It is not displayed after saving.</span>
        </label>
        <label class="grid gap-1 text-sm">Route priority (lower = preferred)
            <input class="rounded-lg border border-slate-300 p-3" type="number" name="priority" min="1" max="65535" value="{{ old('priority', $account?->priority ?? 100) }}" required>
        </label>
        <label class="flex items-center gap-3 text-sm sm:col-span-2">
            <input type="hidden" name="enabled" value="0">
            <input type="checkbox" name="enabled" value="1" {{ old('enabled', $account?->enabled ?? false) ? 'checked' : '' }}>
            Enable this Kavenegar account and route
        </label>
        <div class="sm:col-span-2">
            <button type="submit" class="rounded-lg bg-blue-600 px-5 py-3 font-semibold text-white" data-confirm="Save provider configuration and activation state?">Save Kavenegar settings</button>
        </div>
    </form>
</section>
<section class="rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="text-lg font-semibold">Recent provider attempts</h2>
    <p class="mb-4 text-sm text-slate-500">Only normalized status, HTTP code, provider reference and delivery ID are shown. No phone numbers, API keys, OTPs or message bodies.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[650px] text-left text-sm">
            <thead><tr class="border-b text-slate-500"><th class="py-2">Time (UTC)</th><th>Delivery</th><th>Outcome</th><th>HTTP</th><th>Provider ref</th><th>Error code</th></tr></thead>
            <tbody>
                @forelse($attempts as $attempt)
                    <tr class="border-b border-slate-100"><td class="py-2">{{ $attempt->created_at }}</td><td class="font-mono text-xs">{{ $attempt->delivery_id }}</td><td>{{ $attempt->outcome }}</td><td>{{ $attempt->http_status ?? '—' }}</td><td>{{ $attempt->provider_ref ?? '—' }}</td><td>{{ $attempt->error_code ?? '—' }}</td></tr>
                @empty
                    <tr><td colspan="6" class="py-5 text-slate-500">No SMS attempts yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
