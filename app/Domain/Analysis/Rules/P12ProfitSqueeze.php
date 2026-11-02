<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;
use App\Domain\Analysis\Wording\PyFormat;

/** P12: revenue up while profit falls to half or less of a profitable prior year. */
class P12ProfitSqueeze implements PatternRule
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
        $ratio = (float) $p->get('max_profit_ratio');
        $metrics = ['revenue_growth' => $revenueGrowth, 'profit_prior' => $pre->net_profit_before_tax, 'profit_now' => $now->net_profit_before_tax];
        $summary = 'Revenue '.($revenueGrowth >= 0 ? 'rose ' : 'fell ').PyFormat::percent(abs($revenueGrowth))
            ."; net profit went from {$this->m($pre->net_profit_before_tax)} to {$this->m($now->net_profit_before_tax)}";

        if (! ($revenueGrowth > 0 && $pre->net_profit_before_tax > 0 && $now->net_profit_before_tax <= $ratio * $pre->net_profit_before_tax)) {
            return RuleOutcome::notFired("{$summary} (fires when revenue rises and profit falls to ".PyFormat::percent($ratio).' or less of a profitable prior year).', $metrics);
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: $now->tax_year,
            values: [
                'revenue_growth' => $revenueGrowth,
                'profit_prior' => $pre->net_profit_before_tax,
                'profit_now' => $now->net_profit_before_tax,
            ],
            evidence: self::ids([$pre, $now]),
        )], "{$summary}.", $metrics);
    }
}
