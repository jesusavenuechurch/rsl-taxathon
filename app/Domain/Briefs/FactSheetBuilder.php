<?php

namespace App\Domain\Briefs;

use App\Domain\Records\Channels;
use App\Domain\Records\RecordRegistry;
use App\Models\Analysis\Finding;
use App\Models\Analysis\RuleCheck;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\MemoryCheck;
use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\GroupEntity;
use App\Models\SourceRecords\SourceRecord;
use App\Models\SourceRecords\Taxpayer;
use App\Models\SourceRecords\VatReturn;
use App\Models\SourceRecords\WhtPayment;
use App\Support\Format;
use Illuminate\Support\Collection;

/**
 * Every angle on a case, as a list of record IDs the system already looked
 * up, each with its real figures, not just a note that it was "cited":
 * findings (with their lawful explanations and what must be established),
 * the company's income tax returns across every year on file, withholding
 * tax totals per payee, a VAT summary, the group structure, company memory,
 * and the refund letter. The AI prompt and the citation guard are both built
 * only from this (checkpoint 5: grounding).
 */
class FactSheetBuilder
{
    public function __construct(private RecordRegistry $records) {}

    public function build(ReviewCase $case): FactSheet
    {
        $case->loadMissing(['taxpayer', 'findings.evidence', 'findings.checklist', 'refundClaim']);
        $tin = $case->tin;

        $items = collect();

        if ($case->taxpayer) {
            $items->push($this->companyItem($case->taxpayer, $tin));
        }

        // The whole history, not just the years or payments a rule happened
        // to flag: this is what lets the AI connect findings across years.
        $this->citItems($tin)->each(fn ($i) => $items->push($i));
        $this->whtItems($tin)->each(fn ($i) => $items->push($i));
        $this->groupItems($case->taxpayer)->each(fn ($i) => $items->push($i));

        foreach ($case->findings as $finding) {
            $items->push($this->findingItem($finding));
            foreach ($finding->evidence as $evidence) {
                if ($item = $this->evidenceItem($evidence->record_id, $evidence->record_version)) {
                    $items->push($item);
                }
            }
        }

        foreach ($this->memoryChecks($case) as $check) {
            $items->push($this->memoryItem($check));
        }

        if ($case->refundClaim) {
            $items->push($this->refundClaimItem($case));
        }

        // A record already given its real figures above wins over a later,
        // thinner mention of the same ID (unique() keeps the first).
        return new FactSheet($items->unique('id')->values());
    }

    private function companyItem(Taxpayer $taxpayer, string $tin): FactSheetItem
    {
        $text = "{$taxpayer->name}, {$taxpayer->sector} sector, TIN {$tin}.".$this->vatSummary($tin);

        return new FactSheetItem($taxpayer->record_id, 'Company', $text);
    }

    /** Totals across every VAT period on file, not one line per period (there can be close to 20). */
    private function vatSummary(string $tin): string
    {
        $returns = VatReturn::current()->where('tin', $tin)->orderBy('period')->get();
        if ($returns->isEmpty()) {
            return '';
        }

        $outputVat = $returns->sum('output_vat');
        $inputVat = $returns->sum('input_vat_local') + $returns->sum('input_vat_imported_services');
        $netVat = $returns->sum('net_vat');
        $credits = $returns->where('position', 'credit')->count();

        return ' VAT returns on file: '.$returns->count()." periods ({$returns->first()->period} to {$returns->last()->period}), total output VAT ".
            Format::money($outputVat).', total input VAT '.Format::money($inputVat).', net VAT total '.Format::money($netVat).
            " ({$credits} period(s) in credit).";
    }

    /** @return Collection<int, FactSheetItem> */
    private function citItems(string $tin): Collection
    {
        return CitReturn::current()->where('tin', $tin)->orderBy('tax_year')->get()->map(function (CitReturn $r) {
            $text = "Tax year {$r->tax_year}: revenue ".Format::money($r->revenue).
                ', related-party management fees '.Format::money($r->related_party_management_fees).
                ', related-party interest '.Format::money($r->related_party_interest).
                ', related-party royalties '.Format::money($r->related_party_royalties).
                ', bank interest '.Format::money($r->bank_interest).
                ', depreciation '.Format::money($r->depreciation).
                ', net profit before tax '.Format::money($r->net_profit_before_tax).
                ', related-party loans closing '.Format::money($r->related_party_loans_closing).
                ', taxable income '.Format::money($r->taxable_income).'.';

            return new FactSheetItem($r->record_id, "Income tax return {$r->tax_year}", $text);
        });
    }

    /** One line per payee and service, not one per payment: the effective withholding rate is the fact that matters. */
    private function whtItems(string $tin): Collection
    {
        return WhtPayment::current()->where('withholding_agent_tin', $tin)->orderBy('record_id')
            ->get()->groupBy(fn (WhtPayment $p) => $p->payee_normalized_name.'|'.$p->nature_of_service)
            ->map(function (Collection $payments) {
                $first = $payments->first();
                $gross = $payments->sum('gross_amount');
                $withheld = $payments->sum('tax_withheld');
                $rate = $gross > 0 ? round($withheld / $gross * 100) : 0;
                $periods = $payments->pluck('period')->sort();

                $text = "{$payments->count()} payment(s) from {$periods->first()} to {$periods->last()}: total gross ".Format::money($gross).
                    ', total withheld '.Format::money($withheld)." ({$rate}% effective rate).";

                return new FactSheetItem($first->record_id, "Withholding tax: {$first->payee_legal_name} ({$first->nature_of_service})", $text);
            })->values();
    }

