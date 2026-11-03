<?php

namespace App\Domain\Briefs;

use App\Domain\Records\Channels;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\MemoryCheck;
use App\Support\Format;
use Illuminate\Support\Collection;

/**
 * Every angle on a case, as a list of record IDs the system already looked
 * up: findings (with their lawful explanations and what must be established),
 * the evidence behind them, company memory, and the refund letter. The AI
 * prompt and the citation guard are both built only from this (checkpoint 5:
 * grounding).
 */
class FactSheetBuilder
{
    public function build(ReviewCase $case): FactSheet
    {
        $case->loadMissing(['taxpayer', 'findings.evidence', 'findings.checklist', 'refundClaim']);

        $items = collect();

        if ($case->taxpayer) {
            $items->push(new FactSheetItem(
                $case->taxpayer->record_id,
                'Company',
                "{$case->taxpayer->name}, {$case->taxpayer->sector} sector, TIN {$case->taxpayer->tin}.",
            ));
        }

        foreach ($case->findings as $finding) {
            $items->push($this->findingItem($finding));
            foreach ($finding->evidence as $evidence) {
                $items->push($this->evidenceItem($evidence->record_id, $finding->reference, $finding->pattern_code));
            }
        }

        foreach ($this->memoryChecks($case) as $check) {
            $items->push($this->memoryItem($check));
        }

        if ($case->refundClaim) {
            $items->push($this->refundClaimItem($case));
        }

        // The same record can back more than one finding; keep the first, grounded description.
        return new FactSheet($items->unique('id')->values());
    }

    private function findingItem($finding): FactSheetItem
    {
        $checklist = $finding->checklist->groupBy('type');
        $lawful = $checklist->get('lawful_explanation', collect())->pluck('text')->implode('; ');
        $establish = $checklist->get('must_establish', collect())->pluck('text')->implode('; ');

        $text = $finding->wording
            .($lawful !== '' ? " Lawful explanations to weigh: {$lawful}." : '')
            .($establish !== '' ? " What the officer must establish: {$establish}." : '')
            .' Estimated exposure: '.Format::money($finding->exposure_estimate).' (estimated, not assessed).'
            .' Status: '.$finding->status.'.';

        return new FactSheetItem($finding->reference, "Finding {$finding->pattern_code} {$finding->title}", $text, $finding->evidence_level);
    }

    private function evidenceItem(string $recordId, string $findingRef, string $patternCode): FactSheetItem
    {
        $channel = Channels::forRecordId($recordId);
        $label = $channel?->label ?? 'Record';

        return new FactSheetItem($recordId, $label, "Cited as evidence for {$findingRef} ({$patternCode}).");
    }

    /** @return Collection<int, MemoryCheck> */
    private function memoryChecks(ReviewCase $case): Collection
    {
        $reviewIds = $case->findings->pluck('last_review_id')->filter()->unique();

        if ($reviewIds->isEmpty()) {
            return collect();
        }

        return MemoryCheck::with('decision')->whereIn('filing_review_id', $reviewIds)->get()->unique('decision_id');
    }

    private function memoryItem(MemoryCheck $check): FactSheetItem
    {
        $d = $check->decision;
        $resultLabel = ['within_conditions' => 'still within its conditions', 'outside_conditions' => 'outside its conditions', 'not_checkable' => 'left for the officer to judge'][$check->result] ?? $check->result;

        $text = "{$d->decided_on->format('Y')}: {$d->outcomeLabel()}. {$d->conclusion} Holds under: {$d->conditions}. Now {$resultLabel}: {$check->statement}";

        return new FactSheetItem($d->reference, "Company memory {$d->pattern_label}", $text);
    }

    private function refundClaimItem(ReviewCase $case): FactSheetItem
    {
        $rf = $case->refundClaim;

        $text = Format::money($rf->amount_claimed)." claimed for {$rf->vat_periods}, letter dated {$rf->letter_date->format('j M Y')}, ".
            "verification due {$rf->verification_due->format('j M Y')}, status {$rf->status}.".
            ($case->refund_lane ? ' Lane: '.$case->laneLabel().'.' : '');

        return new FactSheetItem($rf->record_id, 'Refund letter', $text);
    }
}
