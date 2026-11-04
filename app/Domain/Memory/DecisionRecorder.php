<?php

namespace App\Domain\Memory;

use App\Models\Analysis\Finding;
use App\Models\Analysis\OfficerOutcome;
use App\Models\Memory\Decision;
use App\Models\SourceRecords\CitReturn;
use App\Support\Clock;

/**
 * Writes a final officer outcome into company memory, five parts straight
 * across (handoff rule 7). Never called for system_error — the DB trigger on
 * decisions backs that up too (rule 9).
 */
class DecisionRecorder
{
    public function __construct(private Clock $clock) {}

    public function record(OfficerOutcome $outcome, Finding $finding): Decision
    {
        // Unlike other public references here, a decision's reference cannot be
        // backfilled after create() (the model forbids editing anything but
        // superseded_at once saved), so it is worked out up front. Imported
        // decisions' references are not tied to their auto-increment id (the
        // CSV names them), so the next number comes from the references
        // themselves, not from max(id).
        $decision = Decision::create([
            'reference' => $this->nextReference(),
            'tin' => $finding->tin,
            'tax_year_reviewed' => $this->taxYear($finding->tin),
            'pattern_code' => $finding->pattern_code,
            'pattern_label' => $finding->title,
            'outcome' => $outcome->outcome,
            'observed_pattern' => $outcome->observed_pattern,
            'conclusion' => $outcome->conclusion,
            'evidence' => $outcome->evidence,
            'conditions' => $outcome->conditions,
            'reassessment_point' => $outcome->reassessment_point,
            'officer' => $outcome->decider->name,
            'second_signoff' => $outcome->secondSigner?->name,
            'decided_on' => $this->clock->today(),
            'source' => 'officer_outcome',
            'team_code' => $finding->case?->team?->code,
        ]);

        return $decision;
    }

    private function nextReference(): string
    {
        $max = Decision::where('reference', 'like', 'DEC-%')->pluck('reference')
            ->map(fn ($r) => (int) substr($r, 4))->max() ?? 0;

        return sprintf('DEC-%04d', $max + 1);
    }

    private function taxYear(string $tin): string
    {
        if ($latest = CitReturn::current()->where('tin', $tin)->orderByDesc('tax_year')->value('tax_year')) {
            return $latest;
        }

        $today = $this->clock->today();
        $start = $today->month >= 4 ? $today->year : $today->year - 1;

        return sprintf('%d/%02d', $start, ($start + 1) % 100);
    }
}
