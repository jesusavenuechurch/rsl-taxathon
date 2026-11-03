@props(['id'])
@php
    $href = match (true) {
        str_starts_with($id, 'F-') => \Illuminate\Support\Facades\Route::has('findings.show') ? route('findings.show', $id) : null,
        (bool) \App\Domain\Records\Channels::forRecordId($id) => route('records.show', $id),
        default => null,
    };
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->class('rounded border border-slate-300 px-1.5 font-mono text-xs hover:bg-slate-50') }}>{{ $id }}</a>
@else
    <span {{ $attributes->class('rounded border border-slate-200 bg-slate-50 px-1.5 font-mono text-xs text-slate-600') }}>{{ $id }}</span>
@endif
