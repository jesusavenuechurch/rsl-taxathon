<?php

namespace App\Domain\Analysis;

use App\Domain\Analysis\Rules\PatternRule;
use App\Models\Analysis\PatternRule as PatternRuleModel;

/**
 * Starting configuration for each pattern: the PHP class that evaluates it,
 * its thresholds, and the wording officers see. The descriptive text
 * (lawful explanations, what to establish, documents) comes from
 * database/data/rsl/pattern_rules.csv. Seeded once as version 1; after that
 * rule owners edit versions in the database, not this file.
 */
final class RuleCatalogue
{
    private const RISK = 'risk';

    /** @return array<string, class-string<PatternRule>> */
    public static function classes(): array
    {
        return [
            'P01' => Rules\P01PersistentLosses::class,
            'P02' => Rules\P02WhoFundsLosses::class,
            'P03' => Rules\P03FeesOutpacingRevenue::class,
            'P04' => Rules\P04WithholdingNotMatched::class,
            'P05' => Rules\P05TreatyRateClaimed::class,
            'P06' => Rules\P06ImportedServicesVat::class,
            'P07' => Rules\P07RecurringVatCredits::class,
            'P08' => Rules\P08LongTermPersonnel::class,
            'P09' => Rules\P09FringeBenefits::class,
            'P10' => Rules\P10LowTaxJurisdiction::class,
            'P11' => Rules\P11GroupLoanInterest::class,
            'P12' => Rules\P12ProfitSqueeze::class,
            'P13' => Rules\P13InformationGap::class,
            'P14' => Rules\P14DataCaptureError::class,
        ];
    }

