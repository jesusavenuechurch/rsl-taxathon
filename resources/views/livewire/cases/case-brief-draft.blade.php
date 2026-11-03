@foreach ($brief->draft['statements'] ?? [] as $statement)
    <p>
        {{ $statement['text'] }}
        @foreach ($statement['citations'] as $id)
            <x-citation-badge :id="$id" />
        @endforeach
    </p>
@endforeach
@if ($next = $brief->draft['next_step'] ?? null)
    <p class="border-t border-slate-100 pt-2 font-semibold">
        Suggested next step: <span class="font-normal">{{ $next['text'] }}</span>
        @foreach ($next['citations'] as $id)
            <x-citation-badge :id="$id" />
        @endforeach
    </p>
@endif
