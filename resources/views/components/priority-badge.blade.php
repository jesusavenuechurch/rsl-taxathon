@props(['priority'])
<span {{ $attributes->class([
    'inline-block whitespace-nowrap rounded-full px-2 py-0.5 text-xs font-bold uppercase tracking-wide',
    'bg-red-100 text-red-900' => $priority === 'high',
    'bg-amber-100 text-amber-900' => $priority === 'medium',
    'bg-slate-100 text-slate-700' => $priority === 'low',
]) }}>{{ $priority }}</span>
