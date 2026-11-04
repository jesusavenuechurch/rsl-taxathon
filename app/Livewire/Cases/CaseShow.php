<?php

namespace App\Livewire\Cases;

use App\Domain\Audit\AuditLog;
use App\Domain\Cases\CaseWorkflow;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\MemoryCheck;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One case: its findings with evidence, memory, timeline, and the officer's next step. */
#[Layout('components.layouts.app')]
class CaseShow extends Component
{
    public ReviewCase $case;

    public string $referTo = '';

    public string $referReason = '';

    public string $noteText = '';

    public function mount(string $reference, AuditLog $audit): void
    {
        Gate::authorize('view_worklist');
        $this->case = ReviewCase::where('reference', strtoupper($reference))->firstOrFail();
        $audit->record('opened', subjectType: 'case', subjectRef: $this->case->reference, taxpayerTin: $this->case->tin);
    }

    public function take(CaseWorkflow $workflow): void
    {
        $workflow->take(auth()->user(), $this->case);
        $this->case->refresh();
    }

    public function refer(CaseWorkflow $workflow): void
    {
        $this->validate(['referTo' => 'required|exists:teams,code', 'referReason' => 'required|string|min:5|max:1000'],
            ['referReason.required' => 'Say why the case is being referred.', 'referTo.required' => 'Choose a team.']);

        try {
            $workflow->refer(auth()->user(), $this->case, Team::where('code', $this->referTo)->first(), $this->referReason);
        } catch (InvalidArgumentException $e) {
            $this->addError('referTo', $e->getMessage());

            return;
        }
        $this->reset('referTo', 'referReason');
        $this->case->refresh();
    }

    public function addNote(CaseWorkflow $workflow): void
    {
        $this->validate(['noteText' => 'required|string|max:2000']);
        $workflow->note(auth()->user(), $this->case, $this->noteText);
        $this->reset('noteText');
    }

    public function render(CaseWorkflow $workflow)
    {
        $this->case->load(['taxpayer', 'team', 'assignee', 'refundClaim', 'findings.evidence', 'findings.checklist', 'events.fromTeam', 'events.toTeam']);
        $latestReview = $this->case->findings->max('last_review_id');

        return view('livewire.cases.case-show', [
            'c' => $this->case,
            'canAct' => $workflow->canAct(auth()->user(), $this->case),
            'teams' => Team::where('id', '!=', $this->case->team_id)->orderBy('name')->get(),
            'memory' => $latestReview ? MemoryCheck::with('decision')->where('filing_review_id', $latestReview)->get() : collect(),
        ])->title("{$this->case->reference} {$this->case->taxpayer?->name}");
    }
}
