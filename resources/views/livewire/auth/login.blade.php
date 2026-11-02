<div class="mx-auto mt-12 max-w-sm">
    <h1 class="text-2xl font-bold tracking-tight">RSL Case Review</h1>
    <p class="mt-1 text-sm text-slate-600">Sign in with your officer account.</p>

    <form wire:submit="login" class="mt-6 space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        <div>
            <label for="email" class="block text-sm font-semibold">Email</label>
            <input wire:model="email" id="email" type="email" autocomplete="username" autofocus
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
            @error('email') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="password" class="block text-sm font-semibold">Password</label>
            <input wire:model="password" id="password" type="password" autocomplete="current-password"
                   class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 focus:border-slate-500 focus:outline-none">
            @error('password') <p class="mt-1 text-sm text-red-700">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input wire:model="remember" type="checkbox" class="rounded border-slate-300"> Keep me signed in
        </label>
        <button type="submit" class="w-full rounded-lg bg-slate-900 px-4 py-2 font-semibold text-white hover:bg-slate-700"
                wire:loading.attr="disabled">Sign in</button>
    </form>
</div>
