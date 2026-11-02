<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Findings</h1>
        <p class="mt-1 text-slate-600">Patterns are reasons to review, never proof. Each finding links to the exact records behind it.</p>
    </div>

    @foreach ($companies as $c)
        <section class="rounded-xl border border-slate-200 bg-white">
            <div class="flex flex-wrap items-baseline justify-between gap-2 border-b border-slate-100 px-4 py-3">
                <h2 class="font-bold">{{ $c['taxpayer']->name }} <span class="font-mono text-xs font-normal text-slate-500">{{ $c['taxpayer']->tin }}</span></h2>
                @if ($c['review'])
                    <a href="{{ route('reviews.show', $c['review']->reference) }}" class="text-sm text-slate-600 underline">{{ $c['review']->reference }} · all 14 checks</a>
                @endif
            </div>
            @if ($c['findings']->isEmpty())
                <p class="px-4 py-3 text-sm text-slate-600">{{ $c['review']?->summary ?? 'Not analysed yet.' }}</p>
            @else
                <ul class="divide-y divide-slate-100">
                    @foreach ($c['findings'] as $f)
                        <li class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="{{ route('findings.show', $f->reference) }}" class="font-semibold underline decoration-slate-300 hover:decoration-slate-900">{{ $f->pattern_code }} {{ $f->title }}</a>
                                <x-finding-kind :finding="$f" />
                                <span class="text-xs text-slate-500">Evidence level {{ $f->evidence_level }}</span>
                                @if ($f->exposure_estimate > 0)
                                    <span class="ml-auto text-sm tabular-nums text-slate-700">est. {{ \App\Support\Format::money($f->exposure_estimate) }}</span>
                                @endif
                            </div>
                            <p class="mt-1 text-sm text-slate-700">{{ $f->wording }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endforeach
</div>
