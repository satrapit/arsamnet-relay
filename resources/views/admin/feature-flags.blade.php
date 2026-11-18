@extends('layouts.app')
@section('title', 'Feature flags')
@section('content')
<div class="mb-7"><p class="text-sm font-semibold text-blue-600">System</p><h1 class="mt-1 text-3xl font-bold text-slate-900">Feature flags</h1>
<p class="mt-3 text-sm text-slate-600">Operational settings are stored in Pennant/database and are adjustable only by Super Admin. All sending and publishing gates default OFF. Enabling provider sending may generate billable traffic.</p></div>
<div class="grid gap-4">
    @foreach($flags as $flag)
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><h2 class="font-semibold text-slate-900">{{ $flag['key'] }}</h2><p class="mt-1 text-sm text-slate-600">{{ $flag['description'] }}</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $flag['enabled'] ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $flag['enabled'] ? 'Enabled' : 'Disabled' }}</span>
        </div>
        @can('feature-flags.write')
        <form method="POST" action="{{ route('feature-flags.update', $flag['key']) }}" class="mt-4" data-submit-once
            @if($flag['key'] === 'kavenegar-sending' && !$flag['enabled'])
            data-confirm="Enable live, potentially billable Kavenegar SMS? This alone does not enable publishing."
            @endif>
            @csrf
            <input type="hidden" name="enabled" value="{{ $flag['enabled'] ? '0' : '1' }}">
            <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
                {{ $flag['enabled'] ? 'Disable feature' : 'Enable feature' }}
            </button>
        </form>
        @endcan
    </section>
    @endforeach
</div>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
    <h2 class="font-semibold text-slate-900">Kavenegar hourly SMS submission limit</h2>
    <p class="mt-1 text-sm text-slate-600">Maximum new SMS delivery records per client in a rolling hour (1–10,000). This does not enable sending or change the independent safety flags.</p>
    <p class="mt-2 text-sm">Current limit: <strong>{{ $hourlyLimit }}</strong></p>
    @can('feature-flags.write')
    <form method="POST" action="{{ route('settings.kavenegar-hourly-limit.update') }}" class="mt-4 flex flex-wrap items-center gap-3" data-submit-once>
        @csrf
        <label class="text-sm" for="hourly_limit">Deliveries per hour</label>
        <input type="number" class="w-32 rounded-lg border border-slate-300 p-2" name="hourly_limit" id="hourly_limit" min="1" max="10000" value="{{ old('hourly_limit', $hourlyLimit) }}" required>
        <button type="submit" class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white" data-confirm="Update the global Kavenegar hourly delivery limit?">Save limit</button>
    </form>
    @endcan
</section>
@endsection
