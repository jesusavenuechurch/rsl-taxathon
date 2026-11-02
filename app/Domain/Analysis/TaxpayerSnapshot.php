<?php

namespace App\Domain\Analysis;

use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\CustomsImport;
use App\Models\SourceRecords\GroupEntity;
use App\Models\SourceRecords\LekukaReturn;
use App\Models\SourceRecords\PayeReturn;
use App\Models\SourceRecords\RefundClaim;
use App\Models\SourceRecords\SourceRecord;
use App\Models\SourceRecords\Taxpayer;
use App\Models\SourceRecords\VatReturn;
use App\Models\SourceRecords\WhtPayment;
use Illuminate\Support\Collection;

/**
 * Everything RSL holds on one taxpayer, current versions only, loaded once
 * per analysis. Rows are in record ID order (the order they were filed).
 */
final class TaxpayerSnapshot
{
    /** @var array<string, SourceRecord> */
    private array $byRecordId = [];

    /**
     * @param  Collection<int, CitReturn>  $cit
     * @param  Collection<int, VatReturn>  $vat
     * @param  Collection<int, WhtPayment>  $wht
     * @param  Collection<int, LekukaReturn>  $lekuka
     * @param  Collection<int, CustomsImport>  $customs
     * @param  Collection<int, PayeReturn>  $paye
     * @param  Collection<int, RefundClaim>  $refunds
     * @param  Collection<string, GroupEntity>  $groupEntities  keyed by normalized name (whole register)
     */
    public function __construct(
        public readonly string $tin,
        public readonly ?Taxpayer $taxpayer,
        public readonly AnalysisContext $context,
        public readonly Collection $cit,
        public readonly Collection $vat,
        public readonly Collection $wht,
        public readonly Collection $lekuka,
        public readonly Collection $customs,
        public readonly Collection $paye,
        public readonly Collection $refunds,
        public readonly Collection $groupEntities,
    ) {
        foreach ([$cit, $vat, $wht, $lekuka, $customs, $paye, $refunds, $groupEntities] as $rows) {
            foreach ($rows as $row) {
                $this->byRecordId[$row->record_id] = $row;
            }
        }
    }

    public static function load(string $tin, AnalysisContext $context, ?Collection $groupEntities = null): self
    {
        $rows = fn (string $model, string $tinColumn = 'tin') => $model::current()->where($tinColumn, $tin)->orderBy('record_id')->get();

        return new self(
            $tin,
            Taxpayer::current()->where('tin', $tin)->first(),
            $context,
            $rows(CitReturn::class),
            $rows(VatReturn::class),
            $rows(WhtPayment::class, 'withholding_agent_tin'),
            $rows(LekukaReturn::class),
            $rows(CustomsImport::class),
            $rows(PayeReturn::class),
            $rows(RefundClaim::class),
            $groupEntities ?? GroupEntity::current()->orderBy('record_id')->get()->keyBy('normalized_name'),
        );
    }

    public function citFor(?string $taxYear): ?CitReturn
    {
        return $taxYear ? $this->cit->firstWhere('tax_year', $taxYear) : null;
    }

    public function vatFor(string $period): ?VatReturn
    {
        return $this->vat->firstWhere('period', $period);
    }

    public function groupEntityFor(string $payeeName): ?GroupEntity
    {
        return $this->groupEntities->get(SourceRecord::normalizeName($payeeName));
    }

    /** The row a record ID points at, as loaded (to pin evidence to its version). */
    public function record(string $recordId): ?SourceRecord
    {
        return $this->byRecordId[$recordId] ?? null;
    }

    public function relatedCharges(CitReturn $year): int
    {
        return $year->related_party_management_fees + $year->related_party_interest + $year->related_party_royalties;
    }
}
