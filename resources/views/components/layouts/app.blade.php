<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased">
    @auth
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <a href="{{ route('home') }}" class="font-bold tracking-tight">RSL Case Review</a>
                    <nav class="flex flex-wrap gap-x-4 text-sm" aria-label="Main">
                        @can('view_worklist') <a href="{{ route('worklist') }}" @class(['font-semibold text-slate-900' => request()->routeIs('worklist', 'cases.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('worklist', 'cases.*')])>Worklist</a> @endcan
                        @can('view_company_records') <a href="{{ route('findings.index') }}" @class(['font-semibold text-slate-900' => request()->routeIs('findings.*', 'reviews.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('findings.*', 'reviews.*')])>Findings</a> @endcan
                        @can('receive_data') <a href="{{ route('intake') }}" @class(['font-semibold text-slate-900' => request()->routeIs('intake'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('intake')])>Data intake</a> @endcan
                    </nav>
                </div>
                <div class="flex flex-wrap items-center gap-4 text-sm">
                    <span @class([
                        'rounded-full px-3 py-1 font-semibold',
                        'bg-amber-100 text-amber-900' => app(\App\Support\Clock::class)->isSimulated(),
                        'bg-slate-100 text-slate-700' => ! app(\App\Support\Clock::class)->isSimulated(),
                    ]) title="All dates in the system use this day">
                        Today: {{ app(\App\Support\Clock::class)->today()->format('j M Y') }}
                        @if (app(\App\Support\Clock::class)->isSimulated()) (demo) @endif
                    </span>
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-600 underline hover:text-slate-900">Sign out</button>
                    </form>
                </div>
            </div>
        </header>
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

    @livewireScripts
</body>
</html>
