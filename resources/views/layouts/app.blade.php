<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Dashboard') · Arsam Relay</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-800 antialiased">
<div class="min-h-screen lg:flex">
    <button type="button" data-sidebar-backdrop class="fixed inset-0 z-40 hidden bg-slate-950/40 lg:hidden" aria-label="Dismiss navigation"></button>
    <aside id="platform-sidebar" data-sidebar aria-label="Platform navigation" class="fixed inset-y-0 start-0 z-50 w-72 shrink-0 -translate-x-full overflow-y-auto border-e border-slate-200 bg-white p-5 shadow-lg transition-transform duration-200 ease-out lg:static lg:min-h-screen lg:w-64 lg:translate-x-0 lg:shadow-none">
        <div class="mb-5 flex justify-end lg:hidden"><button type="button" data-sidebar-close class="rounded-lg p-2 text-slate-600 hover:bg-slate-100" aria-label="Close navigation"><i class="fa-solid fa-xmark" aria-hidden="true"></i></button></div>
        <div class="mb-8 flex items-center gap-3 text-lg font-bold text-slate-900">
            <span class="flex size-10 items-center justify-center rounded-xl bg-blue-600 text-white"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>Arsam Relay
        </div>
        <nav aria-label="Primary navigation" class="grid gap-2 text-sm">
            <a class="rounded-xl px-4 py-3 hover:bg-blue-50 {{ request()->routeIs('dashboard') ? 'bg-blue-50 font-semibold text-blue-700' : '' }}" href="{{ route('dashboard') }}"><i class="fa-solid fa-gauge-high mr-2" aria-hidden="true"></i>Overview</a>
            @can('clients.manage')
            <a class="rounded-xl px-4 py-3 hover:bg-blue-50 {{ request()->routeIs('clients.*') ? 'bg-blue-50 font-semibold text-blue-700' : '' }}" href="{{ route('clients.index') }}"><i class="fa-solid fa-users mr-2" aria-hidden="true"></i>Clients</a>
            @endcan
            @can('security.monitor')
            <a class="rounded-xl px-4 py-3 hover:bg-blue-50 {{ request()->routeIs('security-monitor.*') ? 'bg-blue-50 font-semibold text-blue-700' : '' }}" href="{{ route('security-monitor.index') }}"><i class="fa-solid fa-shield-halved mr-2" aria-hidden="true"></i>Security monitor</a>
            @endcan
            @can('feature-flags.view')
            <a class="rounded-xl px-4 py-3 hover:bg-blue-50 {{ request()->routeIs('feature-flags.*') ? 'bg-blue-50 font-semibold text-blue-700' : '' }}" href="{{ route('feature-flags.index') }}"><i class="fa-solid fa-toggle-on mr-2" aria-hidden="true"></i>Feature flags</a>
            @endcan
        </nav>
        <div class="mt-12 border-t border-slate-100 pt-6 text-xs text-slate-500">v0.1.0-alpha.1 · Internal preview</div>
    </aside>
    <div class="min-w-0 flex-1">
        <header class="flex min-h-20 items-center justify-between border-b border-slate-200 bg-white px-4 sm:px-8">
            <button type="button" data-sidebar-toggle aria-controls="platform-sidebar" aria-expanded="false" class="rounded-lg p-3 text-slate-700 hover:bg-slate-100 lg:hidden" aria-label="Toggle navigation"><i class="fa-solid fa-bars" aria-hidden="true"></i></button>
            <span class="hidden font-semibold text-slate-900 lg:inline">Dashboard</span>
            <div class="flex items-center gap-4 text-sm">
                <span class="hidden text-slate-600 sm:inline">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('logout') }}">@csrf <button type="submit" class="rounded-lg border border-slate-200 px-3 py-2 hover:bg-slate-50">Sign out</button></form>
            </div>
        </header>
        <main class="mx-auto max-w-7xl px-4 py-8 sm:px-8">
            @if(session('status')) <p role="status" class="mb-5 rounded-xl bg-blue-50 p-4 text-sm text-blue-800">{{ session('status') }}</p> @endif
            @yield('content')
        </main>
    </div>
</div>
</body>
</html>
