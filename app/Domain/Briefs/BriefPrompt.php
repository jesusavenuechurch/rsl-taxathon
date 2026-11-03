<?php

namespace App\Domain\Briefs;

use App\Models\Cases\ReviewCase;

/** Asks the model to consolidate a case's fact sheet into a cited brief with a suggested next step — a draft, never a conclusion. */
final class BriefPrompt
{
    public function build(ReviewCase $case, FactSheet $sheet): string
    {
        $facts = $sheet->toPrompt();

        return <<<PROMPT
You are drafting a case brief for a Revenue Services Lesotho officer. You consolidate the facts below into a short brief; you never conclude, accuse or decide anything — the officer does that.

Hard rules:
- Every statement must cite at least one of the record IDs listed below, in square brackets, e.g. [{$this->exampleId($sheet)}]. Never invent an ID and never cite one that is not listed.
- Never use these words, in any form: evasion, abuse, fraud, illegal, unlawful, non-compliant, guilty, wrongdoing, valid. A lower-risk refund lane is "lower risk", never "valid".
- Only an officer concludes. Do not say the taxpayer did anything wrong; describe what the records show and what explanations are possible.
- Keep each statement to one or two sentences, in plain English, no jargon.
- The suggested next step is a suggestion for the officer, not an instruction: phrase it as "Consider..." or "The next step could be...".

CASE {$case->reference}: {$case->typeLabel()}, {$case->team->name}.

FACTS (cite only these IDs):
{$facts}

Answer with JSON only, matching this shape exactly:
{"statements": [{"text": "...", "citations": ["ID1", "ID2"]}], "next_step": {"text": "...", "citations": ["ID1"]}}

Write 3 to 6 statements that together summarise what the records show across the findings, memory and refund letter above. Then one suggested next step.
PROMPT;
    }

    public function schema(): array
    {
        $item = [
            'type' => 'object',
            'properties' => [
                'text' => ['type' => 'string'],
                'citations' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'required' => ['text', 'citations'],
        ];

        return [
            'type' => 'object',
            'properties' => [
                'statements' => ['type' => 'array', 'items' => $item],
                'next_step' => $item,
            ],
            'required' => ['statements', 'next_step'],
        ];
    }

    private function exampleId(FactSheet $sheet): string
    {
        return $sheet->ids()[0] ?? 'F-0001';
    }
}
