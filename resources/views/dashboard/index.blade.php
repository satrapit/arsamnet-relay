@extends('layouts.app')
@section('title', 'Overview')
@section('content')
<div class="mb-8 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <p class="text-xs font-semibold uppercase tracking-[.18em] text-blue-700">Operations / Overview</p>
        <h1 class="mt-2 text-3xl font-bold tracking-tight text-slate-950 sm:text-4xl">Platform overview</h1>
        <p class="mt-2 max-w-xl text-sm leading-6 text-slate-500">Message delivery, provider readiness and activity in one place. All metrics reflect stored records, not live provider polling.</p>
    </div>
    @can('clients.manage')
        <a href="{{ route('clients.index') }}" class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-blue-700 px-5 py-3 text-sm font-semibold text-white shadow-sm shadow-blue-200 transition hover:bg-blue-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-600">
            <i class="fa-solid fa-plus" aria-hidden="true"></i> Manage clients
        </a>
    @endcan
</div>

<div class="mb-7 flex flex-wrap items-center gap-2 rounded-2xl border border-slate-200 bg-white p-3.5 text-sm shadow-sm">
    <span class="mr-1 font-medium text-slate-700">Operations</span>
    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 {{ $flags['publishing'] ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
        <span class="size-1.5 rounded-full {{ $flags['publishing'] ? 'bg-emerald-500' : 'bg-slate-400' }}"></span> Publishing {{ $flags['publishing'] ? 'on' : 'off' }}
    </span>
    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 {{ $flags['sending'] ? 'bg-amber-50 text-amber-800' : 'bg-slate-100 text-slate-600' }}">
        <span class="size-1.5 rounded-full {{ $flags['sending'] ? 'bg-amber-500' : 'bg-slate-400' }}"></span> SMS sending {{ $flags['sending'] ? 'on' : 'off' }}
    </span>
    <span class="inline-flex items-center gap-2 rounded-full px-3 py-1.5 {{ $flags['status'] ? 'bg-emerald-50 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">
        <span class="size-1.5 rounded-full {{ $flags['status'] ? 'bg-emerald-500' : 'bg-slate-400' }}"></span> Status queries {{ $flags['status'] ? 'on' : 'off' }}
    </span>
    @can('feature-flags.view')
        <a href="{{ route('feature-flags.index') }}" class="ml-auto rounded-lg px-3 py-2 font-semibold text-blue-700 hover:bg-blue-50">Manage settings <i class="fa-solid fa-arrow-right ml-1" aria-hidden="true"></i></a>
    @endcan
</div>

<div class="mb-7 grid grid-cols-2 gap-3 sm:gap-4 xl:grid-cols-3">
    @foreach([
        ['Active clients', $counts['clients'], 'fa-users', 'text-indigo-700', 'bg-indigo-50'],
        ['Messages', $counts['messages'], 'fa-envelope-open-text', 'text-blue-700', 'bg-blue-50'],
        ['Today’s deliveries', $counts['today'], 'fa-paper-plane', 'text-sky-700', 'bg-sky-50'],
        ['Accepted', $counts['accepted'], 'fa-clock', 'text-amber-700', 'bg-amber-50'],
        ['Delivered', $counts['delivered'], 'fa-circle-check', 'text-emerald-700', 'bg-emerald-50'],
        ['Needs review', $counts['review'], 'fa-triangle-exclamation', 'text-rose-700', 'bg-rose-50'],
    ] as [$label, $count, $icon, $color, $background])
        <section class="min-w-0 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition hover:shadow-md sm:p-5">
            <div class="flex items-start justify-between gap-3">
                <span class="text-xs font-semibold text-slate-500 sm:text-sm">{{ $label }}</span>
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $background }} {{ $color }}"><i class="fa-solid {{ $icon }}" aria-hidden="true"></i></span>
            </div>
            <p class="mt-4 text-3xl font-bold tracking-tight text-slate-950 tabular-nums">{{ number_format($count) }}</p>
            @if($label === 'Accepted')
                <p class="mt-1 text-xs text-slate-500">Not a delivery confirmation</p>
            @elseif($label === 'Needs review')
                <p class="mt-1 text-xs text-slate-500">Manual investigation required</p>
            @else
                <p class="mt-1 text-xs text-slate-400">Current records</p>
            @endif
        </section>
    @endforeach
