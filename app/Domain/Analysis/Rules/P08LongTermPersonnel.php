<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;
use App\Domain\Analysis\Wording\PyFormat;

/** P08: the same management fee every month for a year or more (possible seconded staff). */
class P08LongTermPersonnel implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $fees = $s->wht->where('nature_of_service', $p->get('fee_nature'))->sortBy('period')->values();
        $minimum = (int) $p->get('min_months');

        if ($fees->count() < $minimum) {
            return RuleOutcome::notFired("Management fees in {$fees->count()} month(s) on record (needs {$minimum}).", ['months' => $fees->count()]);
        }

        $last = $fees->slice(-$minimum)->values();
        $amounts = $last->pluck('gross_amount')->all();
        $mean = array_sum($amounts) / count($amounts);
        $spread = sqrt(array_sum(array_map(fn ($a) => ($a - $mean) ** 2, $amounts)) / count($amounts));
        $variation = $mean ? $spread / $mean : INF;
        $maximum = (float) $p->get('max_variation');
        $metrics = ['months' => $fees->count(), 'variation' => $variation];

        if ($variation >= $maximum) {
            return RuleOutcome::notFired('Monthly fees vary by '.PyFormat::percent($variation).' (fires under '.PyFormat::percent($maximum).').', $metrics);
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: $last->last()->payee_normalized_name,
            values: ['monthly_fee' => $mean, 'payee' => $fees->last()->payee_legal_name, 'months' => $fees->count()],
            evidence: self::ids($last),
            exposure: array_sum($amounts) * (float) $p->get('exposure_rate'),
        )], "Fee of {$this->m($mean)} paid in {$fees->count()} months with variation ".PyFormat::percent($variation).'.', $metrics);
    }
}
