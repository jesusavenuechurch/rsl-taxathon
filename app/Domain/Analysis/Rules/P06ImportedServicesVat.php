<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P06: services paid abroad (WHT schedules) with little or no reverse-charge VAT declared. */
class P06ImportedServicesVat implements PatternRule
{
    use Support;

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $months = $s->context->fiscalMonths;
        if (! $months) {
            return RuleOutcome::notApplicable('No review year to compare.');
        }

        $payments = $s->wht->whereIn('period', $months)->whereIn('nature_of_service', $p->get('service_natures'))->values();
        $vatRows = $s->vat->whereIn('period', $months)->values();
        $services = $payments->sum('gross_amount');
        $reverseCharge = $vatRows->sum('output_vat_imported_services');
        $vatRate = $p->rate('vat');
        $metrics = ['services' => $services, 'reverse_charge' => $reverseCharge];
        $explanation = "Services of {$this->m($services)} paid abroad in {$s->context->reviewYear}; reverse-charge VAT of {$this->m($reverseCharge)} declared.";

        if ($services < (int) $p->get('min_services') || $reverseCharge >= (float) $p->get('min_reverse_charge_share') * $services * $vatRate) {
            return RuleOutcome::notFired($explanation, $metrics);
        }

        return RuleOutcome::fired([new FindingDraft(
            scope: $s->context->reviewYear,
            values: [
                'services' => $services,
                'reverse_charge' => $reverseCharge,
                'input_vat_imported' => $vatRows->sum('input_vat_imported_services'),
            ],
            evidence: self::ids($payments, $vatRows),
            exposure: $services * $vatRate - $reverseCharge,
        )], $explanation, $metrics);
    }
}
