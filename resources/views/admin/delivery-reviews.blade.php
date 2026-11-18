@extends('layouts.app')
@section('title', 'Delivery reviews')
@section('content')
<div class="mb-6">
    <a href="{{ route('clients.index') }}" class="text-sm text-blue-700 hover:underline">Clients</a>
    <h1 class="mt-2 text-2xl font-bold">Delivery reviews</h1>
    <p class="mt-2 text-sm text-slate-600">Client: {{ $owner->name }} ({{ $owner->slug }}). Review acknowledgements do not change delivery status or send messages.</p>
</div>
@if(session('status'))
    <p role="status" class="mb-4 rounded-xl border border-slate-200 p-4 text-sm">{{ session('status') }}</p>
@endif
<section class="rounded-2xl border border-slate-200 bg-white p-5">
    <h2 class="mb-3 text-lg font-semibold">Review required</h2>
    <p class="mb-4 text-sm text-slate-500">No recipient, SMS body, OTP, credentials or raw provider response is displayed. Investigate provider evidence before changing delivery outcomes.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[690px] text-left text-sm">
            <thead><tr class="border-b text-slate-500"><th class="py-2">Updated (UTC)</th><th>Delivery</th><th>Attempts</th><th>Error code</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($deliveries as $delivery)
                    <tr class="border-b border-slate-100">
                        <td class="py-3">{{ $delivery->updated_at }}</td>
                        <td class="font-mono text-xs"><a class="text-blue-700 hover:underline" href="{{ route('clients.delivery-reviews.history', [$owner->id, $delivery->id]) }}">{{ $delivery->id }}</a></td>
                        <td>{{ $delivery->attempt_count }}</td>
                        <td>{{ $delivery->last_error_code ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('clients.delivery-reviews.acknowledge', [$owner->id, $delivery->id]) }}" data-submit-once>
                                @csrf
                                <button class="rounded-lg border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50" type="submit" data-confirm="Record that investigation has started? This does not resolve or resend the message.">Acknowledge review</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-slate-500">No deliveries require review.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $deliveries->links() }}</div>
</section>

<section class="mt-6 rounded-2xl border border-slate-200 bg-white p-5">
    <h2 class="mb-2 text-lg font-semibold">Accepted Kavenegar messages</h2>
    <p class="mb-4 text-sm text-slate-500">A manual status inquiry is separate from SMS sending and does not mark the delivery as delivered. Network status queries are disabled by default.</p>
    <div class="overflow-x-auto">
        <table class="w-full min-w-[580px] text-left text-sm">
            <thead><tr class="border-b text-slate-500"><th class="py-2">Updated (UTC)</th><th>Delivery</th><th>Action</th></tr></thead>
            <tbody>
                @forelse($accepted as $delivery)
                    <tr class="border-b border-slate-100">
                        <td class="py-3">{{ $delivery->updated_at }}</td>
                        <td class="font-mono text-xs">{{ $delivery->id }}</td>
                        <td>
                            <form method="POST" action="{{ route('clients.delivery-reviews.check-status', [$owner->id, $delivery->id]) }}" data-submit-once>
                                @csrf
                                <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-xs hover:bg-slate-50" data-confirm="Query Kavenegar for status only? The delivery state will not change.">Check provider status</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-slate-500">No accepted Kavenegar messages awaiting confirmation.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
