<?php

namespace App\Console\Commands;

use App\Domain\Records\AlreadyImported;
use App\Domain\Records\Channels;
use App\Domain\Records\CsvImporter;
use App\Domain\Records\ImportFailed;
use App\Models\SourceImport;
use Illuminate\Console\Command;

class ImportCommand extends Command
{
    protected $signature = 'rsl:import
        {channel? : companies, group_register, cit, vat, wht, lekuka, customs, paye or refund_claims (omit with --all)}
        {path? : the file; defaults to the channel\'s file in --dir}
        {--all : import every channel from --dir, in order}
        {--dir=database/data/rsl : folder holding the channel files}
        {--force : import even if this exact file was imported before}';

    protected $description = 'Receive RSL records from channel files (CSV)';

    public function handle(CsvImporter $importer): int
    {
        $dir = rtrim($this->option('dir'), '/');

        if ($this->option('all')) {
            $jobs = collect(Channels::all())->map(fn ($c) => [$c, "{$dir}/{$c->file}"]);
        } else {
            $channel = Channels::get((string) $this->argument('channel'));
            if (! $channel) {
                $this->error('Name a channel ('.implode(', ', array_keys(Channels::all())).') or use --all.');

                return self::FAILURE;
            }
            $jobs = collect([[$channel, $this->argument('path') ?? "{$dir}/{$channel->file}"]]);
        }

        $failed = false;
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
