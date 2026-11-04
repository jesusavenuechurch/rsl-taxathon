<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Notifications sent</h1>
        <p class="mt-1 text-slate-600">Every WhatsApp message the system tried to send on a hand-off, and whether it actually went. No taxpayer name, TIN or amount is ever in one — see for yourself.</p>
    </div>

    @if ($deliveries->isEmpty())
        <p class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-slate-600">None sent yet.</p>
    @else
        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-left text-slate-500">
                        <th class="px-4 py-2">When</th>
                        <th class="px-4 py-2">Event</th>
                        <th class="px-4 py-2">To</th>
                        <th class="px-4 py-2">Case</th>
                        <th class="px-4 py-2">Message</th>
                        <th class="px-4 py-2">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($deliveries as $d)
                        <tr class="border-b border-slate-100 align-top">
                            <td class="whitespace-nowrap px-4 py-2 text-slate-500">{{ $d->created_at->format('j M Y H:i') }}</td>
                            <td class="px-4 py-2">{{ str_replace('_', ' ', $d->event) }}</td>
                            <td class="px-4 py-2">{{ $d->recipient->name }}</td>
                            <td class="px-4 py-2 font-mono text-xs">{{ $d->case_reference }}</td>
                            <td class="px-4 py-2">{{ $d->rendered_text }}</td>
                            <td class="px-4 py-2">
                                <span @class([
                                    'rounded-full px-2 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-900' => $d->status === 'sent',
                                    'bg-red-100 text-red-900' => $d->status === 'failed',
                                    'bg-slate-100 text-slate-600' => $d->status === 'skipped',
                                    'bg-amber-100 text-amber-900' => $d->status === 'queued',
                                ]) title="{{ $d->status_detail }}">{{ $d->status === 'skipped' ? 'not delivered (demo number)' : $d->status }}</span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
