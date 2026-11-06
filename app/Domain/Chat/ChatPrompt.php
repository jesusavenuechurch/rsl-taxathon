<?php

namespace App\Domain\Chat;

use App\Domain\Briefs\FactSheet;

/** Asks the model to answer one question from the facts the system looked up, citing them; a draft, never a conclusion. */
final class ChatPrompt
{
    public function build(string $question, FactSheet $sheet): string
    {
        $facts = $sheet->toPrompt();
        $example = $sheet->ids()[0] ?? 'F-0001';

        return <<<PROMPT
You answer questions for a Revenue Services Lesotho officer, using only the facts listed below. You never conclude, accuse or decide anything: the officer does that.

Hard rules:
- Use only the FACTS. If they do not answer the question, say so in one statement and cite the nearest fact. Never use outside knowledge.
- Every statement must cite at least one record ID listed below, e.g. ["{$example}"]. Never invent an ID or cite one that is not listed.
- Copy every figure exactly as written in the facts (amounts, percentages, years). Do not round, convert or calculate new ones.
- Never use these words, in any form: evasion, abuse, fraud, illegal, unlawful, non-compliant, guilty, wrongdoing, valid. A lower-risk refund lane is "lower risk", never "valid".
- Describe what the records show and what explanations are possible. Do not say a taxpayer did anything wrong.
- One or two plain sentences per statement. One statement per company when the question is about several companies.

QUESTION: {$question}

FACTS (cite only these IDs):
{$facts}

Answer with JSON only, matching this shape exactly:
{"statements": [{"text": "...", "citations": ["ID1", "ID2"]}]}
PROMPT;
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'statements' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => ['type' => 'string'],
                            'citations' => ['type' => 'array', 'items' => ['type' => 'string']],
                        ],
                        'required' => ['text', 'citations'],
                    ],
                ],
            ],
            'required' => ['statements'],
        ];
    }
}
