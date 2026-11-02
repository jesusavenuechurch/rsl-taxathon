<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layer 1: RSL's records as received, one table per channel, mirroring the
 * source files column for column (docs/implementation-plan.md section 3.1).
 *
 * Rows are never edited. A corrected figure arrives as a new version of the
 * same record_id and the old version is marked superseded, so findings can
 * always show exactly the row they were based on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('source_imports', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 32);
            $table->string('filename');
            $table->char('sha256', 64);
            $table->dateTime('received_at');        // RSL clock
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('rows_total');
            $table->unsignedInteger('rows_new');
            $table->unsignedInteger('rows_revised');
            $table->unsignedInteger('rows_unchanged');
            $table->json('warnings');
            $table->timestamps();

            $table->index(['channel', 'sha256']);
        });

        $this->sourceTable('taxpayers', function (Blueprint $table) {
            $table->string('tin', 32)->index();
            $table->string('name');
            $table->string('sector');
            $table->string('district');
            $table->date('registered_on');
            $table->boolean('vat_registered');
            $table->string('group_code', 32)->nullable()->index();
        });

        $this->sourceTable('group_entities', function (Blueprint $table) {
            $table->string('group_code', 32)->index();
            $table->string('entity_name');
            $table->string('normalized_name')->index();
            $table->string('country');
            $table->string('relationship');
            $table->boolean('low_tax_jurisdiction');
            $table->boolean('dta_with_lesotho');
        });

        $this->sourceTable('cit_returns', function (Blueprint $table) {
            $table->string('tin', 32);
            $table->string('tax_year', 7);           // 2025/26 = April 2025 - March 2026
            $table->date('filed_date');
            foreach ([
                'revenue', 'cost_of_sales', 'gross_profit', 'staff_costs', 'rent_utilities', 'depreciation',
                'other_operating_expenses', 'related_party_management_fees', 'related_party_interest',
                'related_party_royalties', 'bank_interest', 'net_profit_before_tax', 'assessed_loss_brought_forward',
                'taxable_income', 'assessed_loss_carried_forward', 'tax_payable', 'related_party_loans_closing',
            ] as $money) {
                $table->bigInteger($money);
            }
            $table->boolean('financial_statements_attached');
            $table->index(['tin', 'tax_year']);
        });

        $this->sourceTable('vat_returns', function (Blueprint $table) {
            $table->string('tin', 32);
            $table->char('period', 7);               // YYYY-MM
            $table->date('due_date');
            $table->date('filed_date');
            foreach ([
                'standard_rated_sales', 'zero_rated_sales', 'exempt_sales', 'output_vat', 'imported_services_value',
                'output_vat_imported_services', 'local_purchases_standard', 'input_vat_local', 'import_vat_per_return',
                'input_vat_imported_services', 'net_vat',
            ] as $money) {
                $table->bigInteger($money);
            }
            $table->string('position', 16);
            $table->string('capture_channel');
            $table->index(['tin', 'period']);
        });

        $this->sourceTable('wht_payments', function (Blueprint $table) {
            // The schedule has no payee TIN or country: payees are matched to
            // the group register by name (normalized_name on both sides).
            $table->string('withholding_agent_tin', 32);
            $table->char('period', 7);
            $table->date('payment_date');
            $table->string('payee_legal_name');
            $table->string('payee_normalized_name')->index();
            $table->string('nature_of_service');
            $table->string('form');
            $table->bigInteger('gross_amount');
            $table->bigInteger('tax_withheld');
            $table->string('submitted_via');
            $table->index(['withholding_agent_tin', 'period']);
        });

        $this->sourceTable('lekuka_monthly', function (Blueprint $table) {
            $table->string('tin', 32);
            $table->char('period', 7);
            $table->unsignedInteger('invoices_issued');
            foreach (['sales_standard_excl_vat', 'sales_zero_rated', 'vat_on_sales', 'purchases_from_vat_vendors_excl_vat', 'vat_on_purchases'] as $money) {
                $table->bigInteger($money);
            }
            $table->text('note')->nullable();
            $table->index(['tin', 'period']);
        });

        $this->sourceTable('customs_imports', function (Blueprint $table) {
            $table->string('tin', 32);
            $table->char('period', 7);
            $table->unsignedInteger('declarations');
            $table->bigInteger('customs_value');
            $table->string('main_origin');
            $table->bigInteger('import_vat_paid');
            $table->index(['tin', 'period']);
        });

        $this->sourceTable('paye_returns', function (Blueprint $table) {
            $table->string('tin', 32);
            $table->char('period', 7);
            $table->unsignedInteger('employees');
            foreach (['gross_remuneration', 'paye_withheld', 'fringe_benefits_value', 'fbt_paid'] as $money) {
                $table->bigInteger($money);
            }
            $table->index(['tin', 'period']);
        });

        $this->sourceTable('refund_claims', function (Blueprint $table) {
            $table->string('tin', 32)->index();
            $table->string('vat_periods');           // as written in the letter
            $table->bigInteger('amount_claimed');
            $table->date('letter_date');
            $table->date('verification_due');
            $table->string('status');
            $table->string('channel');
        });

        // The letter's periods, one row each, so claims join to VAT returns.
        Schema::create('refund_claim_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('refund_claim_id')->constrained()->cascadeOnDelete();
            $table->char('period', 7)->index();
            $table->unique(['refund_claim_id', 'period']);
        });
    }

    public function down(): void
    {
        foreach (['refund_claim_periods', 'refund_claims', 'paye_returns', 'customs_imports', 'lekuka_monthly',
            'wht_payments', 'vat_returns', 'cit_returns', 'group_entities', 'taxpayers', 'source_imports'] as $table) {
            Schema::dropIfExists($table);
        }
    }

    /** Columns every source table shares: identity, version and provenance. */
    private function sourceTable(string $name, Closure $columns): void
    {
        Schema::create($name, function (Blueprint $table) use ($columns) {
            $table->id();
            $table->string('record_id', 20);
            $table->unsignedSmallInteger('version')->default(1);
            $table->dateTime('superseded_at')->nullable();
            $table->foreignId('source_import_id')->constrained()->restrictOnDelete();
            $table->char('row_hash', 64);

            $columns($table);

            $table->string('source_system');
            $table->timestamps();

            $table->unique(['record_id', 'version']);
            $table->index(['record_id', 'superseded_at']);
        });
    }
};
