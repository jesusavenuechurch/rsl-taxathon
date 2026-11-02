@props(['finding'])
@php
    [$label, $class] = match ($finding->kind) {
        'green_lane' => ['Lower risk', 'bg-emerald-100 text-emerald-900'],
        'information_gap' => ['Information gap', 'bg-sky-100 text-sky-900'],
        'data_quality' => ['Data check', 'bg-slate-200 text-slate-800'],
        default => ['For review', 'bg-amber-100 text-amber-900'],
    };
@endphp
<span {{ $attributes->class(['inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-semibold', $class]) }}>{{ $label }}</span>
