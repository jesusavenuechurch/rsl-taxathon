@php($groups = $finding->checklist->groupBy('type'))
<div class="mt-3 grid gap-4 sm:grid-cols-2">
    @foreach (['lawful_explanation' => 'Lawful explanations to weigh', 'must_establish' => 'What you must establish'] as $type => $heading)
        @if ($groups->has($type))
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $heading }}</p>
                <ul class="mt-1 space-y-2">
                    @foreach ($groups[$type] as $item)
                        <li class="rounded-lg border border-slate-200 p-2">
                            <label class="flex items-start gap-2 text-sm">
                                <input type="checkbox" wire:click="toggle({{ $item->id }})" @checked($item->status === 'resolved')
                                       @disabled(! $canAct) class="mt-0.5 rounded border-slate-300">
                                <span @class(['text-slate-500 line-through' => $item->status === 'resolved'])>{{ $item->text }}</span>
                            </label>
                            @if ($canAct)
                                <input wire:model.blur="notes.{{ $item->id }}" wire:change="saveNote({{ $item->id }})"
                                       type="text" placeholder="Add a note…"
                                       class="mt-1 block w-full rounded border-slate-200 px-2 py-1 text-xs">
                            @elseif ($item->note)
                                <p class="mt-1 text-xs text-slate-600">{{ $item->note }}</p>
                            @endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endforeach
</div>
