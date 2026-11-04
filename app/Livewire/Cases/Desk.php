<?php

namespace App\Livewire\Cases;

use App\Domain\Cases\DeskLanes;
use App\Domain\Cases\SignoffPolicy;
use App\Support\Roles;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** "Where should I look?" Each officer's desk: what needs attention, in the order work flows (officer-desk.md section 3). */
#[Layout('components.layouts.app')]
#[Title('My desk')]
class Desk extends Component
{
    public function mount(): void
    {
        Gate::authorize('view_worklist');
    }

    public function render(DeskLanes $deskLanes, SignoffPolicy $signoff)
    {
        $user = auth()->user();

        return view('livewire.cases.desk', [
            'lanes' => $deskLanes->for($user),
            'overview' => $user->hasRole(Roles::TEAM_LEAD) ? $deskLanes->teamOverview() : null,
            'signoffNote' => $user->hasRole(Roles::TEAM_LEAD) ? $signoff->demoNote() : null,
        ]);
    }
}