</div>

<div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1.7fr)_minmax(280px,1fr)]">
    <section class="min-w-0 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
        <div class="flex items-center justify-between gap-3 border-b border-slate-100 px-5 py-5 sm:px-6">
            <div><h2 class="text-lg font-semibold text-slate-950">Recent delivery activity</h2><p class="mt-1 text-xs text-slate-500">Latest accepted, delivered and review-required records</p></div>
            <i class="fa-solid fa-list-check text-slate-400" aria-hidden="true"></i>
        </div>
        <div class="divide-y divide-slate-100">
            @forelse($recent as $item)
                <a href="{{ route('clients.delivery-reviews.history', [$item->client_id, $item->id]) }}" class="flex min-w-0 items-center gap-3 px-5 py-4 transition hover:bg-slate-50 focus-visible:outline-2 focus-visible:outline-blue-600 sm:px-6">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl {{ $item->state === 'delivered' ? 'bg-emerald-50 text-emerald-700' : ($item->state === 'accepted' ? 'bg-amber-50 text-amber-700' : 'bg-rose-50 text-rose-700') }}">
                        <i class="fa-solid {{ $item->state === 'delivered' ? 'fa-check' : ($item->state === 'accepted' ? 'fa-clock' : 'fa-exclamation') }}" aria-hidden="true"></i>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span class="truncate text-sm font-semibold text-slate-900">{{ $item->client_name }}</span>
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{{ str_replace('_', ' ', $item->state) }}</span>
                        </div>
                        <p class="mt-1 truncate font-mono text-xs text-slate-500">{{ $item->id }}</p>
                    </div>
                    <i class="fa-solid fa-chevron-right text-xs text-slate-400" aria-hidden="true"></i>
                </a>
            @empty
                <div class="px-6 py-14 text-center">
                    <span class="mx-auto flex size-12 items-center justify-center rounded-2xl bg-slate-100 text-slate-500"><i class="fa-solid fa-inbox" aria-hidden="true"></i></span>
                    <p class="mt-4 text-sm font-semibold text-slate-700">No delivery activity yet</p>
                    <p class="mt-1 text-xs text-slate-500">Recent delivery records will appear here.</p>
                </div>
            @endforelse
        </div>
    </section>
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
        <h2 class="text-lg font-semibold text-slate-950">Quick access</h2>
        <p class="mt-1 text-xs text-slate-500">Frequent administrative tasks</p>
        <div class="mt-5 grid gap-2">
            @can('clients.manage')
                <a href="{{ route('clients.index') }}" class="flex min-h-14 items-center gap-3 rounded-xl border border-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                    <i class="fa-solid fa-users w-5 text-blue-700" aria-hidden="true"></i><span class="flex-1">Clients & providers</span><i class="fa-solid fa-arrow-right text-slate-400" aria-hidden="true"></i>
                </a>
            @endcan
            @can('feature-flags.view')
                <a href="{{ route('feature-flags.index') }}" class="flex min-h-14 items-center gap-3 rounded-xl border border-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                    <i class="fa-solid fa-sliders w-5 text-blue-700" aria-hidden="true"></i><span class="flex-1">Operational settings</span><i class="fa-solid fa-arrow-right text-slate-400" aria-hidden="true"></i>
                </a>
            @endcan
            @can('security.monitor')
                <a href="{{ route('security-monitor.index') }}" class="flex min-h-14 items-center gap-3 rounded-xl border border-slate-100 px-4 py-3 text-sm font-semibold text-slate-700 transition hover:border-blue-200 hover:bg-blue-50">
                    <i class="fa-solid fa-shield-halved w-5 text-blue-700" aria-hidden="true"></i><span class="flex-1">Security monitor</span><i class="fa-solid fa-arrow-right text-slate-400" aria-hidden="true"></i>
                </a>
            @endcan
        </div>
        <p class="mt-5 rounded-xl bg-slate-50 p-4 text-xs leading-5 text-slate-600">A provider-accepted message is not necessarily delivered. Live SMS must be deliberately enabled by Super Admin after readiness checks.</p>
    </section>
</div>
@endsection
