@props(['checks', 'highlight' => null])
@if ($checks->isNotEmpty())
    <section {{ $attributes->class('rounded-xl border border-indigo-200 bg-indigo-50/60 p-4') }}>
        <h2 class="font-bold">Company memory</h2>
        <p class="text-sm text-slate-600">Earlier officer decisions about this company. Context only: memory never stops a finding being raised.</p>
        <div class="mt-3 space-y-3">
            @foreach ($checks as $check)
                @php($d = $check->decision)
                <article @class(['rounded-lg border bg-white p-3', 'border-indigo-400' => $highlight && $d->pattern_code === $highlight, 'border-slate-200' => ! ($highlight && $d->pattern_code === $highlight)])>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-mono text-sm font-semibold">{{ $d->reference }}</span>
                        <span class="text-sm text-slate-700">{{ $d->pattern_label }} · {{ $d->tax_year_reviewed }} · {{ $d->outcomeLabel() }}</span>
                        <span @class([
                            'ml-auto whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold',
                            'bg-amber-100 text-amber-900' => $check->result === 'outside_conditions',
                            'bg-emerald-100 text-emerald-900' => $check->result === 'within_conditions',
                            'bg-slate-100 text-slate-700' => $check->result === 'not_checkable',
                        ])>{{ ['outside_conditions' => 'Outside its conditions', 'within_conditions' => 'Conditions still hold', 'not_checkable' => 'Officer to judge'][$check->result] }}</span>
                    </div>
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($check->condition_results as $r)
                            <li class="flex gap-2">
                                <span aria-hidden="true">{{ ['met' => '✓', 'not_met' => '✗', 'not_checked' => '?', 'no_data' => '–'][$r['result']] }}</span>
                                <span>
                                    {{ $r['text'] }}
                                    @isset($r['actual_text'])
                                        <span class="text-slate-600">· now {{ $r['actual_text'] }}</span>
                                    @endisset
                                    @if ($r['result'] === 'not_checked') <span class="text-slate-500">· for the officer to judge</span> @endif
                                </span>
                            </li>
                        @endforeach
                    </ul>
                    <details class="mt-2 text-sm">
                        <summary class="cursor-pointer text-slate-600">The decision in full (five parts)</summary>
                        <dl class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[10rem_1fr]">
                            <dt class="text-slate-500">Observed</dt><dd>{{ $d->observed_pattern }}</dd>
                            <dt class="text-slate-500">Conclusion</dt><dd>{{ $d->outcomeLabel() }}: {{ $d->conclusion }}</dd>
                            <dt class="text-slate-500">Evidence</dt><dd>{{ $d->evidence }}</dd>
                            <dt class="text-slate-500">Conditions</dt><dd>{{ $d->conditions }}</dd>
                            <dt class="text-slate-500">Reassess at</dt><dd>{{ $d->reassessment_point }}</dd>
                            <dt class="text-slate-500">Decided</dt><dd>{{ $d->officer }}, {{ $d->decided_on->format('j M Y') }}{{ $d->second_signoff ? ' · second sign-off: '.$d->second_signoff : '' }}</dd>
                        </dl>
                    </details>
                </article>
            @endforeach
        </div>
    </section>
@endif
