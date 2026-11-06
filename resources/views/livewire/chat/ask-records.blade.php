<section @class(['space-y-4', 'rounded-xl border border-sky-200 bg-sky-50/60 p-4' => $embedded]) aria-labelledby="ask-records">
    <div>
        @if ($embedded)
            <h2 id="ask-records" class="font-bold">Ask the records</h2>
        @else
            <h1 id="ask-records" class="text-2xl font-bold tracking-tight">Ask the records</h1>
        @endif
        <p class="mt-1 text-sm text-slate-600">Answers come only from the records RSL holds, and every statement cites them. It points you to what to look at; only an officer concludes.</p>
    </div>

    <form wire:submit="ask" class="flex flex-wrap gap-2">
        <input wire:model="question" type="text" maxlength="500" placeholder="Ask about a company, a pattern or an earlier decision"
            class="min-w-0 flex-1 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm" aria-label="Your question">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700" wire:loading.attr="disabled" wire:target="ask">Ask</button>
    </form>
    @error('question') <p class="text-xs text-red-700">{{ $message }}</p> @enderror

    <div class="flex flex-wrap gap-2">
        @foreach (\App\Livewire\Chat\AskRecords::SUGGESTED as $suggestion)
            <button wire:click="ask(@js($suggestion))" wire:loading.attr="disabled" wire:target="ask"
                class="rounded-full border border-slate-300 bg-white px-3 py-1 text-left text-xs text-slate-700 hover:bg-slate-50">{{ $suggestion }}</button>
        @endforeach
    </div>

    <p wire:loading wire:target="ask" class="text-sm text-slate-600">Looking in the records…</p>

    @foreach ($messages as $message)
        <article class="space-y-2 rounded-lg border border-slate-200 bg-white p-3 text-sm">
            <p class="font-semibold">{{ $message['question'] }}</p>
            @forelse ($message['statements'] as $statement)
                <p>
                    {{ $statement['text'] }}
                    @foreach ($statement['citations'] as $id) <x-citation-badge :id="$id" /> @endforeach
                </p>
            @empty
                <p class="text-slate-600">{{ \App\Domain\Chat\RecordsChat::NOTHING_FOUND }}</p>
            @endforelse
            @if ($message['note'])
                <p class="text-xs italic text-slate-500">{{ $message['note'] }}</p>
            @endif
            @if ($message['source'] === 'ai')
                <p class="text-xs text-slate-500">Worded by {{ $message['provider'] }}; every citation and figure checked against the records.</p>
            @elseif ($message['source'] === 'lookup')
                <p class="text-xs text-slate-500">Answered directly from the records.</p>
            @endif
        </article>
    @endforeach
</section>
