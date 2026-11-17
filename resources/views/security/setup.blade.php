@extends('layouts.auth')
@section('title', 'Set up two-factor authentication')
@section('content')
<h1 class="text-2xl font-bold">Protect your account</h1>
<p class="my-4 text-sm text-slate-600">Two-factor authentication is required before you can access the platform dashboard.</p>
@if(!auth()->user()->isPlatformStaff())
    <p class="text-sm text-red-700">No platform role is assigned to this account.</p>
@elseif(!auth()->user()->two_factor_secret)
    <a href="{{ url('/user/confirm-password') }}" class="mb-4 inline-block text-sm text-blue-700">Confirm your password first</a>
    <form method="POST" action="/user/two-factor-authentication" data-submit-once>@csrf
        <button type="submit" class="rounded-lg bg-blue-600 p-3 font-semibold text-white">Enable authenticator</button>
    </form>
@elseif(!auth()->user()->two_factor_confirmed_at)
    <div class="my-4 max-w-xs rounded-lg border border-slate-200 p-3">{!! auth()->user()->twoFactorQrCodeSvg() !!}</div>
    <p class="mb-4 text-sm">Scan the QR code with your authenticator app and enter its code.</p>
    <form method="POST" action="/user/confirmed-two-factor-authentication" class="grid gap-3" data-submit-once>@csrf
        <input name="code" inputmode="numeric" pattern="[0-9]*" aria-label="Authenticator code" required class="rounded-lg border border-slate-300 p-3">
        <button class="rounded-lg bg-blue-600 p-3 font-semibold text-white" type="submit">Confirm setup</button>
    </form>
@else
    <p class="my-4 text-sm text-green-700">Two-factor authentication is active.</p>
    <p class="text-sm">Store your recovery codes in a secure password manager.</p>
    <ul class="my-3 space-y-1 break-all font-mono text-xs">@foreach(auth()->user()->recoveryCodes() as $code)<li>{{ $code }}</li>@endforeach</ul>
    <a class="text-sm font-semibold text-blue-700" href="{{ route('dashboard') }}">Continue to dashboard</a>
@endif
@endsection
