@extends('layouts.auth')
@section('title', 'Two-factor recovery codes')
@section('content')
<h1 class="text-2xl font-bold text-slate-900">Recovery codes</h1>
<p class="my-4 text-sm text-slate-600">Store these codes offline in a trusted password manager. Each code can be used once if your authenticator is unavailable.</p>
@if(auth()->user()->hasEnabledTwoFactorAuthentication())
    <ul class="my-5 space-y-2 rounded-lg border border-amber-200 bg-amber-50 p-4 font-mono text-sm">
        @foreach(auth()->user()->recoveryCodes() as $code)
            <li>{{ $code }}</li>
        @endforeach
    </ul>
@else
    <p class="my-4 text-sm text-amber-800">Two-factor authentication is not yet confirmed.</p>
@endif
<a href="{{ route('dashboard') }}" class="inline-block text-sm font-semibold text-blue-700">Back to dashboard</a>
@endsection
