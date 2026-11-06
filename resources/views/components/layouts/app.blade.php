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
<body class="min-h-screen bg-[#f6f4ef] font-sans text-slate-900 antialiased">
    @auth
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
                <div class="flex flex-wrap items-center gap-x-5 gap-y-1">
                    <a href="{{ route('home') }}" class="font-bold tracking-tight">RSL Case Review</a>
                    <nav class="flex flex-wrap gap-x-4 text-sm" aria-label="Main">
                        @can('view_worklist') <a href="{{ route('worklist') }}" @class(['font-semibold text-slate-900' => request()->routeIs('worklist', 'cases.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('worklist', 'cases.*')])>My desk</a> @endcan
                        @can('view_worklist') <a href="{{ route('results') }}" @class(['font-semibold text-slate-900' => request()->routeIs('results'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('results')])>Results</a> @endcan
                        @can('use_chat') <a href="{{ route('ask') }}" @class(['font-semibold text-slate-900' => request()->routeIs('ask'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('ask')])>Ask the records</a> @endcan
                        @can('view_company_records') <a href="{{ route('findings.index') }}" @class(['font-semibold text-slate-900' => request()->routeIs('findings.*', 'reviews.*'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('findings.*', 'reviews.*')])>Findings</a> @endcan
                        @can('receive_data') <a href="{{ route('intake') }}" @class(['font-semibold text-slate-900' => request()->routeIs('intake'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('intake')])>Data intake</a> @endcan
                        @can('view_audit_log') <a href="{{ route('notifications.sent') }}" @class(['font-semibold text-slate-900' => request()->routeIs('notifications.sent'), 'text-slate-600 hover:text-slate-900' => ! request()->routeIs('notifications.sent')])>Notifications sent</a> @endcan
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
                    @if (config('rsl.demo') && ! session('acting_for') && auth()->user()->hasRole(\App\Support\Roles::SUPER_ADMIN))
                        <details class="relative">
                            <summary class="cursor-pointer list-none text-slate-600 underline hover:text-slate-900">Act as…</summary>
                            <div class="absolute right-0 z-10 mt-1 w-56 rounded-lg border border-slate-200 bg-white p-1 shadow-lg">
                                @foreach (\App\Livewire\Auth\DemoFront::CARDS as [$email, $name, $title, $question])
                                    <form method="POST" action="{{ route('act-as', \App\Models\User::where('email', $email)->value('id')) }}">
                                        @csrf
                                        <button class="block w-full rounded px-3 py-1.5 text-left text-sm hover:bg-slate-50">{{ $name }} <span class="text-slate-500">· {{ $title }}</span></button>
                                    </form>
                                @endforeach
                            </div>
                        </details>
                    @endif
                    <span class="text-slate-600">{{ auth()->user()->name }}</span>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="text-slate-600 underline hover:text-slate-900">{{ config('rsl.demo') ? 'Switch person' : 'Sign out' }}</button>
                    </form>
                </div>
            </div>
        </header>
        @if (session('acting_for'))
            <div class="border-b border-amber-200 bg-amber-50 px-4 py-2 text-center text-sm text-amber-900">
                You are acting as <strong>{{ auth()->user()->name }}</strong>, {{ \App\Support\Roles::label(auth()->user()->getRoleNames()->first() ?? '') }}
                · <form method="POST" action="{{ route('act-as.stop') }}" class="inline"> @csrf <button class="underline hover:text-amber-700">Return to admin</button></form>
            </div>
        @endif
    @endauth

    <main class="mx-auto max-w-6xl px-4 py-8">
        {{ $slot }}
    </main>

    @auth
        @if (config('rsl.demo') && session('tour_step'))
            <livewire:tour.guided-tour />
        @endif
    @endauth

    @livewireScripts
</body>
</html>
