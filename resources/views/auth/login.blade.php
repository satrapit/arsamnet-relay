@extends('layouts.auth')
@section('title', 'Sign in')
@section('content')
<h1 class="text-2xl font-bold text-slate-900">Sign in</h1>
<p class="mb-6 mt-2 text-sm text-slate-600">Access your Arsam Relay account.</p>
<form method="POST" action="{{ route('login') }}" class="grid gap-4" data-submit-once>
    @csrf
    <label class="grid gap-2 text-sm font-semibold">Email address <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required class="rounded-lg border border-slate-300 px-4 py-3 font-normal outline-blue-600"></label>
    <label class="grid gap-2 text-sm font-semibold">Password <input name="password" type="password" autocomplete="current-password" required class="rounded-lg border border-slate-300 px-4 py-3 font-normal outline-blue-600"></label>
    <label class="flex items-center gap-2 text-sm text-slate-600"><input type="checkbox" name="remember" value="1"> Remember me</label>
    <button type="submit" class="mt-1 rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">Sign in</button>
</form>
<a class="mt-6 inline-block text-sm text-blue-700 hover:underline" href="{{ route('password.request') }}">Forgot your password?</a>
@endsection
