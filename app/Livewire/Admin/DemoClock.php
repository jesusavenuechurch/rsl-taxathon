<?php

namespace App\Livewire\Admin;

use App\Support\Clock;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

/** Lets an admin move the demo calendar, e.g. to show taxpayer documents arriving. */
class DemoClock extends Component
{
    #[Validate('required|integer|min:1|max:366')]
    public int $days = 7;

    public function advance(Clock $clock): void
    {
        Gate::authorize('manage_demo_clock');
        $this->validate();

        $clock->advance($this->days);
        $this->redirectRoute('home');
    }

    public function resetClock(Clock $clock): void
    {
        Gate::authorize('manage_demo_clock');

        $clock->reset();
        $this->redirectRoute('home');
    }

    public function render(Clock $clock)
    {
        return view('livewire.admin.demo-clock', ['clock' => $clock]);
    }
}
