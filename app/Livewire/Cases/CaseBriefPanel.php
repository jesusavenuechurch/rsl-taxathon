<?php

namespace App\Livewire\Cases;

use App\Domain\Briefs\BriefReview;
use App\Models\Cases\CaseBrief;
use App\Models\Cases\ReviewCase;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;
use Livewire\Component;

/**
 * The case page's AI brief: "drafting…" while the queued job runs, then the
 * cited draft with approve / edit / reject. The AI drafts; the officer
 * decides (checkpoint 5).
 */
class CaseBriefPanel extends Component
{
    public ReviewCase $case;

    public bool $canAct = false;

    public bool $editing = false;

    public string $editText = '';

    public string $rejectReason = '';

    public function mount(ReviewCase $case, bool $canAct = false): void
    {
        $this->case = $case;
        $this->canAct = $canAct;
    }

    public function startEdit(CaseBrief $brief): void
    {
        $this->editing = true;
        $this->editText = $this->renderedText($brief);
    }

    public function approve(BriefReview $review): void
    {
        $this->act(fn ($brief) => $review->approve(auth()->user(), $brief));
    }

    public function saveEdit(BriefReview $review): void
    {
        $this->validate(['editText' => 'required|string|min:5|max:4000']);
        $this->act(fn ($brief) => $review->edit(auth()->user(), $brief, $this->editText));
        $this->reset('editing', 'editText');
    }

    public function reject(BriefReview $review): void
    {
        $this->validate(['rejectReason' => 'required|string|min:5|max:1000'], ['rejectReason.required' => 'Say why the brief is being rejected.']);
        $this->act(fn ($brief) => $review->reject(auth()->user(), $brief, $this->rejectReason));
        $this->reset('rejectReason');
    }

    private function act(callable $action): void
    {
        $brief = $this->case->latestBrief();
        if (! $brief) {
            return;
        }

        try {
            $action($brief);
        } catch (AuthorizationException|InvalidArgumentException $e) {
            $this->addError('review', $e->getMessage());
        }
    }

    public function renderedText(CaseBrief $brief): string
    {
        $lines = array_map(fn ($s) => $s['text'], $brief->draft['statements'] ?? []);
        if ($next = $brief->draft['next_step']['text'] ?? null) {
            $lines[] = "Suggested next step: {$next}";
        }

        return implode("\n\n", $lines);
    }

    public function render()
    {
        return view('livewire.cases.case-brief-panel', ['brief' => $this->case->latestBrief()]);
    }
}
