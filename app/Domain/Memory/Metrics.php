<?php

namespace App\Domain\Memory;

use App\Domain\Analysis\TaxpayerSnapshot;
use App\Domain\Analysis\Wording\PyFormat;
use InvalidArgumentException;

/**
 * Figures a decision's conditions can be checked against, measured on the
 * year under review. Adding a metric here makes it available to conditions.
 */
final class Metrics
{
    /** @var array<string, array{label: string, format: string}> */
    public const DEFINITIONS = [
        'related_party_fees' => ['label' => 'Related-party fees (management fees and royalties)', 'format' => 'money'],
        'depreciation' => ['label' => 'Depreciation', 'format' => 'money'],
        'bank_interest' => ['label' => 'Bank interest', 'format' => 'money'],
        'fees_to_revenue' => ['label' => 'Related-party fees as a share of revenue', 'format' => 'percent1'],
        'zero_rated_share' => ['label' => 'Zero-rated (export) share of sales', 'format' => 'percent'],
    ];

    /** The metric's value for this taxpayer, or null when the records needed are missing. */
    public static function value(string $metric, TaxpayerSnapshot $s): ?float
    {
        $year = $s->citFor($s->context->reviewYear);
        $fees = $year ? $year->related_party_management_fees + $year->related_party_royalties : null;

        return match ($metric) {
            'related_party_fees' => $fees,
            'depreciation' => $year?->depreciation,
            'bank_interest' => $year?->bank_interest,
            'fees_to_revenue' => $year && $year->revenue ? $fees / $year->revenue : null,
            'zero_rated_share' => self::zeroRatedShare($s),
            default => throw new InvalidArgumentException("Unknown metric '{$metric}'."),
        };
    }

    public static function label(string $metric): string
    {
        return self::DEFINITIONS[$metric]['label'] ?? $metric;
    }

    public static function format(string $metric, float $value): string
    {
        return match (self::DEFINITIONS[$metric]['format'] ?? 'money') {
            'percent' => PyFormat::percent($value),
            'percent1' => number_format($value * 100, 1).'%',
            default => PyFormat::money($value),
        };
    }

    private static function zeroRatedShare(TaxpayerSnapshot $s): ?float
    {
        $rows = $s->vat->whereIn('period', $s->context->fiscalMonths);
        $sales = $rows->sum('zero_rated_sales') + $rows->sum('standard_rated_sales');

        return $sales ? $rows->sum('zero_rated_sales') / $sales : null;
    }
}
