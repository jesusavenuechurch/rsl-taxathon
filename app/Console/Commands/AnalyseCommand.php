<?php

namespace App\Console\Commands;

use App\Domain\Analysis\AnalysisEngine;
use App\Models\Analysis\AnalysisRun;
use App\Models\Analysis\Finding;
use Illuminate\Console\Command;

class AnalyseCommand extends Command
{
    protected $signature = 'rsl:analyse {tin?* : TINs to analyse (default: every registered taxpayer)}';

    protected $description = 'Run the pattern catalogue over taxpayers and update their findings';

    public function handle(AnalysisEngine $engine): int
    {
        self::printRun($this, $engine->run($this->argument('tin') ?: null, 'manual'));

        return self::SUCCESS;
    }

    public static function printRun(Command $command, AnalysisRun $run): void
    {
        $reviews = $run->reviews()->with('taxpayer')->orderBy('id')->get();
        $findings = Finding::open()->whereIn('tin', $reviews->pluck('tin'))->orderBy('pattern_code')->get()->groupBy('tin');

        $command->info("Analysed {$reviews->count()} taxpayer(s) (run #{$run->id}).");
        $command->table(['Company', 'Open findings'], $reviews->map(fn ($r) => [
            $r->taxpayer?->name ?? $r->tin,
            ($findings[$r->tin] ?? collect())->map(fn ($f) => $f->pattern_code.($f->kind === 'green_lane' ? ' (lower risk)' : ''))->implode(', ') ?: '–',
        ])->all());
    }
}
