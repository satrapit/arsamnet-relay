@extends('layouts.auth')
@section('title', 'Two-factor verification')
@section('content')
<h1 class="text-2xl font-bold">Two-factor verification</h1>
<p class="mb-5 mt-2 text-sm text-slate-600">Enter the one-time code from your authenticator.</p>
<form method="POST" action="/two-factor-challenge" class="grid gap-4" data-submit-once>
    @csrf
    <label class="grid gap-2 text-sm font-semibold">Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" class="rounded-lg border border-slate-300 p-3"></label>
    <p class="text-center text-xs text-slate-500">Or use a recovery code instead:</p>
    <label class="grid gap-2 text-sm font-semibold">Recovery code<input name="recovery_code" autocomplete="off" class="rounded-lg border border-slate-300 p-3"></label>
    <button type="submit" class="rounded-lg bg-blue-600 p-3 font-semibold text-white">Verify</button>
</form>
@endsection
