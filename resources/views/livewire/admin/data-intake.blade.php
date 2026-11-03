<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Data intake</h1>
        <p class="mt-1 text-slate-600">Records enter here. Everything after (analysis, cases, notifications) happens on its own. In a pilot, RSL's systems feed this automatically.</p>
    </div>

    @if ($result)
        <div class="rounded-xl border border-emerald-300 bg-emerald-50 p-4" role="status">
            <p class="font-bold">{{ $result['title'] }}</p>
            @foreach ($result['lines'] as $line) <p class="text-sm">{{ $line }}</p> @endforeach
            @if ($result['case'])
                <p class="mt-2 text-sm">
                    <a class="font-semibold underline" href="{{ route('cases.show', $result['case']['reference']) }}">{{ $result['case']['reference'] }}</a>
                    is on the {{ $result['case']['team'] }} worklist.
                </p>
            @endif
        </div>
    @endif
    @if ($error)
        <div class="rounded-xl border border-red-300 bg-red-50 p-4 text-sm text-red-900" role="alert">{{ $error }}</div>
    @endif

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="font-bold">Simulate a company's records arriving</h2>
        <p class="text-sm text-slate-600">Its returns, withholding tax schedules, Lekuka, customs, PAYE and refund letters arrive from the mock data, as if from RSL's systems.</p>
        <form wire:submit="receiveCompany" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm font-semibold">
                Company
                <select wire:model="tin" class="mt-1 block w-80 max-w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="">Choose…</option>
                    @foreach ($companies as $tin => $name) <option value="{{ $tin }}">{{ $name }}</option> @endforeach
                </select>
            </label>
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="receiveCompany">Receive records</span>
                <span wire:loading wire:target="receiveCompany">Receiving and analysing…</span>
            </button>
            <button type="button" wire:click="receiveEverything" class="rounded-lg border border-slate-300 px-4 py-2 font-semibold hover:bg-slate-50">Receive every company</button>
        </form>
        @error('tin') <p class="mt-2 text-sm text-red-700">Choose a company.</p> @enderror
    </section>

    <section class="rounded-xl border border-slate-200 bg-white p-6">
        <h2 class="font-bold">Receive a channel file</h2>
        <p class="text-sm text-slate-600">A CSV in the channel's layout. A file with any invalid row is rejected whole, with the reason.</p>
        <form wire:submit="upload" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm font-semibold">
                Channel
                <select wire:model="channel" class="mt-1 block w-64 max-w-full rounded-lg border border-slate-300 px-3 py-2">
                    <option value="">Choose…</option>
                    @foreach ($channels as $c) <option value="{{ $c->key }}">{{ $c->label }}</option> @endforeach
                </select>
            </label>
            <label class="text-sm font-semibold">
                File
                <input wire:model="file" type="file" accept=".csv,text/csv" class="mt-1 block max-w-full text-sm">
            </label>
            <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700" wire:loading.attr="disabled">Receive file</button>
        </form>
        @error('channel') <p class="mt-2 text-sm text-red-700">Choose a channel.</p> @enderror
        @error('file') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
    </section>

    <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
        <h2 class="border-b border-slate-200 px-4 py-3 font-bold">Recently received</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-slate-600">
                    <tr><th class="px-4 py-2">File</th><th class="px-4 py-2 text-right">Rows</th><th class="px-4 py-2">Received</th></tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($imports as $i)
                        <tr>
                            <td class="px-4 py-2 font-mono">{{ $i->filename }}</td>
                            <td class="px-4 py-2 text-right tabular-nums">{{ $i->rows_new }} new / {{ $i->rows_total }}</td>
                            <td class="px-4 py-2 text-slate-600">{{ $i->received_at->format('j M Y H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-4 py-3 text-slate-600">Nothing received yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
