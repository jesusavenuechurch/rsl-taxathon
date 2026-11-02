<?php

namespace App\Domain\Records;

use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\CustomsImport;
use App\Models\SourceRecords\GroupEntity;
use App\Models\SourceRecords\LekukaReturn;
use App\Models\SourceRecords\PayeReturn;
use App\Models\SourceRecords\RefundClaim;
use App\Models\SourceRecords\Taxpayer;
use App\Models\SourceRecords\VatReturn;
use App\Models\SourceRecords\WhtPayment;
use Illuminate\Support\Str;

/**
 * Every RSL channel the system receives, in import order (taxpayers first so
 * other files can be checked against registered TINs). The single place
 * that knows record ID prefixes, file layouts and field types.
 */
final class Channels
{
    /** @var array<string, Channel>|null */
    private static ?array $all = null;

    /** @return array<string, Channel> keyed by channel key */
    public static function all(): array
    {
        return self::$all ??= collect(self::definitions())->keyBy->key->all();
    }

    public static function get(string $key): ?Channel
    {
        return self::all()[$key] ?? null;
    }

    public static function forRecordId(string $recordId): ?Channel
    {
        $prefix = Str::before(strtoupper(trim($recordId)), '-');

        return collect(self::all())->first(fn (Channel $c) => $c->prefix === $prefix);
    }

