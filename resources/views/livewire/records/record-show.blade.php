<div class="space-y-6">
    <div>
        <p class="text-sm font-semibold uppercase tracking-wide text-slate-500">{{ $channel->label }} · {{ $channel->rslSystem }}</p>
        <h1 class="mt-1 font-mono text-2xl font-bold">{{ $record->record_id }}</h1>
        @if ($record->taxpayer)
            <p class="mt-1 text-slate-700">{{ $record->taxpayer->name }} <span class="font-mono text-sm text-slate-500">{{ $record->taxpayer->tin }}</span></p>
        @elseif ($record->tin())
            <p class="mt-1 text-slate-700"><span class="font-mono">{{ $record->tin() }}</span> <span class="text-sm text-amber-800">(TIN not registered in eTax)</span></p>
        @endif
    </div>

    @unless ($record->isCurrent())
        <div class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="status">
            You are viewing version {{ $record->version }}, which was replaced on {{ $record->superseded_at->format('j M Y') }}.
            <a class="font-semibold underline" href="{{ route('records.show', $record->record_id) }}">See the current version</a>.
        </div>
    @endunless

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 font-bold">As received</h2>
        <dl class="divide-y divide-slate-100">
            @foreach ($channel->fields as $field)
                <div class="grid grid-cols-1 gap-1 px-4 py-2 sm:grid-cols-3">
                    <dt class="text-sm text-slate-600">{{ $field->label }}</dt>
                    <dd @class(['sm:col-span-2', 'font-mono tabular-nums' => in_array($field->type, ['money', 'integer']), 'text-right sm:text-left'])>
                        {{ \App\Support\Format::field($field, $record->getAttribute($field->attribute)) }}
                    </dd>
                </div>
            @endforeach
        </dl>
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-4 text-sm">
        <h2 class="font-bold">Provenance</h2>
        <p class="mt-1 text-slate-700">
            Received {{ $record->sourceImport->received_at->format('j M Y') }} in
            <span class="font-mono">{{ $record->sourceImport->filename }}</span> (import #{{ $record->sourceImport->id }}).
            Fingerprint <span class="font-mono text-xs break-all">{{ $record->row_hash }}</span>
        </p>
        @if ($versions->count() > 1)
            <h3 class="mt-4 font-semibold">Versions</h3>
            <ul class="mt-1 space-y-1">
                @foreach ($versions as $v)
                    <li>
                        <a class="underline" href="{{ route('records.show', ['recordId' => $v->record_id, 'v' => $v->version]) }}">Version {{ $v->version }}</a>
                        @if ($v->isCurrent()) (current) @else (replaced {{ $v->superseded_at->format('j M Y') }}) @endif
                        @if ($v->is($record)) · <strong>showing</strong> @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
