@props(['case'])
@php
    $c = $case;
    $story = ($c->latestBrief()?->status === 'approved' ? $c->latestBrief()->draft['statements'][0]['text'] ?? null : null)
        ?? $c->findings->sortByDesc('exposure_estimate')->first()?->title
        ?? $c->typeLabel();
    $handoff = $c->events->where('type', 'referred')->last();
    $waitingDays = (int) app(\App\Support\Clock::class)->today()->diffInDays($c->opened_at, true);
    $mine = $c->assigned_user_id === auth()->id();
@endphp
<article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
    <p class="font-semibold">{{ $c->taxpayer?->name ?? $c->tin }}</p>
    <p class="mt-1 text-sm text-slate-700">{{ $story }}</p>
    <p class="mt-2 text-sm">
        <span class="font-semibold">Needs you:</span>
        @if ($c->stage === \App\Models\Cases\ReviewCase::STAGE_SIGNOFF && auth()->user()->can('second_signoff'))
            give the second sign-off on a decision
        @elseif ($mine)
            continue working it
        @elseif (! $c->assigned_user_id)
            take this case{{ $handoff ? ', handed over by '.$handoff->actor_label.($handoff->note ? ': "'.$handoff->note.'"' : '') : '' }}
        @else
            {{ \App\Models\Cases\ReviewCase::STAGE_LABELS[$c->stage] ?? $c->stage }}, with {{ $c->assignee?->name ?? 'the team' }}
        @endif
    </p>
    <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-600">
        <x-priority-badge :priority="$c->priority" />
        @if ($c->exposure_estimate > 0) <span>est. {{ \App\Support\Format::money($c->exposure_estimate) }}</span> @endif
        @if ($c->refundClaim)
            <span>Verification due {{ $c->refundClaim->verification_due->format('j M') }}</span>
        @elseif ($waitingDays === 0)
            <span>Arrived today</span>
        @else
            <span>Waiting {{ $waitingDays }} day{{ $waitingDays === 1 ? '' : 's' }}</span>
        @endif
    </p>
    <a href="{{ route('cases.show', $c->reference) }}" class="mt-3 inline-block rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Open case</a>
</article>
