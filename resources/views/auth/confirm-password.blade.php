@extends('layouts.auth')
@section('title', 'Confirm password')
@section('content')
<h1 class="text-2xl font-bold">Confirm your password</h1>
<p class="my-4 text-sm text-slate-600">This security-sensitive action requires a recent password confirmation.</p>
<form method="POST" action="{{ route('password.confirm') }}" class="grid gap-4" data-submit-once>
    @csrf
    <label class="grid gap-2 text-sm font-semibold">Password<input type="password" name="password" autocomplete="current-password" required class="rounded-lg border border-slate-300 p-3"></label>
    <button type="submit" class="rounded-lg bg-blue-600 p-3 font-semibold text-white">Confirm</button>
</form>
@endsection
