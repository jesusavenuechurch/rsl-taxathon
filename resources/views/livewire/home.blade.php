<div class="space-y-6">
    <section>
        <h1 class="text-2xl font-bold tracking-tight">Welcome, {{ $user->name }}</h1>
        <p class="mt-1 text-slate-600">
            {{ $user->job_title ?? $roles }}@if ($user->team) · {{ $user->team->name }}@endif
        </p>
    </section>

    <section class="rounded-xl border border-dashed border-slate-300 bg-white p-6 text-slate-600">
        Nothing to review yet. Worklists appear here once RSL records are imported and analysed.
    </section>

    @can('manage_demo_clock')
        <livewire:admin.demo-clock />
    @endcan
</div>
