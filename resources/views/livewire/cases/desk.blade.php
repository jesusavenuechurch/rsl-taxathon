<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">My desk</h1>
        <p class="mt-1 font-semibold text-slate-800">{{ auth()->user()->name }} · {{ auth()->user()->job_title ?? auth()->user()->getRoleNames()->map(fn ($r) => \App\Support\Roles::label($r))->implode(', ') }}@if (auth()->user()->team) · {{ auth()->user()->team->name }}@endif</p>
        <p class="mt-1 text-slate-600">What needs your attention, left to right in the order work flows. A case is a reason to review, never proof.</p>
        @if ($signoffNote)
            <p class="mt-2 text-sm text-slate-600">{{ $signoffNote }}</p>
        @endif
    </div>

    @if ($overview)
        <section class="overflow-x-auto rounded-xl border border-slate-200 bg-white p-4">
            <h2 class="font-bold">Team overview</h2>
            <table class="mt-3 min-w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-500">
                        <th class="pr-4 py-1">Team</th>
                        @foreach (\App\Models\Cases\ReviewCase::STAGE_LABELS as $label) <th class="px-3 py-1 text-right">{{ $label }}</th> @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($overview as $row)
                        <tr class="border-t border-slate-100">
                            <td class="pr-4 py-1.5 font-semibold">{{ $row['team']->name }}</td>
                            @foreach (\App\Models\Cases\ReviewCase::STAGE_LABELS as $stage => $label)
                                <td class="px-3 py-1.5 text-right tabular-nums text-slate-700">{{ $row['counts'][$stage] ?? 0 }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </section>
    @endif

    <div class="grid gap-4 lg:grid-cols-4">
        @foreach ($lanes as $lane)
            @php($items = $lane['reviewItems'] ?? $lane['cases'])
            <section class="space-y-3">
                <h2 class="flex items-center gap-2 text-sm font-bold uppercase tracking-wide text-slate-500">
                    {{ $lane['title'] }} <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs font-semibold text-slate-700">{{ $items->count() }}</span>
                </h2>
                @forelse ($items as $item)
                    @if (isset($lane['reviewItems']))
                        <article class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                            <p class="font-semibold">{{ $item->record_id }} <span class="font-normal text-slate-500">v{{ $item->record_version }}</span></p>
                            <p class="mt-1 text-sm text-slate-700">{{ $item->reason }}</p>
                            <p class="mt-2 text-xs text-slate-500">Flagged by {{ $item->outcome?->decider?->name }}@if ($item->finding?->taxpayer) · {{ $item->finding->taxpayer->name }} @endif</p>
                            <a href="{{ route('records.show', $item->record_id) }}" class="mt-3 inline-block rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Open record</a>
                        </article>
                    @else
                        <x-case-card :case="$item" />
                    @endif
                @empty
                    <p class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4 text-sm text-slate-500">Nothing waiting here.</p>
                @endforelse
            </section>
        @endforeach
    </div>
</div>
