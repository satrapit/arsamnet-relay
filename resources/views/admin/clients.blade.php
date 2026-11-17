@extends('layouts.app')
@section('title', 'Clients')
@section('content')
<div class="mb-7"><p class="text-sm font-semibold text-blue-600">Platform</p><h1 class="mt-1 text-3xl font-bold">Clients</h1><p class="mt-2 text-sm text-slate-600">Create isolated customer workspaces.</p></div>
<section class="mb-6 rounded-2xl border border-slate-200 bg-white p-6">
    <h2 class="mb-4 font-semibold">Create client</h2>
    @if($errors->any()) <ul class="mb-4 list-disc pl-5 text-sm text-red-700">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul> @endif
    <form method="POST" action="{{ route('clients.store') }}" class="grid gap-3 sm:grid-cols-3" data-submit-once>
        @csrf
        <input name="name" required maxlength="191" placeholder="Client name" class="rounded-lg border border-slate-300 p-3">
        <input name="slug" required maxlength="100" pattern="[a-zA-Z0-9_-]+" placeholder="client-slug" class="rounded-lg border border-slate-300 p-3">
        <button type="submit" class="rounded-lg bg-blue-600 p-3 font-semibold text-white">Create</button>
    </form>
</section>
<div class="grid gap-4">
    @foreach($clients as $client)
    <section class="rounded-2xl border border-slate-200 bg-white p-5">
        <div class="mb-4 flex items-center justify-between gap-3"><div><h3 class="font-semibold">{{ $client->name }}</h3><p class="text-sm text-slate-500">{{ $client->slug }} · #{{ $client->id }}</p></div><span class="text-xs text-slate-500">{{ $client->status }}</span></div>
        <form method="POST" action="{{ route('clients.keys.store', $client->id) }}" class="flex flex-wrap gap-2" data-submit-once>
            @csrf
            <input name="label" required maxlength="100" aria-label="Key label" placeholder="Key label" class="min-w-0 flex-1 rounded-lg border border-slate-300 p-2 text-sm">
            <label class="flex items-center gap-2 text-xs"><input name="scopes[]" type="checkbox" value="messages.publish" checked>Publish</label>
            <label class="flex items-center gap-2 text-xs"><input name="scopes[]" type="checkbox" value="messages.read">Read</label>
            <button type="submit" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">Issue API key</button>
        </form>
    </section>
    @endforeach
    {{ $clients->links() }}
</div>
@endsection
