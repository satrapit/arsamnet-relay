@extends('layouts.auth')
@section('title', 'Choose password')
@section('content')
<h1 class="mb-6 text-2xl font-bold">Choose a new password</h1>
<form method="POST" action="{{ route('password.update') }}" class="grid gap-4" data-submit-once>
    @csrf
    <input type="hidden" name="token" value="{{ $request->route('token') }}">
    <label class="grid gap-2 text-sm font-semibold">Email<input name="email" type="email" value="{{ old('email', $request->email) }}" required class="rounded-lg border border-slate-300 p-3"></label>
    <label class="grid gap-2 text-sm font-semibold">New password<input name="password" type="password" required autocomplete="new-password" class="rounded-lg border border-slate-300 p-3"></label>
    <label class="grid gap-2 text-sm font-semibold">Confirm new password<input name="password_confirmation" type="password" required autocomplete="new-password" class="rounded-lg border border-slate-300 p-3"></label>
    <button class="rounded-lg bg-blue-600 p-3 font-semibold text-white" type="submit">Reset password</button>
</form>
@endsection
