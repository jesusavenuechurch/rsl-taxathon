<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Results</h1>
        <p class="mt-1 text-slate-600">A summary of work done, not a scoreboard of taxpayers flagged. The mock data was written to contain these stories; this shows the workflow behaves as designed.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Findings concluded</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $findingsConcluded }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Exposure referred for investigation</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ \App\Support\Format::money($exposureReferred) }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Refunds verified within 3 months</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $refunds['verified'] }} / {{ $refunds['total'] }}</p>
            @if ($refunds['medianDays'] !== null) <p class="text-xs text-slate-500">Median {{ $refunds['medianDays'] }} day(s) open to close.</p> @endif
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Average days, open to close</p>
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ $avgCycleDays ?? '–' }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">Cases closed, by outcome</h2>
            @if (empty($byOutcome))
                <p class="mt-2 text-sm text-slate-500">Nothing concluded yet.</p>
            @else
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($byOutcome as $label => $count)
                        <li class="flex justify-between border-b border-slate-100 py-1"><span>{{ $label }}</span><span class="tabular-nums font-semibold">{{ $count }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">Per pattern: concern supported vs explained</h2>
            <p class="text-xs text-slate-500">The feedback loop: how often a pattern turned out to need referral, versus a legitimate explanation.</p>
            @if ($byPattern->isEmpty())
                <p class="mt-2 text-sm text-slate-500">Nothing concluded yet.</p>
            @else
                <table class="mt-2 w-full text-sm">
                    <thead><tr class="text-left text-slate-500"><th class="py-1">Pattern</th><th class="py-1 text-right">Concern supported</th><th class="py-1 text-right">Explained</th></tr></thead>
                    <tbody>
                        @foreach ($byPattern as $code => $counts)
                            <tr class="border-t border-slate-100"><td class="py-1 font-mono">{{ $code }}</td><td class="py-1 text-right tabular-nums">{{ $counts['concern_supported'] }}</td><td class="py-1 text-right tabular-nums">{{ $counts['explained'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Closed cases</h2>
        @if ($closedCases->isEmpty())
            <p class="mt-2 text-sm text-slate-500">No case has closed yet.</p>
        @else
            <div class="mt-3 space-y-3">
                @foreach ($closedCases as $c)
                    <article class="rounded-lg border border-slate-200 p-3">
                        <p class="font-semibold">
                            <a href="{{ route('cases.show', $c->reference) }}" class="underline decoration-slate-300 hover:decoration-slate-900">{{ $c->reference }}</a>
                            {{ $c->taxpayer?->name ?? $c->tin }} <span class="font-normal text-slate-500">closed {{ $c->closed_at?->format('j M Y') }}</span>
                        </p>
                        <ul class="mt-1 space-y-1 text-sm text-slate-700">
                            @foreach ($c->findings as $f)
                                @if ($f->outcome)
                                    <li>
                                        <span class="font-mono text-xs">{{ $f->pattern_code }}</span> {{ $f->outcome->outcomeLabel() }}: {{ $f->outcome->conclusion }}
                                        @foreach ($f->evidence()->take(4)->get() as $e)
                                            <a href="{{ route('records.show', $e->record_id) }}" class="ml-1 rounded border border-slate-300 px-1 font-mono text-xs hover:bg-slate-50">{{ $e->record_id }}</a>
                                        @endforeach
                                    </li>
                                @endif
                            @endforeach
                        </ul>
                    </article>
                @endforeach
            </div>
        @endif
    </section>
</div>
