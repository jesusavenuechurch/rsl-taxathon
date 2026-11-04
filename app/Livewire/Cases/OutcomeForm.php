<?php

namespace App\Livewire\Cases;

use App\Domain\Cases\OutcomeWorkflow;
use App\Domain\Cases\SignoffPolicy;
use App\Models\Analysis\Finding;
use App\Models\Analysis\OfficerOutcome;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Livewire\Component;

/**
 * One finding, one of four outcomes, five parts (officer-desk.md section
 * 4.6). The AI drafts the brief; only an officer ever fills this in.
 */
class OutcomeForm extends Component
{
    public Finding $finding;

    public bool $canAct = false;

    public bool $open = false;

    public string $outcome = '';

    public string $observed_pattern = '';

    public string $conclusion = '';

    public string $evidence = '';

    public string $conditions = '';

    public string $reassessment_point = '';

    public string $record_id = '';

    public string $record_version = '1';

    public string $signoffNote = '';

    public function mount(Finding $finding, bool $canAct = false): void
    {
        $this->finding = $finding;
        $this->canAct = $canAct;
    }

    public function startForm(): void
    {
        $this->open = true;
        $this->observed_pattern = $this->finding->wording;
    }

    public function submit(OutcomeWorkflow $workflow): void
    {
        try {
            $workflow->record(auth()->user(), $this->finding, [
                'outcome' => $this->outcome,
                'observed_pattern' => $this->observed_pattern,
                'conclusion' => $this->conclusion,
                'evidence' => $this->evidence,
                'conditions' => $this->conditions,
                'reassessment_point' => $this->reassessment_point,
                'record_id' => $this->record_id,
                'record_version' => $this->record_version,
            ]);
        } catch (AuthorizationException|InvalidArgumentException $e) {
            $this->addError('submit', $e->getMessage());

            return;
        }

        $this->finding->refresh();
        $this->open = false;
    }

    public function approve(OutcomeWorkflow $workflow): void
    {
        $this->decide($workflow, true);
    }

    public function return(OutcomeWorkflow $workflow): void
    {
        $this->decide($workflow, false);
    }

    private function decide(OutcomeWorkflow $workflow, bool $approve): void
    {
        $row = $this->finding->outcome()->first();
        if (! $row) {
            return;
        }

        try {
            $workflow->signOff(auth()->user(), $row, $approve, $this->signoffNote);
        } catch (AuthorizationException|InvalidArgumentException $e) {
            $this->addError('submit', $e->getMessage());

            return;
        }

        $this->reset('signoffNote');
        $this->finding->refresh();
    }

    public function render(SignoffPolicy $signoff)
    {
        $latest = $this->finding->outcome()->with(['decider', 'secondSigner'])->first();

        return view('livewire.cases.outcome-form', [
            'outcomeRow' => $this->finding->status === 'concluded' ? $latest : null,
            'interim' => $this->finding->status === 'open' && $latest?->outcome === OfficerOutcome::MORE_INFORMATION ? $latest : null,
            'labels' => OfficerOutcome::LABELS,
            'demoSignoffNote' => $signoff->demoNote(),
        ]);
    }
}
