<?php

namespace App\Domain\Analysis;

use App\Events\SourceRecordsImported;
use App\Jobs\AnalyseTaxpayers;

/**
 * Decides when newly received records get analysed. Normally each import
 * queues an analysis of the taxpayers it touched. While deferring (a batch of
 * files), it only collects them, so the batch is analysed once, on complete
 * data, instead of once per file on partial data.
 */
class AnalysisScheduler
{
    /** @var array<string, true>|null */
    private ?array $collected = null;

    /**
     * @param  callable(): void  $imports
     * @return list<string> taxpayers touched while deferring
     */
    public function deferring(callable $imports): array
    {
        $outer = $this->collected;
        $this->collected = [];

        try {
            $imports();

            return array_keys($this->collected);
        } finally {
            $this->collected = $outer === null ? null : [...$outer, ...$this->collected];
        }
    }

    public function onImported(SourceRecordsImported $event): void
    {
        if ($this->collected !== null) {
            foreach ($event->tins as $tin) {
                $this->collected[$tin] = true;
            }

            return;
        }

        AnalyseTaxpayers::dispatch($event->tins, 'import', $event->import->id);
    }
}
