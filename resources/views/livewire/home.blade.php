<div class="space-y-6">
    <section>
        <h1 class="text-2xl font-bold tracking-tight">Welcome, {{ $user->name }}</h1>
        <p class="mt-1 text-slate-600">
            {{ $user->job_title ?? $roles }}@if ($user->team) · {{ $user->team->name }}@endif
        </p>
    </section>

    @can('view_company_records')
        <a href="{{ route('findings.index') }}" class="block rounded-xl border border-slate-200 bg-white p-6 hover:border-slate-400">
            <span class="font-bold">Findings</span>
            <span class="block text-slate-600">
                {{ $openFindings }} open finding(s) across {{ $companiesWithFindings }} compan{{ $companiesWithFindings === 1 ? 'y' : 'ies' }}. Worklists and cases come next.
            </span>
        </a>
    @endcan

    @can('view_company_records')
        <livewire:records.record-lookup />

        <section class="overflow-hidden rounded-xl border border-slate-200 bg-white">
            <h2 class="border-b border-slate-200 px-4 py-3 font-bold">RSL records received</h2>
            @if ($channels->sum('count') === 0)
                <p class="px-4 py-3 text-slate-600">Nothing received yet. Run <code class="font-mono">php artisan rsl:import --all</code>.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-slate-600">
                            <tr><th class="px-4 py-2">Channel</th><th class="px-4 py-2">RSL system</th><th class="px-4 py-2 text-right">Records</th><th class="px-4 py-2">Last received</th></tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($channels as $row)
                                <tr>
                                    <td class="px-4 py-2">{{ $row['channel']->label }} <span class="font-mono text-xs text-slate-500">{{ $row['channel']->prefix }}-</span></td>
                                    <td class="px-4 py-2 text-slate-600">{{ $row['channel']->rslSystem }}</td>
                                    <td class="px-4 py-2 text-right tabular-nums">{{ number_format($row['count']) }}</td>
                                    <td class="px-4 py-2 text-slate-600">
                                        {{ $row['last']?->received_at->format('j M Y') ?? '–' }}
                                        @if ($row['last'] && $row['last']->warnings)
                                            <span class="ml-1 text-amber-800" title="{{ implode(' ', $row['last']->warnings) }}">⚠ {{ count($row['last']->warnings) }} warning(s)</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endcan

    @can('manage_demo_clock')
        <livewire:admin.demo-clock />
    @endcan
</div>
