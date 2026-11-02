<section class="rounded-xl border border-amber-200 bg-amber-50 p-6">
    <h2 class="font-bold">Demo clock</h2>
    @if ($clock->isSimulated())
        <p class="mt-1 text-sm text-amber-900">
            Today is <strong>{{ $clock->today()->format('l j F Y') }}</strong>
            ({{ $clock->offsetDays() }} day(s) after {{ \Carbon\CarbonImmutable::parse(config('rsl.today'))->format('j F Y') }}).
            Moving the clock is written to the audit log.
        </p>
        <form wire:submit="advance" class="mt-4 flex flex-wrap items-end gap-3">
            <label class="text-sm font-semibold">
                Advance by (days)
                <input wire:model="days" type="number" min="1" max="366"
                       class="mt-1 block w-28 rounded-lg border border-amber-300 bg-white px-3 py-2">
            </label>
            <button class="rounded-lg bg-amber-700 px-4 py-2 font-semibold text-white hover:bg-amber-800">Advance</button>
            <button type="button" wire:click="resetClock" wire:confirm="Reset the demo calendar to its start date?"
                    class="rounded-lg border border-amber-700 px-4 py-2 font-semibold text-amber-900 hover:bg-amber-100">Reset</button>
        </form>
        @error('days') <p class="mt-2 text-sm text-red-700">{{ $message }}</p> @enderror
    @else
        <p class="mt-1 text-sm text-amber-900">The clock follows real time (RSL_CLOCK=real).</p>
    @endif
</section>
