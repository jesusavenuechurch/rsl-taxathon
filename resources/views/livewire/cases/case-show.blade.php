<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $c->reference }} · {{ $c->typeLabel() }}@if ($c->laneLabel()) · {{ $c->laneLabel() }}@endif</p>
        <h1 class="mt-1 text-2xl font-bold tracking-tight">{{ $c->taxpayer?->name ?? $c->tin }}</h1>
        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-700">
            <x-priority-badge :priority="$c->priority" />
            <span>{{ $c->team->name }}</span>
            <span>· {{ $c->assignee ? 'with '.$c->assignee->name : 'not yet taken' }}</span>
            @if ($c->exposure_estimate > 0)
                <span>· largest estimated exposure {{ \App\Support\Format::money($c->exposure_estimate) }} (estimates overlap, so they are not added)</span>
            @endif
        </div>
        <p class="mt-1 text-sm text-slate-600">When opened: {{ $c->routing_reason }}</p>
        @php($handoff = $c->events->where('type', 'referred')->last())
        @if ($handoff)
            <p class="mt-2 rounded-lg border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900">
                <span class="font-semibold">Handed over by {{ $handoff->actor_label }}:</span> {{ $handoff->note }}
            </p>
        @endif
        <div class="mt-3"><x-journey-strip :case="$c" /></div>
    </div>

    @if ($canAct)
        <section class="rounded-xl border border-slate-300 bg-white p-4" aria-labelledby="next-step">
            <h2 id="next-step" class="font-bold">Your next step</h2>
            <div class="mt-3 flex flex-wrap items-start gap-4">
                @unless ($c->assigned_user_id === auth()->id())
                    <button wire:click="take" class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700">Take this case</button>
                @endunless
                <form wire:submit="refer" class="flex w-full flex-1 flex-wrap items-end gap-2 sm:w-auto">
                    <label class="w-full text-sm font-semibold sm:w-auto">
                        Refer to
                        <select wire:model="referTo" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 sm:w-auto">
                            <option value="">Choose a team…</option>
                            @foreach ($teams as $t) <option value="{{ $t->code }}">{{ $t->name }}</option> @endforeach
                        </select>
                    </label>
                    <label class="w-full min-w-0 text-sm font-semibold sm:w-auto sm:flex-1">
                        Why
                        <input wire:model="referReason" type="text" placeholder="e.g. Group charges need a specialist review"
                               class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2">
                    </label>
                    <button class="rounded-lg border border-slate-900 px-4 py-2 font-semibold hover:bg-slate-50">Refer</button>
                </form>
            </div>
            @error('referTo') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            @error('referReason') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
            <p class="mt-3 text-sm text-slate-600">Closing a case is an officer outcome (concern supported, explained, more information needed, or system error) with the reasons recorded. It is never a plain dismiss.</p>
        </section>
    @else
        <p class="rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600">This case is with {{ $c->team->name }}. Only that team or a team lead can act on it.</p>
    @endif

    @if ($c->refundClaim)
        <section class="rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">Refund letter</h2>
            <p class="mt-1 text-sm">
                <a class="font-mono underline" href="{{ route('records.show', $c->refundClaim->record_id) }}">{{ $c->refundClaim->record_id }}</a>:
                {{ \App\Support\Format::money($c->refundClaim->amount_claimed) }} claimed for {{ $c->refundClaim->vat_periods }},
                letter dated {{ $c->refundClaim->letter_date->format('j M Y') }}, verification due {{ $c->refundClaim->verification_due->format('j M Y') }} ({{ $c->refundClaim->status }}).
            </p>
            <p class="mt-1 text-sm text-slate-600">
                {{ $c->refund_lane === 'lower_risk' ? 'Lower risk: the checks performed found the claims consistent with Lekuka and customs. Normal verification still applies.' : 'Needs review: not refused on a pattern alone; the checks below need an officer.' }}
            </p>
        </section>
    @endif

    <section class="space-y-3">
        <h2 class="font-bold">Findings ({{ $c->findings->where('status', 'open')->count() }} open)</h2>
        @foreach ($c->findings as $f)
            <article @class(['rounded-xl border bg-white p-4', 'border-slate-200' => $f->status === 'open', 'border-dashed border-slate-300 opacity-70' => $f->status !== 'open'])>
                <div class="flex flex-wrap items-center gap-2">
                    <a href="{{ route('findings.show', $f->reference) }}" class="font-semibold underline decoration-slate-300 hover:decoration-slate-900">{{ $f->pattern_code }} {{ $f->title }}</a>
                    <x-finding-kind :finding="$f" />
                    <span class="text-xs text-slate-500" title="Evidence level {{ $f->evidence_level }}">{{ \App\Support\Format::evidenceLevel($f->evidence_level) }}</span>
                    @if ($f->status !== 'open') <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold">{{ ucfirst($f->status) }}</span> @endif
                    @if ($f->exposure_estimate > 0)
                        <span class="ml-auto text-sm tabular-nums">est. {{ \App\Support\Format::money($f->exposure_estimate) }}</span>
                    @endif
                </div>
                <p class="mt-1 text-sm">{{ $f->wording }}</p>
                <p class="mt-2 flex flex-wrap gap-1">
                    @foreach ($f->evidence->take(8) as $e)
                        <a href="{{ route('records.show', ['recordId' => $e->record_id, 'v' => $e->record_version]) }}" class="rounded border border-slate-300 px-1.5 font-mono text-xs hover:bg-slate-50">{{ $e->record_id }}</a>
                    @endforeach
                    @if ($f->evidence->count() > 8)
                        <a href="{{ route('findings.show', $f->reference) }}" class="text-xs text-slate-600 underline">+{{ $f->evidence->count() - 8 }} more</a>
                    @endif
                </p>
                <livewire:cases.finding-checklist :finding="$f" :can-act="$canAct" :key="'checklist-'.$f->id" />
                <livewire:cases.outcome-form :finding="$f" :can-act="$canAct" :key="'outcome-'.$f->id" />
            </article>
        @endforeach
    </section>

    <livewire:cases.case-brief-panel :case="$c" :can-act="$canAct" :key="'brief-'.$c->id" />

    <livewire:cases.information-request-panel :case="$c" :can-act="$canAct" :key="'requests-'.$c->id" />

    <x-memory-panel :checks="$memory" />

    <section class="rounded-xl border border-slate-200 bg-white p-4">
        <h2 class="font-bold">Timeline</h2>
        <ol class="mt-3 space-y-3 border-l-2 border-slate-200 pl-4">
            @foreach ($c->events as $e)
                <li>
                    <p class="text-sm">
                        <span class="font-semibold">{{ $e->actor_label }}</span>
                        @switch($e->type)
                            @case('opened') opened the case for {{ $e->toTeam?->name }} @break
                            @case('findings_added') added {{ implode(', ', $e->data['findings'] ?? []) }} @break
                            @case('findings_withdrawn') withdrew {{ implode(', ', $e->data['findings'] ?? []) }} @break
                            @case('assigned') took the case @break
                            @case('referred') referred it from {{ $e->fromTeam?->name }} to {{ $e->toTeam?->name }} @break
                            @case('note') added a note @break
                            @case('information_requested') sent an information request @break
                            @case('document_received') received a document @break
                            @case('decide_now') moved the case to decision @break
                            @default {{ str_replace('_', ' ', $e->type) }}
                        @endswitch
                        <span class="text-slate-500">· {{ $e->occurred_at->format('j M Y H:i') }}</span>
                    </p>
                    @if ($e->note && $e->type !== 'assigned') <p class="text-sm text-slate-700">{{ $e->note }}</p> @endif
                </li>
            @endforeach
        </ol>
        @if ($canAct)
            <form wire:submit="addNote" class="mt-4 flex flex-wrap gap-2">
                <label for="note" class="sr-only">Add a note</label>
                <input wire:model="noteText" id="note" type="text" placeholder="Add a note to the timeline" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-2">
                <button class="rounded-lg border border-slate-300 px-4 py-2 font-semibold hover:bg-slate-50">Add note</button>
            </form>
        @endif
    </section>
</div>
