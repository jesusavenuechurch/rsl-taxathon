<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $r->reference }} · {{ $r->taxpayer?->name ?? $r->tin }}</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight">Filing review{{ $r->anchor_label ? ': '.$r->anchor_label : '' }}</h1>
        <p class="mt-1 text-slate-600">Reviewed {{ $r->reviewed_at->format('j M Y') }}</p>
    </div>

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Summary</h2>
        <p class="mt-1">{{ $r->summary }}</p>
    </section>

    <x-memory-panel :checks="$memory" />

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 font-bold">All {{ $r->checks->count() }} checks</h2>
        <ul class="divide-y divide-slate-100">
            @foreach ($r->checks as $check)
                <li class="grid gap-1 px-4 py-3 sm:grid-cols-[14rem_1fr]">
                    <div class="font-semibold">
                        {{ $check->pattern_code }} {{ $check->ruleVersion->rule->name }}
                        <span @class([
                            'ml-1 whitespace-nowrap rounded-full px-2 py-0.5 text-xs',
                            'bg-amber-100 text-amber-900' => $check->result === 'fired',
                            'bg-slate-100 text-slate-700' => $check->result !== 'fired',
                        ])>{{ str_replace('_', ' ', $check->result) }}</span>
                    </div>
                    <div class="text-sm text-slate-700">
                        {{ $check->explanation }}
                        @if ($check->result === 'fired' && ($finding = $findings->get($check->pattern_code)))
                            <a class="ml-1 underline" href="{{ route('findings.show', $finding->reference) }}">{{ $finding->reference }}</a>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    </section>
</div>
