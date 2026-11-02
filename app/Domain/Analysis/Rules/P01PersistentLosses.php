<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;
use App\Models\SourceRecords\CitReturn;
use Illuminate\Support\Collection;

/** P01: consecutive loss years (up to the latest) with related-party charges in each. */
class P01PersistentLosses implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        if ($s->cit->isEmpty()) {
            return RuleOutcome::notApplicable('No income tax returns on record.');
        }

        $lossYears = self::lossYearsWithCharges($s);
        $plainLosses = self::trailing($s->cit, fn (CitReturn $y) => $y->net_profit_before_tax < 0)->count();
        $minimum = (int) $p->get('min_loss_years');
        $metrics = ['loss_years_with_charges' => $lossYears->count(), 'loss_years' => $plainLosses];

        if ($lossYears->count() < $minimum) {
            return RuleOutcome::notFired(
                "{$plainLosses} consecutive loss year(s) up to {$s->cit->last()->tax_year}, {$lossYears->count()} of them with related-party charges (needs {$minimum}).",
                $metrics,
            );
        }

        $total = $lossYears->sum(fn (CitReturn $y) => $s->relatedCharges($y));

        return RuleOutcome::fired([new FindingDraft(
            scope: $lossYears->last()->tax_year,
            values: [
                'years' => $lossYears->count(),
                'first_year' => $lossYears->first()->tax_year,
                'last_year' => $lossYears->last()->tax_year,
                'related_total' => $total,
            ],
            evidence: self::ids($lossYears),
        )], "{$lossYears->count()} consecutive loss years with related-party charges.", $metrics);
    }

    /** The unbroken run of loss years with group charges ending at the latest return, oldest first. */
    public static function lossYearsWithCharges(TaxpayerSnapshot $s): Collection
    {
        return self::trailing($s->cit, fn (CitReturn $y) => $y->net_profit_before_tax < 0 && $s->relatedCharges($y) > 0);
    }

    private static function trailing(Collection $years, callable $test): Collection
    {
        $run = collect();
        foreach ($years->reverse() as $year) {
            if (! $test($year)) {
                break;
            }
            $run->prepend($year);
        }

        return $run->values();
    }
}
