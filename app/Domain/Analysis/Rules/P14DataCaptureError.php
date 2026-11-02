<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/** P14: a VAT figure far above the company's usual month: check the capture first. */
class P14DataCaptureError implements PatternRule
{
    use Support;

    private const LABELS = [
        'input_vat_local' => 'Local input VAT',
        'output_vat' => 'Output VAT',
        'standard_rated_sales' => 'Standard-rated sales',
    ];

    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        if ($s->vat->isEmpty()) {
            return RuleOutcome::notApplicable('No VAT returns on record.');
        }

        $multiple = (float) $p->get('multiple_of_median');
        $findings = [];

        foreach ($p->get('fields') as $column) {
            $median = self::median($s->vat->pluck($column)->all());
            if (! $median) {
                continue;
            }
            foreach ($s->vat as $row) {
                $value = $row->getAttribute($column);
                if ($value > $multiple * $median) {
                    $findings[] = new FindingDraft(
                        scope: "{$column}:{$row->period}",
                        values: [
                            'field_label' => self::LABELS[$column] ?? $column,
                            'period' => $row->period,
                            'value' => $value,
                            'multiple' => $value / $median,
                            'median' => $median,
                            'capture_channel' => $row->capture_channel,
                        ],
                        evidence: [$row->record_id],
                    );
                }
            }
        }

        return $findings
            ? RuleOutcome::fired($findings, count($findings).' VAT figure(s) far above the usual monthly level.')
            : RuleOutcome::notFired('No VAT figure above '.(int) $multiple.' times its usual monthly level.');
    }

    private static function median(array $values): float|int
    {
        sort($values);
        $n = count($values);
        $mid = intdiv($n, 2);

        return $n % 2 ? $values[$mid] : ($values[$mid - 1] + $values[$mid]) / 2;
    }
}
