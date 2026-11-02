<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P02: when P01 holds, related-party loans rise in every loss year. */
class P02WhoFundsLosses implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $lossYears = P01PersistentLosses::lossYearsWithCharges($s);

        if ($lossYears->count() < (int) $p->get('min_loss_years')) {
            return RuleOutcome::notApplicable('Only checked when persistent losses with group charges (P01) are found.');
        }

        $loans = $lossYears->pluck('related_party_loans_closing')->all();
        $rising = true;
        for ($i = 1; $i < count($loans); $i++) {
            $rising = $rising && $loans[$i] > $loans[$i - 1];
        }
        $metrics = ['loans' => $loans];

        if (! $rising) {
            return RuleOutcome::notFired('Related-party loans did not rise in every loss year.', $metrics);
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: $lossYears->last()->tax_year,
            values: ['loans_start' => $loans[0], 'loans_end' => end($loans)],
            evidence: self::ids($lossYears),
        )], 'Related-party loans rose in every loss year.', $metrics);
    }
}
