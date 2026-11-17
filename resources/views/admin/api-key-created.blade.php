@extends('layouts.app')
@section('title', 'API key created')
@section('content')
<h1 class="text-2xl font-bold">API key created for {{ $owner->name }}</h1>
<p class="my-4 text-sm text-red-700">Copy this key now. It is never stored in recoverable form and will not be displayed again.</p>
<div class="break-all rounded-xl border border-amber-200 bg-amber-50 p-4 font-mono text-sm" role="status">{{ $token }}</div>
<a href="{{ route('clients.index') }}" class="mt-5 inline-block rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white">Done</a>
@endsection
