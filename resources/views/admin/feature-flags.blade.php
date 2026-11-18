@extends('layouts.app')
@section('title', 'Settings')
@section('content')
<div class="mb-8 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-blue-700">System / Configuration</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950">Settings</h1>
        <p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Manage messaging capabilities and provider operations. Changes are saved instantly without leaving this page.</p>
    </div>
    @cannot('feature-flags.write')
        <span class="w-fit rounded-full bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600"><svg class="inline-block size-5 shrink-0 align-middle me-2" aria-hidden="true"><use href="{{ asset('icons/lucide.svg') }}#eye"></use></svg>Read-only access</span>
    @endcannot
</div>

<div class="grid gap-6">
    @foreach($groups as $title => $group)
        <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="settings-group-{{ $loop->index }}">
            <div class="flex items-start gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6">
                <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700">
                    <svg class="inline-block size-5 shrink-0 align-middle {{ $group['icon'] }}" aria-hidden="true"><use href="{{ asset('icons/lucide.svg') }}#{{ $group['icon'] }}"></use></svg>
                </span>
                <div>
                    <h2 id="settings-group-{{ $loop->index }}" class="font-semibold text-slate-950">{{ $title }}</h2>
                    <p class="mt-1 text-sm text-slate-500">{{ $group['description'] }}</p>
                </div>
            </div>
            <div class="divide-y divide-slate-100">
                @foreach($group['flags'] as $flag)
                    <div class="flex flex-wrap items-center justify-between gap-4 px-5 py-5 sm:px-6">
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-semibold text-slate-900">{{ \Illuminate\Support\Str::headline($flag['key']) }}</h3>
                            <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">{{ $flag['description'] }}</p>
                            @if($flag['key'] === 'kavenegar-sending')
                                <p class="mt-1 text-xs font-medium text-amber-700">Enabling live sending may incur SMS charges.</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('settings.flags.update', $flag['key']) }}"
                              data-settings-toggle data-setting-label="{{ \Illuminate\Support\Str::headline($flag['key']) }}"
                              @if($flag['key'] === 'kavenegar-sending') data-confirm-live @endif>
                            @csrf
                            <input type="hidden" name="enabled" value="0">
                            <label class="flex cursor-pointer items-center gap-3">
                                <span class="text-xs font-semibold text-slate-500" data-switch-state>{{ $flag['enabled'] ? 'On' : 'Off' }}</span>
                                <input type="checkbox" name="enabled" value="1" role="switch"
                                       class="settings-switch" aria-label="{{ \Illuminate\Support\Str::headline($flag['key']) }}"
                                       aria-checked="{{ $flag['enabled'] ? 'true' : 'false' }}"
                                       {{ $flag['enabled'] ? 'checked' : '' }}
                                       @cannot('feature-flags.write') disabled @endcannot>
                            </label>
                            <noscript><button type="submit" class="mt-2 text-xs font-semibold text-blue-700">Save setting</button></noscript>
                        </form>
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <section class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm" aria-labelledby="settings-limits-title">
        <div class="flex items-start gap-3 border-b border-slate-100 bg-slate-50/70 px-5 py-5 sm:px-6">
            <span class="flex size-11 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-700"><svg class="inline-block size-5 shrink-0 align-middle " aria-hidden="true"><use href="{{ asset('icons/lucide.svg') }}#sliders-horizontal"></use></svg></span>
            <div>
                <h2 id="settings-limits-title" class="font-semibold text-slate-950">Limits & quotas</h2>
                <p class="mt-1 text-sm text-slate-500">Protect client usage and control hourly SMS capacity.</p>
            </div>
        </div>
        <form method="POST" action="{{ route('settings.kavenegar-hourly-limit.update') }}"
              data-settings-limit class="flex flex-col gap-4 px-5 py-5 sm:flex-row sm:items-end sm:justify-between sm:px-6">
            @csrf
            <div class="max-w-2xl">
                <label class="text-sm font-semibold text-slate-900" for="hourly_limit">Kavenegar hourly SMS limit</label>
                <p class="mt-1 text-sm leading-6 text-slate-500">Maximum new SMS deliveries per client during a rolling hour. This limit does not enable network sending.</p>
            </div>
            <div class="flex items-center gap-3">
                <input id="hourly_limit" type="number" name="hourly_limit" min="1" max="10000"
                       value="{{ old('hourly_limit', $hourlyLimit) }}" required
                       class="h-11 w-28 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-900 focus:border-blue-500 focus:outline-none focus:ring-4 focus:ring-blue-100"
                       @cannot('feature-flags.write') disabled @endcannot>
                @can('feature-flags.write')
                    <button type="submit" class="inline-flex h-11 items-center justify-center rounded-xl bg-blue-700 px-5 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-wait disabled:opacity-60">Save</button>
                @endcan
            </div>
        </form>
    </section>
</div>

<div data-settings-toasts class="pointer-events-none fixed bottom-5 end-4 z-[100] flex w-[calc(100vw-2rem)] max-w-sm flex-col gap-3 sm:end-6" role="region" aria-label="Settings notifications" aria-live="polite" aria-relevant="additions"></div>

<dialog data-settings-confirm class="m-auto w-[calc(100vw-2rem)] max-w-md rounded-2xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
    <div class="p-6">
        <span class="flex size-12 items-center justify-center rounded-xl bg-amber-100 text-amber-700"><svg class="inline-block size-5 shrink-0 align-middle " aria-hidden="true"><use href="{{ asset('icons/lucide.svg') }}#triangle-alert"></use></svg></span>
        <h2 class="mt-4 text-lg font-semibold text-slate-950">Enable live SMS sending?</h2>
        <p class="mt-2 text-sm leading-6 text-slate-600">This setting allows billable provider requests when publishing and an eligible client route are also enabled.</p>
        <div class="mt-6 flex justify-end gap-3">
            <button type="button" data-settings-cancel class="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
            <button type="button" data-settings-confirm-accept class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white hover:bg-amber-700">Enable sending</button>
        </div>
    </div>
</dialog>
@endsection
