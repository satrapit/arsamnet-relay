@extends('layouts.auth')
@section('title', 'Verify email')
@section('content')
<h1 class="text-2xl font-bold">Verify your email address</h1>
<p class="my-5 text-sm text-slate-600">Please use the verification link sent to your email.</p>
<form method="POST" action="{{ route('verification.send') }}" data-submit-once>@csrf
<button class="rounded-lg bg-blue-600 p-3 font-semibold text-white" type="submit">Resend verification email</button></form>
@endsection
