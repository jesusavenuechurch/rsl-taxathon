<?php

namespace App\Jobs;

use App\Domain\Analysis\AnalysisEngine;
use App\Models\SourceImport;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Analyse taxpayers after their records arrive. Queued so imports stay fast. */
class AnalyseTaxpayers implements ShouldQueue
{
    use Queueable;

    /** @param list<string> $tins */
    public function __construct(public array $tins, public string $trigger = 'import', public ?int $sourceImportId = null) {}

    public function handle(AnalysisEngine $engine): void
    {
        $engine->run($this->tins, $this->trigger, $this->sourceImportId ? SourceImport::find($this->sourceImportId) : null);
    }
}
