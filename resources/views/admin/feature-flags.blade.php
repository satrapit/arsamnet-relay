@extends('layouts.app')
@section('title', 'Feature flags')
@section('content')
<div class="mb-7"><p class="text-sm font-semibold text-blue-600">System</p><h1 class="mt-1 text-3xl font-bold text-slate-900">Feature flags</h1>
<p class="mt-3 text-sm text-slate-600">Optional capabilities only. Security protections cannot be disabled.</p></div>
<div class="grid gap-4">
    @foreach($flags as $flag)
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div><h2 class="font-semibold text-slate-900">{{ $flag['key'] }}</h2><p class="mt-1 text-sm text-slate-600">{{ $flag['description'] }}</p></div>
            <span class="rounded-full px-3 py-1 text-xs font-semibold {{ $flag['enabled'] ? 'bg-green-50 text-green-700' : 'bg-slate-100 text-slate-600' }}">{{ $flag['enabled'] ? 'Enabled' : 'Disabled' }}</span>
        </div>
        @can('feature-flags.write')
        <form method="POST" action="{{ route('feature-flags.update', $flag['key']) }}" class="mt-4" data-submit-once>
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
@endsection
