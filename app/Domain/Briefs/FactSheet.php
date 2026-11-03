<?php

namespace App\Domain\Briefs;

use Illuminate\Support\Collection;

/**
 * Exactly the record IDs the system looked up for one case. The AI prompt is
 * built only from this; the guard rejects any citation outside it. This is
 * the grounding checkpoint 5 depends on.
 */
final class FactSheet
{
    /** @param Collection<int, FactSheetItem> $items */
    public function __construct(public readonly Collection $items) {}

    /** @return list<string> */
    public function ids(): array
    {
        return $this->items->pluck('id')->all();
    }

    public function has(string $id): bool
    {
        return in_array(strtoupper(trim($id)), $this->ids(), true);
    }

    public function toPrompt(): string
    {
        return $this->items->map(fn (FactSheetItem $i) => $i->promptLine())->implode("\n");
    }

    /**
     * The lowest evidence-ladder level a statement citing these IDs may use.
     * No cited finding (only records, decisions, the refund letter) is the
     * most conservative case: level 1, "may need examination" wording only.
     */
    public function evidenceLevelFor(array $citedIds): int
    {
        $levels = $this->items
            ->whereIn('id', $citedIds)
            ->pluck('evidenceLevel')
            ->filter(fn ($l) => $l !== null);

        return $levels->isEmpty() ? 1 : $levels->min();
    }
}
