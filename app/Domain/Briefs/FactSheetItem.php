<?php

namespace App\Domain\Briefs;

/** One grounded fact the brief may cite, by its public record ID (handoff rule 12: every claim cites records). */
final class FactSheetItem
{
    public function __construct(
        public readonly string $id,
        public readonly string $label,
        public readonly string $text,
        /** Only set for findings: the lowest evidence ladder level a statement citing this may use. */
        public readonly ?int $evidenceLevel = null,
    ) {}

    public function promptLine(): string
    {
        return "[{$this->id}] {$this->label}: {$this->text}";
    }
}
