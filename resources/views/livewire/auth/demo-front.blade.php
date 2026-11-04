<div class="mx-auto mt-8 max-w-3xl">
    <h1 class="text-2xl font-bold tracking-tight">RSL Case Review</h1>
    <p class="mt-2 text-sm text-slate-700">
        A prototype case workflow for Revenue Services Lesotho, built for the Taxathon. Every company, figure and document here is fictional, made for this demonstration.
        The system never concludes anything about a taxpayer — it shows the evidence; an officer always decides.
    </p>

    @if (request()->query('reset'))
        <p class="mt-3 rounded-lg border border-emerald-200 bg-emerald-50 p-2 text-sm text-emerald-900">Demo reset: every company received and analysed, clock back to 2 Nov 2026.</p>
    @endif
    @error('reset') <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 p-2 text-sm text-amber-900">{{ $message }}</p> @enderror

    <div class="mt-4 flex flex-wrap gap-2">
        <button wire:click="startTour" wire:loading.attr="disabled" class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700">Take the guided tour</button>
        <button wire:click="resetDemo" wire:loading.attr="disabled" wire:confirm="Reset the demo? This wipes everything and loads fresh mock data." class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold hover:bg-slate-50">Reset the demo</button>
    </div>

    <h2 class="mt-8 text-sm font-semibold uppercase tracking-wide text-slate-500">Enter as an officer</h2>
    <div class="mt-3 grid gap-3 sm:grid-cols-2">
        @foreach (\App\Livewire\Auth\DemoFront::CARDS as [$email, $name, $title, $question])
            <button wire:click="enterAs('{{ $email }}')" wire:loading.attr="disabled"
                    class="rounded-xl border border-slate-200 bg-white p-4 text-left shadow-sm hover:border-slate-400 hover:shadow">
                <p class="font-semibold">{{ $officers[$email]->name ?? $name }}</p>
                <p class="text-sm text-slate-600">{{ $title }}</p>
                <p class="mt-2 text-sm italic text-slate-500">"{{ $question }}"</p>
            </button>
        @endforeach
    </div>

    <p class="mt-8 text-sm text-slate-600">
        Administrator? <a href="{{ route('login') }}" class="underline hover:text-slate-900">Sign in with a password</a>.
    </p>
</div>
