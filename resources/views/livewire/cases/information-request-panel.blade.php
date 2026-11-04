<section class="rounded-xl border border-slate-200 bg-white p-4" aria-labelledby="requests">
    <div class="flex items-center justify-between">
        <h2 id="requests" class="font-bold">Requests and documents</h2>
        @if ($canAct && ! $composing && $case->stage !== 'closed')
            <button wire:click="startComposing" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Request documents</button>
        @endif
    </div>

    @if ($composing)
        <form wire:submit="send" class="mt-3 space-y-3 rounded-lg border border-slate-200 p-3">
            <p class="text-xs text-slate-500">Prefilled from what the open findings suggest asking for. Edit or remove any line, or add another.</p>
            @foreach ($draftItems as $i => $item)
                <div class="flex flex-wrap items-center gap-2">
                    <input wire:model="draftItems.{{ $i }}.document_type" type="text" placeholder="Document type, e.g. Services agreement"
                           class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    <input wire:model="draftItems.{{ $i }}.reason" type="text" placeholder="Why (optional)"
                           class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    <button type="button" wire:click="removeItem({{ $i }})" class="text-sm text-slate-500 hover:text-red-700">Remove</button>
                </div>
            @endforeach
            <button type="button" wire:click="addItem" class="text-sm text-slate-600 underline">Add another document</button>
            <label class="block text-sm font-semibold">
                Message to the taxpayer
                <textarea wire:model="message" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="What you're asking for and why"></textarea>
            </label>
            @error('send') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="flex gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Send request</button>
                <button type="button" wire:click="$set('composing', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Cancel</button>
            </div>
        </form>
    @endif

    @if ($case->informationRequests->isEmpty())
        <p class="mt-3 text-sm text-slate-500">No information requests sent yet.</p>
    @else
        <div class="mt-3 space-y-4">
            @foreach ($case->informationRequests as $request)
                <div class="rounded-lg border border-slate-200 p-3">
                    <p class="text-sm font-semibold">{{ $request->reference }} <span class="font-normal text-slate-500">sent by {{ $request->composer?->name }}, {{ $request->sent_at?->format('j M Y') }}</span></p>
                    @if ($request->message) <p class="mt-1 text-sm text-slate-700">{{ $request->message }}</p> @endif
                    <ul class="mt-2 space-y-1 text-sm">
                        @foreach ($request->items as $item)
                            <li class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $item->document_type }}</span>
                                @if ($item->finding) <span class="text-xs text-slate-500">for {{ $item->finding->pattern_code }}</span> @endif
                                @if ($item->releasedDocument)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-900">Arrived {{ $item->releasedDocument->received_at->format('j M Y') }}</span>
                                @elseif ($item->delivery)
                                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-900">Due {{ $item->delivery->due_at->format('j M Y') }}</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">Nothing on file for this type</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endif

    @if ($case->releasedDocuments->isNotEmpty())
        <div class="mt-4 space-y-2">
            <h3 class="text-xs font-semibold uppercase tracking-wide text-slate-500">Documents received</h3>
            @foreach ($case->releasedDocuments as $doc)
                <article class="rounded-lg border border-slate-200 p-3 text-sm">
                    <p class="font-semibold">{{ $doc->reference }} {{ $doc->title }} <span class="font-normal text-slate-500">· {{ $doc->document_type }}@if ($doc->finding) · relates to {{ $doc->finding->pattern_code }} @endif</span></p>
                    <p class="mt-1 text-slate-700">{{ $doc->content_summary }}</p>
                    <p class="mt-1 text-slate-500">{{ $doc->what_it_shows }}</p>
                </article>
            @endforeach
        </div>
    @endif

    @if ($canAct && $case->stage === 'waiting_taxpayer')
        <div class="mt-4">
            <button wire:click="decideNow" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Decide now, without waiting further</button>
        </div>
    @endif
</section>
