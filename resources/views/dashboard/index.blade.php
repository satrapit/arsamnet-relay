@extends('layouts.app')
@section('title', 'Overview')
@section('content')
<div class="mb-8">
    <p class="text-sm font-medium text-blue-600">Platform overview</p>
    <h1 class="mt-2 text-3xl font-bold text-slate-900">Welcome to Arsam Relay</h1>
    <p class="mt-2 text-slate-600">Development preview. No message delivery provider is connected.</p>
</div>
<div class="grid gap-5 md:grid-cols-3">
    @foreach([['Clients','fa-users'],['Messages','fa-envelope'],['Delivery operations','fa-truck-fast']] as [$name, $icon])
        <x-common.component-card :title="$name" desc="Not available in this milestone.">
            <i class="fa-solid {{ $icon }} text-xl text-blue-600" aria-hidden="true"></i>
        </x-common.component-card>
    @endforeach
</div>
@endsection
