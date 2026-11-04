<?php

namespace App\Livewire\Cases;

use App\Domain\Cases\CaseWorkflow;
use App\Domain\Requests\RequestWorkflow;
use App\Models\Cases\ReviewCase;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Livewire\Component;

/**
 * Requests and documents (officer-desk.md section 4.5): a prefilled, editable
 * draft request; pending items with their due date; arrived documents marked
 * with the finding they relate to.
 */
class InformationRequestPanel extends Component
{
    public ReviewCase $case;

    public bool $canAct = false;

    public array $draftItems = [];

    public string $message = '';

    public bool $composing = false;

    public function mount(ReviewCase $case, bool $canAct = false): void
    {
        $this->case = $case;
        $this->canAct = $canAct;
    }

    public function startComposing(RequestWorkflow $workflow): void
    {
        $this->draftItems = $workflow->prefill($this->case) ?: [['document_type' => '', 'finding_id' => null, 'reason' => '']];
        $this->composing = true;
    }

    public function addItem(): void
    {
        $this->draftItems[] = ['document_type' => '', 'finding_id' => null, 'reason' => ''];
    }

    public function removeItem(int $i): void
    {
        unset($this->draftItems[$i]);
        $this->draftItems = array_values($this->draftItems);
    }

    public function send(RequestWorkflow $workflow): void
    {
        try {
            $workflow->send(auth()->user(), $this->case, $this->draftItems, $this->message);
        } catch (AuthorizationException|InvalidArgumentException $e) {
            $this->addError('send', $e->getMessage());

            return;
        }

        $this->reset('draftItems', 'message', 'composing');
        $this->case->refresh();
    }

    public function decideNow(CaseWorkflow $workflow): void
    {
        try {
            $workflow->decideNow(auth()->user(), $this->case);
        } catch (AuthorizationException|InvalidArgumentException $e) {
            $this->addError('send', $e->getMessage());

            return;
        }
        $this->case->refresh();
    }

    public function render()
    {
        $this->case->load(['informationRequests.items.delivery', 'informationRequests.items.releasedDocument', 'informationRequests.items.finding', 'informationRequests.composer']);

        return view('livewire.cases.information-request-panel');
    }
}
