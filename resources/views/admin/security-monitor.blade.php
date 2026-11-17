@extends('layouts.app')
@section('title', 'Security monitor')
@section('content')
<div class="mb-8">
    <p class="text-sm font-semibold text-blue-600">System</p>
    <h1 class="mt-1 text-3xl font-bold text-slate-900">Security monitor</h1>
    <p class="mt-2 text-sm text-slate-600">Aggregated authentication events. No credentials or raw IP addresses are shown.</p>
</div>
<x-common.component-card title="Scheduled operations" desc="Recent scheduler heartbeat (checked against 3-minute freshness threshold)">
    @if($schedulerStale)
        <p role="status" class="rounded-lg bg-amber-50 p-3 text-sm font-semibold text-amber-800">Scheduler heartbeat is missing or stale. Check the cPanel Cron and PHP CLI path.</p>
    @else
        <p role="status" class="rounded-lg bg-emerald-50 p-3 text-sm font-semibold text-emerald-800">Scheduler heartbeat is recent.</p>
    @endif
    <p class="text-xs text-slate-500">Last successful run: {{ $lastRun ?? 'Never observed' }} UTC</p>
</x-common.component-card>
<div class="mt-6">
    <x-common.component-card title="Authentication activity" :desc="'Event counts in the last '.$timeWindow">
        @forelse($events as $event)
            <div class="flex items-center justify-between gap-4 border-b border-slate-100 py-2 text-sm last:border-0">
                <code class="break-all text-slate-700">{{ $event->event_code }}</code>
                <span class="font-semibold text-slate-900">{{ number_format($event->total) }}</span>
            </div>
        @empty
            <p class="text-sm text-slate-500">No recorded security events in this window.</p>
        @endforelse
    </x-common.component-card>
</div>
@endsection
