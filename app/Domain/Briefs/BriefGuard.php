<?php

namespace App\Domain\Briefs;

use App\Domain\Analysis\Wording\WordingPolicy;

/**
 * The AI drafts; it never concludes. Every statement must cite record IDs
 * the system actually looked up (never an invented one, never uncited), and
 * must pass the same WordingPolicy a finding does. If anything fails, the
 * whole draft is rejected and the generator falls back to the deterministic
 * template, so the demo never shows an unchecked AI sentence.
 */
final class BriefGuard
{
    /**
     * @return array{statements: list<array{text: string, citations: list<string>}>, next_step: array{text: string, citations: list<string>}}
     *
     * @throws BriefRejected
     */
    public function validate(mixed $decoded, FactSheet $sheet): array
    {
        if (! is_array($decoded) || ! is_array($decoded['statements'] ?? null) || ! is_array($decoded['next_step'] ?? null)) {
            throw new BriefRejected(['The response was not shaped as {statements: [...], next_step: {...}}.']);
        }

        $problems = [];
        $statements = [];

        foreach ($decoded['statements'] as $i => $raw) {
            $result = $this->checkItem($raw, $sheet, 'Statement '.($i + 1));
            is_string($result) ? $problems[] = $result : $statements[] = $result;
        }

        if ($statements === []) {
            $problems[] = 'No statement survived grounding checks.';
        }

        $nextStep = $this->checkItem($decoded['next_step'], $sheet, 'The next step');
        if (is_string($nextStep)) {
            $problems[] = $nextStep;
            $nextStep = null;
        }

        if ($problems !== []) {
            throw new BriefRejected($problems);
        }

        return ['statements' => $statements, 'next_step' => $nextStep];
    }

    /** @return array{text: string, citations: list<string>}|string a clean item, or the problem found */
    public function checkItem(mixed $raw, FactSheet $sheet, string $label): array|string
    {
        if (! is_array($raw) || ! is_string($raw['text'] ?? null) || trim($raw['text']) === '') {
            return "{$label} has no text.";
        }

        $citations = array_values(array_unique(array_map(
            fn ($c) => strtoupper(trim((string) $c)),
            is_array($raw['citations'] ?? null) ? $raw['citations'] : []
        )));

        if ($citations === []) {
            return "{$label} cites nothing: \"{$raw['text']}\".";
        }

        $invalid = array_diff($citations, $sheet->ids());
        if ($invalid !== []) {
            return "{$label} cites a record not in the fact sheet: ".implode(', ', $invalid).'.';
        }

        $violations = WordingPolicy::violations($raw['text'], $sheet->evidenceLevelFor($citations));
        if ($violations !== []) {
            return "{$label}: ".implode(' ', $violations);
        }

        return ['text' => trim($raw['text']), 'citations' => $citations];
    }
}
