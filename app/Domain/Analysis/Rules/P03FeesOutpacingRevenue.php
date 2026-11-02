<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;
use App\Domain\Analysis\Wording\PyFormat;

/** P03: related-party fees (management + royalties) grew much faster than revenue. */
class P03FeesOutpacingRevenue implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $now = $s->citFor($s->context->reviewYear);
        $pre = $s->citFor($s->context->priorYear);
        if (! $now || ! $pre || $pre->revenue == 0) {
            return RuleOutcome::notApplicable("Needs income tax returns for {$s->context->priorYear} and {$s->context->reviewYear}.");
        }

        $revenueGrowth = $now->revenue / $pre->revenue - 1;
        $feesNow = $now->related_party_management_fees + $now->related_party_royalties;
        $feesPre = $pre->related_party_management_fees + $pre->related_party_royalties;
        $minFees = (int) $p->get('min_fees');
        $minGap = (float) $p->get('min_gap');

        if ($feesPre <= 0) {
            return RuleOutcome::notFired("No related-party fees in {$pre->tax_year} to compare with.", ['fees_now' => $feesNow]);
        }

        $feeGrowth = $feesNow / $feesPre - 1;
        $gap = $feeGrowth - $revenueGrowth;
        $metrics = ['fee_growth' => $feeGrowth, 'revenue_growth' => $revenueGrowth, 'gap' => $gap, 'fees_now' => $feesNow, 'fees_prior' => $feesPre];
        $summary = 'Fees '.self::signedPct($feeGrowth).' against revenue '.self::signedPct($revenueGrowth)
            .': gap '.PyFormat::fixed0($gap * 100).' points (fires at '.PyFormat::fixed0($minGap * 100).')';

        if ($feesNow < $minFees || $gap < $minGap) {
            $why = $feesNow < $minFees ? "fees of {$this->m($feesNow)} are below {$this->m($minFees)}" : 'gap below the threshold';

            return RuleOutcome::notFired("{$summary}; {$why}.", $metrics);
        }

        $excess = $feesNow - $feesPre * (1 + $revenueGrowth);

        return RuleOutcome::fired([new FindingDraft(
            scope: $now->tax_year,
            values: ['fee_growth' => $feeGrowth, 'revenue_growth' => $revenueGrowth],
            evidence: self::ids([$pre, $now]),
            exposure: $excess * $p->rate('cit'),
        )], "{$summary}.", $metrics);
    }

    private static function signedPct(float $x): string
    {
        return ($x >= 0 ? '+' : '').PyFormat::percent($x);
    }
}
