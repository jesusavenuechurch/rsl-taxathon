<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/**
 * P07 (green lane): recurring VAT credits, checked against Lekuka and customs.
 * Passing the checks makes a claim "lower risk", never "valid".
 */
class P07RecurringVatCredits implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $window = $s->context->recentVatPeriods;
        $recent = $s->vat->whereIn('period', $window)->values();
        if ($recent->isEmpty()) {
            return RuleOutcome::notApplicable('No recent VAT returns.');
        }

        $creditRows = $recent->filter(fn ($r) => $r->net_vat < 0);
        $credits = $creditRows->count();
        $minimum = (int) $p->get('min_credit_months');
        if ($credits < $minimum) {
            return RuleOutcome::notFired("VAT credits in {$credits} of the last ".count($window)." months (fires at {$minimum}).", ['credit_months' => $credits]);
        }

        $lekuka = $s->lekuka->values();
        $lekukaPeriods = $lekuka->pluck('period')->all();
        $customs = $s->customs->whereIn('period', $lekukaPeriods)->values();
        $problems = $this->problems($s, $p, $lekuka);
        $evidence = self::ids($recent, $lekuka, $customs);
        $values = ['credit_months' => $credits, 'window' => count($window)];

        if ($problems) {
            return RuleOutcome::fired([new FindingDraft(
                scope: end($window),
                values: [...$values, 'problems' => implode('; ', $problems)],
                evidence: $evidence,
                exposure: -$creditRows->sum('net_vat'),
                variant: 'needs_review',
            )], "{$credits} credit months; checks found: ".implode('; ', $problems).'.', ['credit_months' => $credits]);
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: end($window),
            values: $values,
            evidence: $evidence,
            variant: 'lower_risk',
        )], "{$credits} credit months; sales, purchases and import VAT match Lekuka and customs.", ['credit_months' => $credits]);
    }

    /** @return list<string> distinct problems, sorted */
    private function problems(TaxpayerSnapshot $s, RuleParameters $p, $lekuka): array
    {
        if ($lekuka->isEmpty()) {
            // Without e-invoicing data the checks cannot run; never call that lower risk.
            return ['no Lekuka e-invoicing data to check the claims against'];
        }

        $lekTolerance = (float) $p->get('lekuka_tolerance');
        $customsTolerance = (float) $p->get('customs_tolerance');
        $problems = [];

        foreach ($lekuka as $l) {
            $vat = $s->vatFor($l->period);
            if (! $vat) {
                $problems[] = 'no VAT return for a period invoiced in Lekuka';

                continue;
            }
            $customs = $s->customs->firstWhere('period', $l->period);

            if (self::differs($vat->input_vat_local, $l->vat_on_purchases, $lekTolerance)) {
                $problems[] = 'local input VAT does not match Lekuka purchases';
            }
            if (self::differs($vat->output_vat, $l->vat_on_sales, $lekTolerance)) {
                $problems[] = 'output VAT does not match Lekuka sales';
            }
            if ($customs && $customs->import_vat_paid && self::differs($vat->import_vat_per_return, $customs->import_vat_paid, $customsTolerance)) {
                $problems[] = 'import VAT does not match customs';
            }
            if ($vat->input_vat_imported_services > $vat->output_vat_imported_services * (float) $p->get('reverse_charge_factor') + (int) $p->get('reverse_charge_margin')) {
                $problems[] = 'input VAT claimed on imported services without matching reverse-charge output';
            }
        }

        $problems = array_values(array_unique($problems));
        sort($problems, SORT_STRING);

        return $problems;
    }

    private static function differs(int $declared, int $reference, float $tolerance): bool
    {
        if ($reference == 0) {
            return $declared != 0;
        }

        return abs($declared / $reference - 1) > $tolerance;
    }
}
