<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/**
 * P13: records needed to understand related-party charges are missing.
 * An information gap is not suspicion (rule 5): it asks for records.
 */
class P13InformationGap implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $now = $s->citFor($s->context->reviewYear);
        if (! $now) {
            return RuleOutcome::notApplicable("No {$s->context->reviewYear} income tax return.");
        }

        $related = $s->relatedCharges($now);
        $gaps = [];
        if (! $now->financial_statements_attached && $related > 0) {
            $gaps[] = 'financial statements were not attached';
        }

        $pattern = '/'.$p->get('related_natures_pattern').'/';
        $unknown = $s->wht->groupBy('payee_legal_name')
            ->filter(fn ($rows, $payee) => ! $s->groupEntityFor($payee)
                && $rows->contains(fn ($r) => preg_match($pattern, $r->nature_of_service)))
            ->keys();
        if ($unknown->isNotEmpty()) {
            $gaps[] = 'the recipient '.$unknown->implode(', ').' is not in the group register';
        }

        if (! $gaps) {
            return RuleOutcome::notFired('Financial statements are attached where needed and every related payee is in the group register.');
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: $now->tax_year,
            values: ['related_total' => $related, 'gaps' => implode(' and ', $gaps)],
            evidence: self::ids([$now], $s->wht->whereIn('payee_legal_name', $unknown->all())),
        )], ucfirst(implode(' and ', $gaps)).'.');
    }
}
