<div>
@if ($current)
    <aside class="fixed bottom-4 right-4 z-50 w-80 rounded-xl border border-slate-300 bg-white p-4 shadow-xl">
        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Guided tour · Step {{ $step }} of {{ $total }}</p>
        <h2 class="mt-1 font-bold">{{ $current['title'] }}</h2>
        <p class="mt-1 text-sm text-slate-700">{{ $current['body'] }}</p>
        <div class="mt-3 flex items-center gap-2">
            @if ($step < $total)
                <button wire:click="next" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Next</button>
            @else
                <button wire:click="dismiss" class="rounded-lg bg-slate-900 px-3 py-1.5 text-sm font-semibold text-white hover:bg-slate-700">Finish</button>
            @endif
            <button wire:click="dismiss" class="text-sm text-slate-500 underline hover:text-slate-900">Skip tour</button>
        </div>
    </aside>
@endif
</div>
