<?php

namespace App\Domain\Analysis;

use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\VatReturn;

/**
 * The review window shared by every taxpayer in a run: the tax year under
 * review (latest filed in eTax), the year before it, that year's months, and
 * the last six VAT periods received.
 */
final class AnalysisContext
{
    /**
     * @param  list<string>  $fiscalMonths  YYYY-MM, April to March of the review year
     * @param  list<string>  $recentVatPeriods  the last N VAT periods on record
     */
    public function __construct(
        public readonly ?string $reviewYear,
        public readonly ?string $priorYear,
        public readonly array $fiscalMonths,
        public readonly array $recentVatPeriods,
    ) {}

    public static function fromRecords(int $vatWindow = 6): self
    {
        $reviewYear = CitReturn::current()->max('tax_year');
        $periods = VatReturn::current()->distinct()->orderBy('period')->pluck('period')->all();

        return new self(
            $reviewYear,
            $reviewYear ? self::previousTaxYear($reviewYear) : null,
            $reviewYear ? self::monthsOf($reviewYear) : [],
            array_slice($periods, -$vatWindow),
        );
    }

    /** "2025/26" -> "2024/25" */
    public static function previousTaxYear(string $year): string
    {
        $start = (int) substr($year, 0, 4) - 1;

        return sprintf('%d/%02d', $start, ($start + 1) % 100);
    }

    /** "2025/26" -> 2025-04 ... 2026-03 */
    public static function monthsOf(string $year): array
    {
        $start = (int) substr($year, 0, 4);
        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $m = 4 + $i;
            $months[] = sprintf('%d-%02d', $start + intdiv($m - 1, 12), ($m - 1) % 12 + 1);
        }

        return $months;
    }
}
