<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;
use App\Domain\Analysis\Wording\PyFormat;

/** P11: large interest to group companies relative to the result before interest. */
class P11GroupLoanInterest implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $now = $s->citFor($s->context->reviewYear);
        if (! $now) {
            return RuleOutcome::notApplicable("No {$s->context->reviewYear} income tax return.");
        }

        $interest = $now->related_party_interest;
        $beforeInterest = $now->net_profit_before_tax + $now->related_party_interest + $now->bank_interest;
        $minInterest = (int) $p->get('min_interest');
        $minShare = (float) $p->get('min_share');
        $metrics = ['interest' => $interest, 'result_before_interest' => $beforeInterest];

        if ($interest < $minInterest) {
            $bank = $now->bank_interest > 0 ? " (bank interest of {$this->m($now->bank_interest)} is not a related-party charge)" : '';

            return RuleOutcome::notFired("Related-party interest of {$this->m($interest)} is below {$this->m($minInterest)}{$bank}.", $metrics);
        }
        if ($beforeInterest > 0 && $interest / $beforeInterest < $minShare) {
            return RuleOutcome::notFired('Related-party interest is '.PyFormat::percent($interest / $beforeInterest).' of the result before interest (fires at '.PyFormat::percent($minShare).').', $metrics);
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: $now->tax_year,
            values: ['interest' => $interest, 'result_before_interest' => $beforeInterest],
            evidence: self::ids([$now]),
            exposure: $interest * $p->rate('cit'),
        )], "Related-party interest of {$this->m($interest)} against a result before interest of {$this->m($beforeInterest)}.", $metrics);
    }
}
