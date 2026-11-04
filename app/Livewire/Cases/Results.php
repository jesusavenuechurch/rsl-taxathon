<?php

namespace App\Livewire\Cases;

use App\Domain\Cases\ResultsSummary;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/** A summary of work done, not a scoreboard of taxpayers flagged (officer-desk.md section 6). */
#[Layout('components.layouts.app')]
#[Title('Results')]
class Results extends Component
{
    public function mount(): void
    {
        Gate::authorize('view_worklist');
    }

    public function render(ResultsSummary $summary)
    {
        return view('livewire.cases.results', [
            'byOutcome' => $summary->byOutcome(),
            'exposureReferred' => $summary->exposureReferred(),
            'refunds' => $summary->refundsVerified(),
            'avgCycleDays' => $summary->averageDaysOpenToClose(),
            'byPattern' => $summary->byPattern(),
            'closedCases' => $summary->closedCases(),
            'findingsConcluded' => $summary->findingsConcluded(),
        ]);
    }
}