    private function groupItems(?Taxpayer $taxpayer): Collection
    {
        if (! $taxpayer?->group_code) {
            return collect();
        }

        return GroupEntity::current()->where('group_code', $taxpayer->group_code)->get()->map(function (GroupEntity $e) {
            $text = "{$e->entity_name} ({$e->country}): {$e->relationship}".
                ($e->low_tax_jurisdiction ? ', low-tax jurisdiction' : '').
                ($e->dta_with_lesotho ? ', tax treaty with Lesotho' : ', no tax treaty with Lesotho').'.';

            return new FactSheetItem($e->record_id, 'Group entity', $text);
        });
    }

    private function findingItem(Finding $finding): FactSheetItem
    {
        $checklist = $finding->checklist->groupBy('type');
        $lawful = $checklist->get('lawful_explanation', collect())->pluck('text')->implode('; ');
        $establish = $checklist->get('must_establish', collect())->pluck('text')->implode('; ');

        $text = $finding->wording
            .($lawful !== '' ? " Lawful explanations to weigh: {$lawful}." : '')
            .($establish !== '' ? " What the officer must establish: {$establish}." : '')
            .' Estimated exposure: '.Format::money($finding->exposure_estimate).' (estimated, not assessed).'
            .' Status: '.$finding->status.'.'
            .$this->notFiredSummary($finding);

        return new FactSheetItem($finding->reference, "Finding {$finding->pattern_code} {$finding->title}", $text, $finding->evidence_level);
    }

    /** What else the system checked on the same filing and found nothing in, e.g. "WHT schedules fully cover management fees". */
    private function notFiredSummary(Finding $finding): string
    {
        $notFired = RuleCheck::where('filing_review_id', $finding->last_review_id)
            ->where('result', RuleCheck::NOT_FIRED)->where('pattern_code', '!=', $finding->pattern_code)->get();

        if ($notFired->isEmpty()) {
            return '';
        }

        return ' Other checks on the same filing found nothing: '
            .$notFired->map(fn ($c) => "{$c->pattern_code} ({$c->explanation})")->implode('; ').'.';
    }

    public function evidenceItem(string $recordId, int $version): ?FactSheetItem
    {
        $channel = Channels::forRecordId($recordId);
        if (! $channel) {
            return null;
        }

        $record = $this->records->find($recordId, $version);
        if (! $record) {
            return new FactSheetItem($recordId, $channel->label, 'Record could not be loaded.');
        }

        return new FactSheetItem($recordId, $channel->label, $this->recordSummary($record, $channel->key));
    }

    private function recordSummary(SourceRecord $r, string $channelKey): string
    {
        return match ($channelKey) {
            'vat' => "Period {$r->period}: output VAT ".Format::money($r->output_vat).', input VAT '.
                Format::money($r->input_vat_local + $r->input_vat_imported_services).', net VAT '.Format::money($r->net_vat)." ({$r->position}).",
            'lekuka' => "Period {$r->period}: {$r->invoices_issued} invoices, sales ".
                Format::money($r->sales_standard_excl_vat + $r->sales_zero_rated).', VAT on sales '.Format::money($r->vat_on_sales).'.',
            'customs' => "Period {$r->period}: {$r->declarations} declaration(s), customs value ".Format::money($r->customs_value).
                ', import VAT paid '.Format::money($r->import_vat_paid).'.',
            'paye' => "Period {$r->period}: {$r->employees} employee(s), gross remuneration ".Format::money($r->gross_remuneration).
                ', PAYE withheld '.Format::money($r->paye_withheld).'.',
            'cit' => "Tax year {$r->tax_year}: revenue ".Format::money($r->revenue).', net profit before tax '.Format::money($r->net_profit_before_tax).'.',
            'wht' => "{$r->period}: ".Format::money($r->gross_amount)." gross to {$r->payee_legal_name} for {$r->nature_of_service}, ".
                Format::money($r->tax_withheld).' withheld.',
            'group_register' => "{$r->entity_name} ({$r->country}): {$r->relationship}.",
            default => "Record {$r->record_id}.",
        };
    }

    /** @return Collection<int, MemoryCheck> */
    private function memoryChecks(ReviewCase $case): Collection
    {
        $reviewIds = $case->findings->pluck('last_review_id')->filter()->unique();

        if ($reviewIds->isEmpty()) {
            return collect();
        }

        return MemoryCheck::with('decision')->whereIn('filing_review_id', $reviewIds)->get()->unique('decision_id');
    }

    private function memoryItem(MemoryCheck $check): FactSheetItem
    {
        $d = $check->decision;
        $resultLabel = ['within_conditions' => 'still within its conditions', 'outside_conditions' => 'outside its conditions', 'not_checkable' => 'left for the officer to judge'][$check->result] ?? $check->result;

        $text = "{$d->decided_on->format('Y')}: {$d->outcomeLabel()}. {$d->conclusion} Holds under: {$d->conditions}. Now {$resultLabel}: {$check->statement}";

        return new FactSheetItem($d->reference, "Company memory {$d->pattern_label}", $text);
    }

    private function refundClaimItem(ReviewCase $case): FactSheetItem
    {
        $rf = $case->refundClaim;

        $text = Format::money($rf->amount_claimed)." claimed for {$rf->vat_periods}, letter dated {$rf->letter_date->format('j M Y')}, ".
            "verification due {$rf->verification_due->format('j M Y')}, status {$rf->status}.".
            ($case->refund_lane ? ' Lane: '.$case->laneLabel().'.' : '');

        return new FactSheetItem($rf->record_id, 'Refund letter', $text);
    }
}
