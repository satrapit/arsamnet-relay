@extends('layouts.app')
@section('title', 'Overview')
@section('content')
<div class="mb-8">
    <p class="text-sm font-medium text-blue-600">Platform overview</p>
    <h1 class="mt-2 text-3xl font-bold text-slate-900">Welcome to Arsam Relay</h1>
    <p class="mt-2 text-slate-600">Messaging operations and delivery services will appear here as the foundation is verified.</p>
</div>
<div class="grid gap-5 md:grid-cols-3">
    @foreach([['Clients','fa-users'],['Messages','fa-envelope'],['Delivery operations','fa-truck-fast']] as [$title,$icon])
    <section class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
        <i class="fa-solid {{ $icon }} text-xl text-blue-600" aria-hidden="true"></i>
        <h2 class="mt-4 text-base font-semibold">{{ $title }}</h2>
        <p class="mt-2 text-sm text-slate-500">Not available in this milestone.</p>
    </section>
    @endforeach
</div>
@endsection
