<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\FindingDraft;
use App\Domain\Analysis\RuleOutcome;
use App\Domain\Analysis\RuleParameters;
use App\Domain\Analysis\TaxpayerSnapshot;

/**
 * P09: raised only once the taxpayer's own documents are in (housing and
 * vehicle records), never from returns alone — the one document-triggered
 * rule in the catalogue (checkpoint 7). Cites the PAYE return it compares
 * against; the released document itself is named in the wording and shown
 * in the case's "Requests and documents" section.
 */
class P09FringeBenefits implements PatternRule
{
    public function evaluate(TaxpayerSnapshot $s, RuleParameters $p): RuleOutcome
    {
        $docs = $s->documentsOfType('Housing and vehicle records');
        if ($docs->isEmpty()) {
            return RuleOutcome::notApplicable('Raised only from taxpayer documents (housing and vehicle records), never from returns.');
        }

        $latestPaye = $s->paye->sortByDesc('period')->first();
        if (! $latestPaye) {
            return RuleOutcome::notApplicable('Housing and vehicle records were released, but no PAYE return is on file to compare against.');
        }

        $doc = $docs->sortByDesc('received_at')->first();
        $declared = $latestPaye->fringe_benefits_value;

        return RuleOutcome::fired([new FindingDraft(
            scope: $doc->reference,
            // What the document itself says (e.g. "company-leased houses in Maseru
            // West and two company vehicles for the managers"), never the mock
            // data's own notes about it.
            values: ['document' => "{$doc->reference}, {$doc->title}", 'benefits' => lcfirst(rtrim($doc->content_summary, '.')), 'declared' => $declared],
            evidence: [$latestPaye->record_id],
            exposure: 0,
        )], "{$doc->reference} ({$doc->title}) shows benefits the PAYE returns do not declare.", ['document' => $doc->reference, 'declared_fringe_benefits' => $declared]);
    }
}
