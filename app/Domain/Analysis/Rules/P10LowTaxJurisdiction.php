<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P10: payments to a related entity the group register flags as low-tax. */
class P10LowTaxJurisdiction implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $findings = [];

        foreach ($s->wht->groupBy('payee_legal_name')->sortKeys() as $payee => $rows) {
            $entity = $s->groupEntityFor($payee);
            if (! $entity?->low_tax_jurisdiction) {
                continue;
            }
            $findings[] = new FindingDraft(
                scope: $entity->record_id,
                values: [
                    'total' => $rows->sum('gross_amount'),
                    'natures' => $rows->pluck('nature_of_service')->unique()->implode(', '),
                    'payee' => $payee,
                    'country' => $entity->country,
                ],
                evidence: [...self::ids($rows), $entity->record_id],
            );
        }

        return $findings
            ? RuleOutcome::fired($findings, count($findings).' related payee(s) in a low-tax jurisdiction.')
            : RuleOutcome::notFired('No payments to related entities flagged low-tax in the group register.');
    }
}