    /**
     * @return array<string, array{trigger: string, parameters: array, variants: array, exposure_basis: ?string}>
     */
    public static function defaults(): array
    {
        $one = fn (string $title, string $template, string $kind = self::RISK) => ['default' => compact('title', 'kind', 'template')];

        return [
            'P01' => [
                'parameters' => ['min_loss_years' => 3],
                'variants' => $one('Persistent losses with group charges',
                    'Losses in {years|int} consecutive years ({first_year} to {last_year}) while paying related companies {related_total|money} over the period. This pattern may need examination.'),
                'exposure_basis' => null,
            ],
            'P02' => [
                'parameters' => ['min_loss_years' => 3],
                'variants' => $one('Who funds the losses',
                    'Related-party loans grew from {loans_start|money} to {loans_end|money} across the loss years. How the losses are funded, and what the group gains, may need examination.'),
                'exposure_basis' => null,
            ],
            'P03' => [
                'parameters' => ['min_gap' => 0.25, 'min_fees' => 500_000],
                'variants' => $one('Fees growing faster than revenue',
                    'Related-party fees rose {fee_growth|percent} against revenue growth of {revenue_growth|percent}. This pattern may need examination.'),
                'exposure_basis' => 'Fees above what revenue growth would explain, at the CIT rate',
            ],
            'P04' => [
                'parameters' => [
                    'min_amount' => 200_000,
                    'min_coverage' => 0.8,
                    'natures' => [
                        'related_party_management_fees' => 'Management fee',
                        'related_party_interest' => 'Interest',
                        'related_party_royalties' => 'Royalty',
                    ],
                ],
                'variants' => $one('Related-party payments without matching withholding tax',
                    '{nature} of {declared|money} reported in the {tax_year} return, but withholding tax schedules cover only {withheld_on|money}. These records appear inconsistent with each other.'),
                'exposure_basis' => 'Amount not on WHT schedules, at the standard WHT rate',
            ],
            'P05' => [
                'parameters' => [],
                'variants' => $one('Treaty rate claimed',
                    'Withholding at {rate|percent} applied to payments to {payee}, which the group register places in {country}, a country without a tax treaty with Lesotho. These records appear inconsistent with each other.'),
                'exposure_basis' => 'Review-year payments at the standard WHT rate less the rate applied',
            ],
            'P06' => [
                'parameters' => [
                    'min_services' => 200_000,
                    'min_reverse_charge_share' => 0.5,
                    'service_natures' => ['Management fee', 'Royalty', 'Technical services'],
                ],
                'variants' => $one('Imported services without matching VAT',
                    'Services of {services|money} were paid to non-resident related parties (withholding tax schedules), but reverse-charge VAT of only {reverse_charge|money} was declared; input VAT of {input_vat_imported|money} was claimed on imported services. These records appear inconsistent with each other.'),
                'exposure_basis' => 'Imported services at the VAT rate, less reverse-charge VAT declared',
            ],
            'P07' => [
                'parameters' => [
                    'min_credit_months' => 3,
                    'lekuka_tolerance' => 0.10,
                    'customs_tolerance' => 0.05,
                    'reverse_charge_factor' => 1.01,
                    'reverse_charge_margin' => 1,
                ],
                'variants' => [
                    'lower_risk' => [
                        'title' => 'Recurring VAT credits - lower risk',
                        'kind' => 'green_lane',
                        'template' => 'VAT credits in {credit_months|int} of the last {window|int} months. The available records are consistent with the reported trading activity: sales and purchases match Lekuka, and import VAT matches customs. No significant discrepancies were identified in the checks performed.',
                    ],
                    'needs_review' => [
                        'title' => 'Recurring VAT credits - needs review',
                        'kind' => self::RISK,
                        'template' => 'VAT credits in {credit_months|int} of the last {window|int} months. Checks found: {problems}.',
                    ],
                ],
                'exposure_basis' => 'VAT credits claimed in the window (needs review only)',
            ],
            'P08' => [
                'parameters' => ['min_months' => 12, 'max_variation' => 0.02, 'fee_nature' => 'Management fee', 'exposure_rate' => 0.30],
                'variants' => $one('Long-term personnel arrangement',
                    'An identical fee of {monthly_fee|money} has been paid to {payee} every month for all {months|int} months on record. A fixed, long-running charge can indicate seconded personnel; whether this is consultancy or employment may need examination.'),
                'exposure_basis' => "Last 12 months' fees at an indicative 30% (PAYE if the officer finds employment)",
            ],
            'P09' => [
                'trigger' => PatternRuleModel::TRIGGER_DOCUMENT,
                'parameters' => [],
                'variants' => $one('Unreported fringe benefits',
                    'Taxpayer documents show {benefits} provided to {who}, while PAYE returns declare fringe benefits of {declared|money}. The declared benefits do not appear to match the evidence provided.'),
                'exposure_basis' => null,
            ],
            'P10' => [
                'parameters' => [],
                'variants' => $one('Payments to low-tax jurisdictions',
                    'Payments of {total|money} ({natures}) were made to {payee}, a related entity in {country}, a low-tax jurisdiction. What the payments are for may need examination.'),
                'exposure_basis' => null,
            ],
            'P11' => [
                'parameters' => ['min_interest' => 500_000, 'min_share' => 0.30],
                'variants' => $one('Large interest on group loans',
                    'Interest of {interest|money} paid to related companies; operating result before interest is {result_before_interest|money}. This may need examination.'),
                'exposure_basis' => 'Related-party interest at the CIT rate',
            ],
            'P12' => [
                'parameters' => ['max_profit_ratio' => 0.5],
                'variants' => $one('Profit squeeze',
                    'Revenue rose {revenue_growth|percent} while net profit fell from {profit_prior|money} to {profit_now|money}. This may have a legitimate explanation and may need examination.'),
                'exposure_basis' => null,
            ],
            'P13' => [
                'parameters' => ['related_natures_pattern' => 'Management|Royalty|Interest'],
                'variants' => $one('Information gap',
                    'Related-party charges of {related_total|money} were reported, but {gaps}. The available records do not establish the nature or basis of these charges.',
                    'information_gap'),
                'exposure_basis' => null,
            ],
            'P14' => [
                'parameters' => ['multiple_of_median' => 4, 'fields' => ['input_vat_local', 'output_vat', 'standard_rated_sales']],
                'variants' => $one('Possible data capture error',
                    "{field_label} for {period} is {value|money}, about {multiple|fixed0} times the company's usual monthly level ({median|money}), while other figures are normal. Check the source figure before drawing conclusions (channel: {capture_channel}).",
                    'data_quality'),
                'exposure_basis' => null,
            ],
        ];
    }
}
