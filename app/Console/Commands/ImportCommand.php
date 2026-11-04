<?php

namespace App\Console\Commands;

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Analysis\AnalysisScheduler;
use App\Domain\Memory\DecisionImporter;
use App\Domain\Records\AlreadyImported;
use App\Domain\Records\Channels;
use App\Domain\Records\CsvImporter;
use App\Domain\Records\ImportFailed;
use App\Domain\Requests\TaxpayerResponsePool;
use App\Models\Memory\Decision;
use App\Models\SourceImport;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'rsl:import
        {channel? : companies, group_register, cit, vat, wht, lekuka, customs, paye, refund_claims or officer_decisions (omit with --all)}
        {path? : the file; defaults to the channel\'s file in --dir}
        {--all : import every channel from --dir, in order}
        {--dir=database/data/rsl : folder holding the channel files}
        {--force : import even if this exact file was imported before}
        {--no-analyse : receive the records without analysing them}';

    protected $description = 'Receive RSL records from channel files (CSV)';

    public function handle(CsvImporter $importer, AnalysisScheduler $scheduler, AnalysisEngine $engine, DecisionImporter $decisions, TaxpayerResponsePool $responses): int
    {
        $dir = rtrim($this->option('dir'), '/');
        $loadMemory = $this->option('all') || $this->argument('channel') === DecisionImporter::CHANNEL;

        if ($this->option('all')) {
            $jobs = collect(Channels::all())->map(fn ($c) => [$c, "{$dir}/{$c->file}"]);
        } elseif ($loadMemory) {
            $jobs = collect();
        } else {
            $channel = Channels::get((string) $this->argument('channel'));
            if (! $channel) {
                $this->error('Name a channel ('.implode(', ', array_keys(Channels::all())).') or use --all.');

                return self::FAILURE;
            }
            $jobs = collect([[$channel, $this->argument('path') ?? "{$dir}/{$channel->file}"]]);
        }

        $failed = false;
        // Analyse once after all files, on complete data, not once per file.
        $tins = $scheduler->deferring(function () use ($jobs, $importer, &$failed) {
            foreach ($jobs as [$channel, $path]) {
                try {
                    $this->report($importer->import($channel, $path, (bool) $this->option('force')));
                } catch (AlreadyImported $e) {
                    $this->line("<comment>{$channel->label}</comment>: skipped. {$e->getMessage()} Use --force to re-check it.");
                } catch (ImportFailed $e) {
                    $this->error("{$channel->label}: rejected, nothing saved. {$e->getMessage()}");
                    $failed = true;
                }
            }
        });

        // Earlier officer decisions: loaded before analysis so memory checks see them.
        if ($loadMemory) {
            try {
                $import = $decisions->import($this->argument('path') && ! $this->option('all') ? $this->argument('path') : "{$dir}/officer_decisions.csv",
                    "{$dir}/decision_conditions.json", (bool) $this->option('force'));
                $this->info("Company memory (officer_decisions.csv): {$import->rows_total} decisions, {$import->rows_new} new, {$import->rows_unchanged} already known.");
                foreach ($import->warnings as $warning) {
                    $this->warn("  ! {$warning}");
                }
                if ($import->rows_new) {
                    $tins = array_values(array_unique([...$tins, ...Decision::where('source_import_id', $import->id)->pluck('tin')->all()]));
                }
            } catch (AlreadyImported $e) {
                $this->line("<comment>Company memory</comment>: skipped. {$e->getMessage()}");
            } catch (ImportFailed $e) {
                $this->error("Company memory: rejected, nothing saved. {$e->getMessage()}");
                $failed = true;
            }
        }

        if ($loadMemory) {
            $loaded = $responses->import("{$dir}/taxpayer_responses.csv");
            $this->info("Taxpayer document pool (taxpayer_responses.csv): {$loaded} document(s) on file, invisible until requested.");
        }

        if ($tins && ! $this->option('no-analyse')) {
            $this->newLine();
            AnalyseCommand::printRun($this, $engine->run($tins, 'import'));
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    private function report(SourceImport $import): void
    {
        $channel = $import->channelDefinition();
        $this->info("{$channel->label} ({$import->filename}): {$import->rows_total} rows, {$import->rows_new} new, {$import->rows_revised} revised, {$import->rows_unchanged} unchanged.");
        foreach ($import->warnings as $warning) {
            $this->warn("  ! {$warning}");
        }
    }
}