    /** @return list<Channel> */
    private static function definitions(): array
    {
        $money = fn (string ...$columns) => array_map(fn ($c) => self::f($c, Field::MONEY), $columns);

        return [
            new Channel('companies', 'COM', 'Company registration', 'eTax registration', 'companies.csv', Taxpayer::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('name', Field::STRING),
                self::f('sector', Field::STRING),
                self::f('district', Field::STRING),
                self::f('registered', Field::DATE, 'Registered', attribute: 'registered_on'),
                self::f('vat_registered', Field::YES_NO, 'VAT registered'),
                self::f('group_id', Field::STRING, 'Group', attribute: 'group_code', nullable: true, maxLength: 32),
                self::f('source_system', Field::STRING),
            ]),
            new Channel('group_register', 'GRP', 'Group register', 'Company registry / taxpayer disclosure', 'group_register.csv', GroupEntity::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('group_id', Field::STRING, 'Group', attribute: 'group_code', maxLength: 32),
                self::f('entity_name', Field::STRING),
                self::f('country', Field::STRING),
                self::f('relationship', Field::STRING),
                self::f('low_tax_jurisdiction', Field::YES_NO),
                self::f('dta_with_lesotho', Field::YES_NO, 'Tax treaty with Lesotho'),
                self::f('source', Field::STRING, 'Source', attribute: 'source_system'),
            ], tinAttribute: null),
            new Channel('cit', 'CIT', 'Income tax return', 'eTax', 'cit_returns.csv', CitReturn::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('tax_year', Field::TAX_YEAR),
                self::f('filed_date', Field::DATE, 'Filed'),
                ...$money('revenue', 'cost_of_sales', 'gross_profit', 'staff_costs'),
                self::f('rent_utilities', Field::MONEY, 'Rent and utilities'),
                ...$money('depreciation',
                    'other_operating_expenses', 'related_party_management_fees', 'related_party_interest',
                    'related_party_royalties', 'bank_interest', 'net_profit_before_tax', 'assessed_loss_brought_forward',
                    'taxable_income', 'assessed_loss_carried_forward', 'tax_payable', 'related_party_loans_closing'),
                self::f('financial_statements_attached', Field::YES_NO),
                self::f('source_system', Field::STRING),
            ], receivedDateAttribute: 'filed_date'),
            new Channel('vat', 'VAT', 'VAT return', 'eTax', 'vat_returns.csv', VatReturn::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('period', Field::PERIOD),
                self::f('due_date', Field::DATE, 'Due'),
                self::f('filed_date', Field::DATE, 'Filed'),
                ...$money('standard_rated_sales', 'zero_rated_sales', 'exempt_sales', 'output_vat', 'imported_services_value',
                    'output_vat_imported_services', 'local_purchases_standard', 'input_vat_local', 'import_vat_per_return',
                    'input_vat_imported_services', 'net_vat'),
                self::f('position', Field::STRING, maxLength: 32),
                self::f('capture_channel', Field::STRING),
                self::f('source_system', Field::STRING),
            ], receivedDateAttribute: 'filed_date'),
            new Channel('wht', 'WHT', 'Withholding tax schedule', 'WHT Excel schedule (email)', 'wht_schedule.csv', WhtPayment::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('withholding_agent_tin', Field::STRING, 'Withholding agent TIN', maxLength: 32),
                self::f('period', Field::PERIOD),
                self::f('payment_date', Field::DATE, 'Paid'),
                self::f('payee_legal_name', Field::STRING, 'Payee'),
                self::f('nature_of_service', Field::STRING),
                self::f('form', Field::STRING),
                ...$money('gross_amount', 'tax_withheld'),
                self::f('submitted_via', Field::STRING),
                self::f('source_system', Field::STRING),
            ], tinAttribute: 'withholding_agent_tin', receivedDateAttribute: 'payment_date'),
            new Channel('lekuka', 'LEK', 'E-invoicing monthly summary', 'Lekuka (IDMS)', 'lekuka_monthly.csv', LekukaReturn::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('period', Field::PERIOD),
                self::f('invoices_issued', Field::INTEGER),
                ...$money('sales_standard_excl_vat', 'sales_zero_rated', 'vat_on_sales', 'purchases_from_vat_vendors_excl_vat', 'vat_on_purchases'),
                self::f('note', Field::TEXT, nullable: true),
                self::f('source_system', Field::STRING),
            ]),
            new Channel('customs', 'CUS', 'Customs imports', 'eCustoms (ASYCUDA)', 'customs_imports.csv', CustomsImport::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('period', Field::PERIOD),
                self::f('declarations', Field::INTEGER),
                self::f('customs_value', Field::MONEY),
                self::f('main_origin', Field::STRING),
                self::f('import_vat_paid', Field::MONEY, 'Import VAT paid'),
                self::f('source_system', Field::STRING),
            ]),
            new Channel('paye', 'PAYE', 'PAYE return', 'eTax', 'paye_returns.csv', PayeReturn::class, [
                self::f('record_id', Field::STRING, 'Record ID', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('period', Field::PERIOD),
                self::f('employees', Field::INTEGER),
                ...$money('gross_remuneration', 'paye_withheld', 'fringe_benefits_value', 'fbt_paid'),
                self::f('source_system', Field::STRING),
            ]),
            new Channel('refund_claims', 'RF', 'VAT refund letter', 'Refunds (written letter)', 'refund_claims.csv', RefundClaim::class, [
                self::f('claim_id', Field::STRING, 'Claim ID', attribute: 'record_id', maxLength: 20),
                self::f('tin', Field::STRING, 'TIN', maxLength: 32),
                self::f('vat_periods', Field::STRING, 'VAT periods'),
                self::f('amount_claimed', Field::MONEY),
                self::f('letter_date', Field::DATE),
                self::f('verification_due', Field::DATE),
                self::f('status', Field::STRING),
                self::f('channel', Field::STRING),
                self::f('source_system', Field::STRING),
            ], idColumn: 'claim_id', receivedDateAttribute: 'letter_date'),
        ];
    }

    private static function f(string $column, string $type, ?string $label = null, ?string $attribute = null, bool $nullable = false, ?int $maxLength = null): Field
    {
        $label ??= Str::of($column)->replace('_', ' ')->ucfirst()
            ->replace(['Vat ', ' vat', 'Fbt ', 'Paye ', ' paye', 'Excl vat', 'Dta '], ['VAT ', ' VAT', 'FBT ', 'PAYE ', ' PAYE', 'excl. VAT', 'DTA '])
            ->toString();

        return new Field($column, $attribute ?? $column, $type, $label, $nullable, $maxLength);
    }
}
