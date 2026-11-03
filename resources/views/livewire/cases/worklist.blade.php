<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Worklist</h1>
        <p class="mt-1 text-slate-600">Ranked by priority, then estimated exposure. A case is a reason to review, never proof.</p>
    </div>

    <nav class="flex flex-wrap gap-2" aria-label="Worklist views">
        @foreach (['team' => ($teamName ?? 'My team'), 'mine' => 'Assigned to me', 'all' => 'All teams'] as $key => $label)
            @continue($key === 'team' && $counts['team'] === null)
            <a href="?tab={{ $key }}" @class([
                'rounded-full px-3 py-1 text-sm font-semibold',
                'bg-slate-900 text-white' => $tab === $key,
                'bg-white text-slate-700 ring-1 ring-slate-300 hover:bg-slate-50' => $tab !== $key,
            ])>{{ $label }} ({{ $counts[$key] }})</a>
        @endforeach
    </nav>

    @if ($cases->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-slate-600">No open cases here.</p>
    @else
        <ul class="space-y-3">
            @foreach ($cases as $c)
                <li>
                    <a href="{{ route('cases.show', $c->reference) }}" class="block rounded-xl border border-slate-200 bg-white p-4 hover:border-slate-400">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-priority-badge :priority="$c->priority" />
                            <span class="font-bold">{{ $c->taxpayer?->name ?? $c->tin }}</span>
                            <span class="text-sm text-slate-600">{{ $c->typeLabel() }}@if ($c->laneLabel()) · {{ $c->laneLabel() }}@endif</span>
                            @if ($c->exposure_estimate > 0)
                                <span class="ml-auto text-sm tabular-nums text-slate-700">est. {{ \App\Support\Format::money($c->exposure_estimate) }}</span>
                            @endif
                        </div>
                        <div class="mt-1 flex flex-wrap gap-x-3 text-sm text-slate-600">
                            <span class="font-mono">{{ $c->reference }}</span>
                            <span>{{ $c->findings_count }} open finding(s)</span>
                            <span>{{ $c->team->name }}</span>
                            <span>{{ $c->assignee ? 'With '.$c->assignee->name : 'Not yet taken' }}</span>
                            <span>Opened {{ $c->opened_at->format('j M') }}</span>
                        </div>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
</div>
