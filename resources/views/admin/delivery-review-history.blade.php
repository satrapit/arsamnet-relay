@extends('layouts.app')
@section('title', 'Delivery audit history')
@section('content')
<div class="mb-6">
    <a href="{{ route('clients.delivery-reviews.index', $owner->id) }}" class="text-sm text-blue-700 hover:underline">Delivery reviews</a>
    <h1 class="mt-2 text-2xl font-bold">Delivery history</h1>
    <p class="mt-2 break-all font-mono text-xs text-slate-600">{{ $record->id }}</p>
    <p class="mt-2 text-sm text-slate-600">Client: {{ $owner->name }} · State: {{ $record->state }} · Attempts: {{ $record->attempt_count }}</p>
    <p class="mt-1 text-sm text-slate-500">Signed operator events and provider-status observations only. This history does not prove delivery or authorize a resend.</p>
</div>
<section class="rounded-2xl border border-slate-200 bg-white p-5">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[670px] text-left text-sm">
            <thead><tr class="border-b text-slate-500"><th class="py-2">Time (UTC)</th><th>Event</th><th>Action / observation</th><th>Provider code</th><th>Actor ID</th><th>Integrity</th></tr></thead>
            <tbody>
                @forelse($events as $event)
                    <tr class="border-b border-slate-100">
                        <td class="py-3">{{ $event->created_at }}</td>
                        <td>{{ $event->event_code }}</td>
                        <td>{{ $event->review_action ?? '—' }}</td>
                        <td>{{ $event->provider_status_code ?? '—' }}</td>
                        <td>{{ $event->actor_user_id ?? '—' }}</td>
                        <td>
                            @if($event->verified)
                                <span class="font-medium text-green-700">Verified</span>
                            @else
                                <span role="alert" class="font-semibold text-red-700">Integrity failed</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-slate-500">No audit events for this delivery.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $events->links() }}</div>
</section>
@endsection
