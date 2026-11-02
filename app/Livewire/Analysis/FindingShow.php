<?php

namespace App\Livewire\Analysis;

use App\Domain\Audit\AuditLog;
use App\Domain\Records\Channels;
use App\Models\Analysis\Finding;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/** One finding with the five things rule 4 requires every finding to show. */
#[Layout('components.layouts.app')]
class FindingShow extends Component
{
    public Finding $finding;

    public function mount(string $reference, AuditLog $audit): void
    {
        Gate::authorize('view_company_records');

        $this->finding = Finding::where('reference', strtoupper($reference))->firstOrFail();
        $audit->record('opened', subjectType: 'finding', subjectRef: $this->finding->reference, taxpayerTin: $this->finding->tin);
    }

    public function render()
    {
        $this->finding->load(['evidence', 'checklist', 'ruleVersion.rule', 'taxpayer', 'firstReview']);

        return view('livewire.analysis.finding-show', [
            'f' => $this->finding,
            'channels' => Channels::all(),
        ])->title("{$this->finding->reference} {$this->finding->title}");
    }
}
