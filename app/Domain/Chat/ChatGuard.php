<?php

namespace App\Domain\Chat;

use App\Domain\Briefs\BriefGuard;
use App\Domain\Briefs\BriefRejected;
use App\Domain\Briefs\FactSheet;

/**
 * Same discipline as the case brief's guard (every statement cites a record
 * the system looked up, and passes WordingPolicy), plus one more: a chat
 * answer states figures, so every number in it must appear in the facts it
 * was given. A model that rounds, converts or invents a figure is rejected
 * and the plain lookup answer is shown instead.
 */
final class ChatGuard
{
    public function __construct(private BriefGuard $briefGuard) {}

    /**
     * @return list<array{text: string, citations: list<string>}>
     *
     * @throws BriefRejected
     */
    public function validate(mixed $decoded, FactSheet $sheet): array
    {
        if (! is_array($decoded) || ! is_array($decoded['statements'] ?? null) || $decoded['statements'] === []) {
            throw new BriefRejected(['The response was not shaped as {statements: [...]}.']);
        }

        $problems = [];
        $statements = [];
        $known = $this->numbersIn($sheet->toPrompt());

        foreach ($decoded['statements'] as $i => $raw) {
            $result = $this->briefGuard->checkItem($raw, $sheet, 'Statement '.($i + 1));
            if (is_string($result)) {
                $problems[] = $result;

                continue;
            }
            $unknown = array_diff($this->numbersIn($result['text']), $known);
            if ($unknown !== []) {
                $problems[] = 'Statement '.($i + 1).' states a figure not in the records: '.implode(', ', $unknown).'.';

                continue;
            }
            $statements[] = $result;
        }

        if ($problems !== []) {
            throw new BriefRejected($problems);
        }

        return $statements;
    }

    /** @return list<string> every figure of two or more digits, commas removed */
    private function numbersIn(string $text): array
    {
        preg_match_all('/\d[\d,]*(?:\.\d+)?/', $text, $m);

        return array_values(array_unique(array_filter(
            array_map(fn ($n) => rtrim(str_replace(',', '', $n), '.'), $m[0]),
            fn ($n) => strlen(str_replace('.', '', $n)) >= 2,
        )));
    }
}
