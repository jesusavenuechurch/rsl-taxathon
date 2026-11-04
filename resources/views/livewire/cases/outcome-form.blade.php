<div>
@if ($outcomeRow)
    <div class="mt-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm">
        <p class="font-semibold">{{ $outcomeRow->outcomeLabel() }} <span class="font-normal text-slate-500">by {{ $outcomeRow->decider->name }}, {{ $outcomeRow->decided_at->format('j M Y') }}</span></p>
        <dl class="mt-2 grid gap-x-4 gap-y-1 sm:grid-cols-[9rem_1fr]">
            <dt class="text-slate-500">Observed</dt><dd>{{ $outcomeRow->observed_pattern }}</dd>
            <dt class="text-slate-500">Conclusion</dt><dd>{{ $outcomeRow->conclusion }}</dd>
            <dt class="text-slate-500">Evidence</dt><dd>{{ $outcomeRow->evidence }}</dd>
            <dt class="text-slate-500">Conditions</dt><dd>{{ $outcomeRow->conditions }}</dd>
            <dt class="text-slate-500">Reassess at</dt><dd>{{ $outcomeRow->reassessment_point }}</dd>
        </dl>
        @if ($outcomeRow->requires_second_signoff)
            <p class="mt-2 @class(['font-semibold', 'text-amber-800' => $outcomeRow->signoff_status === 'pending', 'text-emerald-800' => $outcomeRow->signoff_status === 'approved', 'text-red-800' => $outcomeRow->signoff_status === 'returned'])">
                @switch($outcomeRow->signoff_status)
                    @case('pending') Waiting for a team lead's sign-off (est. {{ \App\Support\Format::money($outcomeRow->exposure_at_decision) }}). @break
                    @case('approved') Signed off by {{ $outcomeRow->secondSigner?->name }}, {{ $outcomeRow->signed_off_at->format('j M Y') }}. @break
                    @case('returned') Returned by {{ $outcomeRow->secondSigner?->name }}: {{ $outcomeRow->signoff_note }} @break
                @endswitch
            </p>
            @if ($outcomeRow->signoff_status === 'pending' && auth()->user()->can('second_signoff') && auth()->id() !== $outcomeRow->decided_by)
                <div class="mt-2 space-y-2 border-t border-slate-200 pt-2">
                    <input wire:model="signoffNote" type="text" placeholder="Note (required to return, optional to approve)" class="block w-full rounded-lg border border-slate-300 px-3 py-1.5 text-sm">
                    @error('submit') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
                    <div class="flex gap-2">
                        <button wire:click="approve" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Sign off</button>
                        <button wire:click="return" class="rounded-lg border border-red-300 px-3 py-1.5 text-sm font-semibold text-red-800 hover:bg-red-50">Return</button>
                    </div>
                </div>
            @endif
        @endif
    </div>
@elseif ($canAct && $finding->status === 'open')
    @if (! $open)
        <button wire:click="startForm" class="mt-3 rounded-lg border border-slate-300 px-3 py-1.5 text-sm font-semibold hover:bg-slate-50">Record an outcome</button>
    @else
        <form wire:submit="submit" class="mt-3 space-y-2 rounded-lg border border-slate-300 bg-white p-3">
            <label class="block text-sm font-semibold">
                Outcome
                <select wire:model="outcome" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Choose…</option>
                    @foreach ($labels as $value => $label) <option value="{{ $value }}">{{ $label }}</option> @endforeach
                </select>
            </label>
            <label class="block text-sm font-semibold">Observed pattern <textarea wire:model="observed_pattern" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></label>
            <label class="block text-sm font-semibold">Conclusion <textarea wire:model="conclusion" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Only you conclude; the system never does."></textarea></label>
            <label class="block text-sm font-semibold">Evidence <textarea wire:model="evidence" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></label>
            <label class="block text-sm font-semibold">Conditions this holds under <textarea wire:model="conditions" rows="2" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea></label>
            <label class="block text-sm font-semibold">When to reassess <input wire:model="reassessment_point" type="text" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>

            @if ($outcome === 'system_error')
                <div class="grid grid-cols-2 gap-2">
                    <label class="block text-sm font-semibold">Record that's wrong <input wire:model="record_id" type="text" placeholder="e.g. CIT-00036" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                    <label class="block text-sm font-semibold">Version <input wire:model="record_version" type="number" min="1" class="mt-1 block w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></label>
                </div>
                <p class="text-xs text-slate-500">Goes to the data team to correct as a new version of the row; this finding is withdrawn once they do.</p>
            @endif

            @error('submit') <p class="text-sm text-red-700">{{ $message }}</p> @enderror
            <div class="flex gap-2">
                <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Submit outcome</button>
                <button type="button" wire:click="$set('open', false)" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Cancel</button>
            </div>
        </form>
    @endif
@endif
</div>
