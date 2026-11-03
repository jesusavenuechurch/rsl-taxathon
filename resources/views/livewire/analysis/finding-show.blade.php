<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $f->reference }} · {{ $f->taxpayer?->name ?? $f->tin }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $f->pattern_code }} {{ $f->title }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm">
            <x-finding-kind :finding="$f" />
            <span class="text-slate-600">Evidence level {{ $f->evidence_level }} · raised {{ $f->raised_at->format('j M Y') }} · rule {{ $f->pattern_code }} v{{ $f->ruleVersion->version }}</span>
            @if ($f->status !== 'open') <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold">{{ ucfirst($f->status) }}</span> @endif
        </div>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">What the records show</h2>
        <p class="mt-2 text-lg leading-relaxed">{{ $f->wording }}</p>
        <p class="mt-3 text-sm text-slate-600">This is a reason to review, not a conclusion. Only an officer concludes.</p>
    </section>

    <x-memory-panel :checks="$memory" :highlight="$f->pattern_code" />

    <div class="grid gap-6 md:grid-cols-2">
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">Possible lawful explanations</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($f->checklist->where('type', 'lawful_explanation') as $item) <li>{{ $item->text }}</li> @endforeach
            </ul>
        </section>
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">What the officer must establish</h2>
            <ul class="mt-2 list-disc space-y-1 pl-5">
                @foreach ($f->checklist->where('type', 'must_establish') as $item) <li>{{ $item->text }}</li> @endforeach
            </ul>
            @if ($f->ruleVersion->documents_to_request)
                <p class="mt-3 text-sm text-slate-600">Documents that could help: {{ implode(', ', $f->ruleVersion->documents_to_request) }}.</p>
            @endif
        </section>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Estimated exposure</h2>
        @if ($f->exposure_estimate > 0)
            <p class="mt-1 text-2xl font-bold tabular-nums">{{ \App\Support\Format::money($f->exposure_estimate) }}</p>
            <p class="text-sm text-slate-600">An estimate to help prioritise ({{ $f->exposure_basis }}), never an assessed amount.</p>
        @else
            <p class="mt-1 text-slate-700">Not estimated for this pattern.</p>
        @endif
        @if (! in_array(trim($f->ruleVersion->rule->legal_source), ['', '-'], true))
            <p class="mt-2 text-sm text-slate-600">Legal source: {{ $f->ruleVersion->rule->legal_source }}</p>
        @endif
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 font-bold">Evidence ({{ $f->evidence->count() }} {{ Str::plural('record', $f->evidence->count()) }})</h2>
        <ul class="flex flex-wrap gap-2 p-4">
            @foreach ($f->evidence as $e)
                <li>
                    <a href="{{ route('records.show', ['recordId' => $e->record_id, 'v' => $e->record_version]) }}"
                       title="{{ $channels[$e->channel]->label }}, version {{ $e->record_version }}"
                       class="inline-block rounded-lg border border-slate-300 px-2 py-1 font-mono text-sm hover:bg-slate-50">{{ $e->record_id }}</a>
                </li>
            @endforeach
        </ul>
    </section>
</div>
