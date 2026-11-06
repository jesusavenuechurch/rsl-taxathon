<?php

namespace App\Domain\Chat;

use App\Domain\Briefs\FactSheet;
use App\Domain\Briefs\FactSheetBuilder;
use App\Domain\Briefs\FactSheetItem;
use App\Models\Analysis\Finding;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\Decision;
use App\Models\Memory\MemoryCheck;
use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\Taxpayer;
use App\Support\Format;
use Illuminate\Support\Collection;

/**
 * Looks up what a question is about, in the records, before any model is
 * involved: the answer to a recognised question comes straight from the
 * records (the fallback), and the same records become the fact sheet the AI
 * may rephrase it from. A question about nothing recognisable still gets a
 * general fact sheet of every open finding and earlier decision, so the model
 * has something real to cite, never its own memory.
 */
class ChatRetriever
{
    private const NOTE = 'Indicators for review, not evidence of wrongdoing.';

    public function __construct(private FactSheetBuilder $facts) {}

    public function retrieve(string $question, ?ReviewCase $case = null): ChatRetrieval
    {
        $q = mb_strtolower($question);
        $taxpayer = $this->taxpayerNamedIn($q) ?? $case?->taxpayer;

        return match (true) {
            $taxpayer !== null && $this->asksAboutHistory($q) => $this->history($taxpayer),
            $taxpayer !== null && ! $this->asksAcrossCompanies($q) => $this->company($taxpayer),
            (bool) preg_match('/related[- ]party|intra-?group|group payments/', $q) => $this->relatedPartyIncreases(),
            (bool) preg_match('/low[- ]tax|tax haven|jurisdiction/', $q) => $this->findingsOfPattern('P10'),
            (bool) preg_match('/vat credit|recurring vat|refund/', $q) => $this->findingsOfPattern('P07'),
            default => $this->general(),
        };
    }

    private function asksAboutHistory(string $q): bool
    {
        return (bool) preg_match('/review(ed)? before|previous|earlier|history|decision|memory|explained|seen before/', $q);
    }

    private function asksAcrossCompanies(string $q): bool
    {
        return (bool) preg_match('/which companies|show me companies|companies (with|paying|that)/', $q);
    }

    /** Matches on a company's distinctive name ("Qiloane", "Highveld"), not its "(Pty) Ltd". */
    private function taxpayerNamedIn(string $q): ?Taxpayer
    {
        $stop = ['the', 'and', 'of', 'pty', 'ltd', 'limited', 'lesotho', 'group', 'trading', 'retail', 'foods', 'imports', 'hardware', 'building', 'supplies', 'distributors', 'wholesale'];

        return Taxpayer::current()->get()
            ->map(function (Taxpayer $t) use ($q, $stop) {
                $words = collect(preg_split('/[^a-z0-9]+/', mb_strtolower($t->name), -1, PREG_SPLIT_NO_EMPTY))
                    ->reject(fn ($w) => strlen($w) < 4 || in_array($w, $stop, true));

                // The first distinctive word is the name people use; matching more of them ranks higher.
                $hits = $words->filter(fn ($w) => preg_match('/\b'.preg_quote($w, '/').'\b/', $q))->count();

                return ['taxpayer' => $t, 'hits' => $words->isNotEmpty() && preg_match('/\b'.preg_quote($words->first(), '/').'\b/', $q) ? $hits : 0];
            })
            ->filter(fn ($m) => $m['hits'] > 0)->sortByDesc('hits')->first()['taxpayer'] ?? null;
    }

    // -- one company: its open findings, with the same facts the case brief sees ---------------------------------

    private function company(Taxpayer $taxpayer): ChatRetrieval
    {
        $case = ReviewCase::where('tin', $taxpayer->tin)->latest('id')->first();
        $findings = Finding::open()->where('tin', $taxpayer->tin)->with('evidence')->orderBy('reference')->get();

        $sheet = $case ? $this->facts->build($case) : new FactSheet(collect([$this->companyItem($taxpayer)]));
        $lookup = $findings->map(fn (Finding $f) => $this->findingStatement($f, $taxpayer))->all();

        return new ChatRetrieval($sheet, $lookup, $lookup === [] ? null : self::NOTE);
    }

    // -- earlier decisions about one company ---------------------------------------------------------------------

    private function history(Taxpayer $taxpayer): ChatRetrieval
    {
        $decisions = Decision::current()->where('tin', $taxpayer->tin)->orderBy('decided_on')->get();
        $case = ReviewCase::where('tin', $taxpayer->tin)->latest('id')->first();
        $sheet = $case ? $this->facts->build($case) : new FactSheet(collect([$this->companyItem($taxpayer)]));

        $lookup = [];
        foreach ($decisions as $d) {
            $lookup[] = [
                'text' => "Yes. {$taxpayer->shortName()} was reviewed in {$d->decided_on->format('Y')} ({$d->pattern_label}): {$d->outcomeLabel()}. ".$this->sentence($d->conclusion).' That holds under: '.$this->sentence($d->conditions),
                'citations' => [$d->reference],
            ];
            $check = MemoryCheck::where('decision_id', $d->id)->latest('id')->first();
            if ($check) {
                $lookup[] = ['text' => $check->statement, 'citations' => array_values(array_filter([$d->reference, $this->findingRefFor($check)]))];
            }
        }

        if ($lookup === []) {
            $lookup[] = ['text' => "No earlier officer decision about {$taxpayer->shortName()} is on file.", 'citations' => [$taxpayer->record_id]];
        }

        return new ChatRetrieval($sheet, $lookup);
    }

