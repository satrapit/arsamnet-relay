@extends('layouts.auth')
@section('title', 'Reset password')
@section('content')
<h1 class="text-2xl font-bold text-slate-900">Reset your password</h1>
<p class="mb-6 mt-2 text-sm text-slate-600">Enter your email to request a reset link.</p>
<form method="POST" action="{{ route('password.email') }}" class="grid gap-4" data-submit-once>
    @csrf
    <label class="grid gap-2 text-sm font-semibold">Email address<input name="email" type="email" value="{{ old('email') }}" required autocomplete="email" class="rounded-lg border border-slate-300 px-4 py-3 font-normal outline-blue-600"></label>
    <button type="submit" class="rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white">Send reset link</button>
</form>
@endsection
