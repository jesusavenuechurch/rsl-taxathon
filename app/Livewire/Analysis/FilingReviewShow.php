<?php

namespace App\Livewire\Analysis;

use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** A filing review: every rule's result, including why the others did not fire. */
#[Layout('components.layouts.app')]
class FilingReviewShow extends Component
{
    public FilingReview $review;

    public function mount(string $reference): void
    {
        Gate::authorize('view_company_records');
        $this->review = FilingReview::where('reference', strtoupper($reference))->firstOrFail();
    }

    public function render()
    {
        $this->review->load(['checks.ruleVersion.rule', 'taxpayer']);

        return view('livewire.analysis.filing-review-show', [
            'r' => $this->review,
            'findings' => Finding::where('last_review_id', $this->review->id)->orWhere('first_review_id', $this->review->id)->get()->keyBy('pattern_code'),
        ])->title($this->review->reference);
    }
}
