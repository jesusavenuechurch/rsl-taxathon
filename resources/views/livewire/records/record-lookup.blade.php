<form wire:submit="open" class="rounded-xl border border-slate-200 bg-white p-6">
    <label for="record-id" class="block font-bold">Open a record</label>
    <p class="text-sm text-slate-600">Any ID from a finding or a source file, e.g. CIT-00036, WHT-00042, RF-0001.</p>
    <div class="mt-3 flex flex-wrap gap-2">
        <input wire:model="recordId" id="record-id" type="text" placeholder="CIT-00036"
               class="w-48 rounded-lg border border-slate-300 px-3 py-2 font-mono uppercase">
        <button class="rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700">Open</button>
    </div>
    @error('recordId') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
</form>
