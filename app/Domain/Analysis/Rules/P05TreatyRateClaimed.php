<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P05: a reduced WHT rate applied to a payee the group register places in a non-treaty country. */
class P05TreatyRateClaimed implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $standard = $p->rate('wht_standard');
        $findings = [];
        $checked = 0;

        foreach ($s->wht->groupBy('payee_legal_name')->sortKeys() as $payee => $rows) {
            $entity = $s->groupEntityFor($payee);
            if (! $entity) {
                continue;
            }
            $checked++;
            $rate = $rows->sum('tax_withheld') / $rows->sum('gross_amount');

            if (! $entity->dta_with_lesotho && $rate < $standard) {
                $yearGross = $rows->whereIn('period', $s->context->fiscalMonths)->sum('gross_amount');
                $findings[] = new FindingDraft(
                    scope: $entity->record_id,
                    values: ['rate' => $rate, 'payee' => $payee, 'country' => $entity->country],
                    evidence: [...self::ids($rows), $entity->record_id],
                    exposure: $yearGross * ($standard - $rate),
                );
            }
        }

        if ($findings) {
            return RuleOutcome::fired($findings, count($findings).' payee(s) in a non-treaty country received a reduced rate.');
        }

        return RuleOutcome::notFired($checked
            ? "{$checked} group payee(s) checked; none in a non-treaty country at a reduced rate."
            : 'No payments to entities in the group register.');
    }
}
