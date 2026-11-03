<section class="rounded-xl border border-violet-200 bg-violet-50/60 p-4" aria-labelledby="ai-brief">
    <div class="flex items-center justify-between">
        <h2 id="ai-brief" class="font-bold">AI case brief</h2>
        @if ($brief)
            <span class="text-xs text-slate-500">{{ $brief->reference }} · {{ $brief->generated_at->format('j M Y H:i') }}</span>
        @endif
    </div>

    @if (! $brief)
        <p wire:poll.3s class="mt-2 text-sm text-slate-600">Drafting the AI case brief…</p>
    @else
        <p class="mt-1 text-xs text-slate-600">
            The AI consolidates the findings, company memory and refund letter into a cited draft; it never concludes — only an officer does.
            @if ($brief->source === 'template')
                This one is the deterministic fallback{{ $brief->rejection_reason ? ' ('.$brief->rejection_reason.')' : ' (the AI model was unavailable)' }}, built the same way, with no model call.
            @else
                Drafted by {{ $brief->provider }}.
            @endif
        </p>

        @if ($brief->status === 'rejected')
            <div class="mt-3 rounded-lg border border-red-200 bg-red-50 p-3 text-sm">
                <p class="font-semibold text-red-900">Rejected by {{ $brief->reviewer?->name }}, {{ $brief->reviewed_at->format('j M Y') }}</p>
                <p class="mt-1 text-red-800">{{ $brief->review_note }}</p>
            </div>
        @elseif ($brief->status === 'edited' && ! $editing)
            <div class="mt-3 rounded-lg border border-slate-200 bg-white p-3 text-sm">
                <p class="whitespace-pre-line">{{ $brief->final_text }}</p>
                <p class="mt-2 text-xs text-slate-500">Edited by {{ $brief->reviewer?->name }}, {{ $brief->reviewed_at->format('j M Y') }}.</p>
                <details class="mt-2 text-xs text-slate-500"><summary class="cursor-pointer">See the AI's original draft</summary>
                    <div class="mt-2 space-y-2">@include('livewire.cases.case-brief-draft', ['brief' => $brief])</div>
                </details>
            </div>
        @else
            <div class="mt-3 space-y-2 rounded-lg border border-slate-200 bg-white p-3 text-sm">
                @include('livewire.cases.case-brief-draft', ['brief' => $brief])
            </div>

            @if ($brief->status === 'approved')
                <p class="mt-2 text-xs text-slate-500">Approved by {{ $brief->reviewer?->name }}, {{ $brief->reviewed_at->format('j M Y') }}.</p>
            @elseif ($canAct && ! $editing)
                <div class="mt-3 flex flex-wrap items-start gap-2">
                    <button wire:click="approve" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Approve</button>
                    <button wire:click="startEdit" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Edit</button>
                    <form wire:submit="reject" class="flex flex-1 flex-wrap items-end gap-2">
                        <input wire:model="rejectReason" type="text" placeholder="Reject: say why" class="min-w-0 flex-1 rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                        <button class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-800 hover:bg-red-50">Reject</button>
                    </form>
                </div>
                @error('rejectReason') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
                @error('review') <p class="mt-1 text-xs text-red-700">{{ $message }}</p> @enderror
            @endif

            @if ($canAct && $editing)
                <form wire:submit="saveEdit" class="mt-3 space-y-2">
                    <textarea wire:model="editText" rows="6" class="block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                    @error('editText') <p class="text-xs text-red-700">{{ $message }}</p> @enderror
                    <div class="flex gap-2">
                        <button class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Save as edited</button>
                        <button type="button" wire:click="$set('editing', false)" class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Cancel</button>
                    </div>
                </form>
            @endif
        @endif
    @endif
</section>
