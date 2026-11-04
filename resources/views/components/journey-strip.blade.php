@props(['case'])
@php
    $stages = \App\Models\Cases\ReviewCase::STAGE_ORDER;
    $currentIndex = array_search($case->stage, $stages, true);
    $reachedSpecialist = $case->team->code === \App\Models\Team::SPECIAL_SECTORS
        || $case->events->contains(fn ($e) => $e->toTeam?->code === \App\Models\Team::SPECIAL_SECTORS);
@endphp
<ol class="flex flex-wrap items-center gap-1 text-xs" aria-label="Case journey">
    <li class="rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-900">Received</li>
    @foreach ($stages as $i => $stage)
        @php
            $skipped = $stage === \App\Models\Cases\ReviewCase::STAGE_SPECIALIST && ! $reachedSpecialist;
            $state = $skipped ? 'skipped' : ($i < $currentIndex ? 'done' : ($i === $currentIndex ? 'current' : 'future'));
        @endphp
        <li aria-hidden="true" class="text-slate-300">&rarr;</li>
        <li @class([
            'rounded-full px-2 py-1 font-semibold',
            'bg-slate-900 text-white' => $state === 'current',
            'bg-emerald-100 text-emerald-900' => $state === 'done',
            'bg-slate-100 text-slate-500' => $state === 'future',
            'bg-slate-50 text-slate-300 line-through' => $state === 'skipped',
        ])>{{ \App\Models\Cases\ReviewCase::STAGE_LABELS[$stage] }}</li>
    @endforeach
</ol>
