<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P04: related-party payments in the return that the WHT schedules do not cover. */
class P04WithholdingNotMatched implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $now = $s->citFor($s->context->reviewYear);
        if (! $now) {
            return RuleOutcome::notApplicable("No {$s->context->reviewYear} income tax return.");
        }

        $yearPayments = $s->wht->whereIn('period', $s->context->fiscalMonths);
        $coverage = (float) $p->get('min_coverage');
        $minAmount = (int) $p->get('min_amount');
        $findings = [];
        $lines = [];

        foreach ($p->get('natures') as $column => $nature) {
            $declared = (int) $now->getAttribute($column);
            $rows = $yearPayments->where('nature_of_service', $nature)->values();
            $withheldOn = $rows->sum('gross_amount');
            $lines[] = "{$nature}: {$this->m($declared)} declared, {$this->m($withheldOn)} on WHT schedules";

            if ($declared >= $minAmount && $withheldOn < $coverage * $declared) {
                $findings[] = new FindingDraft(
                    scope: "{$now->tax_year}:{$nature}",
                    values: ['nature' => $nature, 'declared' => $declared, 'withheld_on' => $withheldOn, 'tax_year' => $now->tax_year],
                    evidence: self::ids([$now], $rows),
                    exposure: ($declared - $withheldOn) * $p->rate('wht_standard'),
                );
            }
        }

        $explanation = implode('; ', $lines).'.';

        return $findings ? RuleOutcome::fired($findings, $explanation) : RuleOutcome::notFired($explanation);
    }
}