    private function sentence(string $text): string
    {
        $text = trim($text);

        return preg_match('/[.!?]$/', $text) ? $text : $text.'.';
    }

    private function findingRefFor(MemoryCheck $check): ?string
    {
        return Finding::open()->where('last_review_id', $check->filing_review_id)->orderBy('reference')->value('reference');
    }

    // -- companies across the registry ---------------------------------------------------------------------------

    private function relatedPartyIncreases(): ChatRetrieval
    {
        $threshold = (float) config('rsl.chat_related_party_increase', 0.30);
        $items = collect();
        $lookup = [];

        foreach (CitReturn::current()->orderBy('tax_year')->get()->groupBy('tin') as $tin => $returns) {
            if ($returns->count() < 2) {
                continue;
            }
            [$before, $after] = [$returns[$returns->count() - 2], $returns->last()];
            $was = $this->relatedPartyTotal($before);
            if ($was <= 0) {
                continue;
            }
            $now = $this->relatedPartyTotal($after);
            $growth = $now / $was - 1;
            if ($growth < $threshold) {
                continue;
            }

            $taxpayer = Taxpayer::current()->where('tin', $tin)->first();
            $pct = '+'.round($growth * 100).'%';
            $items->push(new FactSheetItem($before->record_id, "Income tax return {$before->tax_year}", "{$taxpayer?->name}: related-party payments (management fees, interest and royalties) ".Format::money($was).'.'));
            $items->push(new FactSheetItem($after->record_id, "Income tax return {$after->tax_year}", "{$taxpayer?->name}: related-party payments (management fees, interest and royalties) ".Format::money($now)." ({$pct} on {$before->tax_year})."));

            $citations = [$before->record_id, $after->record_id];
            $text = "{$taxpayer?->shortName()}: related-party payments rose from ".Format::money($was).' to '.Format::money($now)." ({$pct}) between {$before->tax_year} and {$after->tax_year}.";

            foreach (Decision::current()->where('tin', $tin)->where('pattern_code', 'P03')->get() as $d) {
                $items->push(new FactSheetItem($d->reference, "Company memory {$d->pattern_label}", "{$d->decided_on->format('Y')}: {$d->outcomeLabel()}. {$d->conclusion}"));
                $text .= " Previously explained under {$d->reference}.";
                $citations[] = $d->reference;
            }
            $lookup[] = ['growth' => $growth, 'text' => $text, 'citations' => $citations];
        }

        usort($lookup, fn ($a, $b) => $b['growth'] <=> $a['growth']);

        return new ChatRetrieval(new FactSheet($items->unique('id')->values()),
            array_map(fn ($l) => ['text' => $l['text'], 'citations' => $l['citations']], $lookup), self::NOTE);
    }

    private function relatedPartyTotal(CitReturn $r): int
    {
        return $r->related_party_management_fees + $r->related_party_interest + $r->related_party_royalties;
    }

    // -- one pattern, across every company -----------------------------------------------------------------------

    private function findingsOfPattern(string $code): ChatRetrieval
    {
        $findings = Finding::open()->where('pattern_code', $code)->with('evidence')->orderBy('reference')->get();
        $items = $findings->flatMap(fn (Finding $f) => $this->findingItems($f, Taxpayer::current()->where('tin', $f->tin)->first()));

        return new ChatRetrieval(new FactSheet($items->unique('id')->values()),
            $findings->map(fn (Finding $f) => $this->findingStatement($f, Taxpayer::current()->where('tin', $f->tin)->first()))->all(), self::NOTE);
    }

    // -- anything else: every open finding and earlier decision -------------------------------------------------

    private function general(): ChatRetrieval
    {
        $items = Finding::open()->with('evidence')->orderBy('reference')->get()
            ->flatMap(fn (Finding $f) => $this->findingItems($f, Taxpayer::current()->where('tin', $f->tin)->first()));

        foreach (Decision::current()->get() as $d) {
            $items->push(new FactSheetItem($d->reference, "Company memory {$d->pattern_label}", "{$d->decided_on->format('Y')}: {$d->outcomeLabel()}. {$d->conclusion}"));
        }

        return new ChatRetrieval(new FactSheet($items->unique('id')->values()), []);
    }

    // -- shared pieces -------------------------------------------------------------------------------------------

    private function companyItem(Taxpayer $taxpayer): FactSheetItem
    {
        return new FactSheetItem($taxpayer->record_id, 'Company', "{$taxpayer->name}, {$taxpayer->sector} sector, TIN {$taxpayer->tin}.");
    }

    /** @return Collection<int, FactSheetItem> the finding with the company it concerns and its evidence records */
    private function findingItems(Finding $f, ?Taxpayer $taxpayer): Collection
    {
        $items = collect([new FactSheetItem($f->reference, "Finding {$f->pattern_code} {$f->title}",
            "{$taxpayer?->name}: {$f->wording}", $f->evidence_level)]);

        if ($taxpayer) {
            $items->push($this->companyItem($taxpayer));
        }
        foreach ($f->evidence->take(8) as $evidence) {
            if ($item = $this->facts->evidenceItem($evidence->record_id, $evidence->record_version)) {
                $items->push($item);
            }
        }

        return $items;
    }

    /** @return array{text: string, citations: list<string>} */
    private function findingStatement(Finding $f, ?Taxpayer $taxpayer): array
    {
        return [
            'text' => "{$taxpayer?->shortName()} ({$f->title}): {$f->wording}",
            'citations' => array_values(array_unique([$f->reference, ...$f->evidence->take(6)->pluck('record_id')->all()])),
        ];
    }
}
