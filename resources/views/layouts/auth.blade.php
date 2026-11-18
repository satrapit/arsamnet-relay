<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ in_array(app()->getLocale(), ['fa','ar'], true) ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Account') · Arsam Relay</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-800 antialiased">
    <main class="mx-auto flex min-h-screen max-w-xl items-center justify-center px-4 py-12">
        <section class="w-full rounded-2xl border border-slate-200 bg-white p-7 shadow-sm sm:p-10">
            <a href="/" class="mb-8 flex items-center gap-3 font-semibold text-xl text-slate-900">
                <span class="flex size-10 items-center justify-center rounded-xl bg-blue-600 text-white"><i class="fa-solid fa-paper-plane" aria-hidden="true"></i></span>
                Arsam Relay
            </a>
            @if(session('status')) <p role="status" class="mb-5 rounded-lg bg-blue-50 p-3 text-sm text-blue-800">{{ session('status') }}</p> @endif
            @if($errors->any()) <div role="alert" class="mb-5 rounded-lg bg-red-50 p-3 text-sm text-red-700"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
            @yield('content')
        </section>
    </main>
</body>
</html>
