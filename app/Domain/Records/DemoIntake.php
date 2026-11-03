<?php

namespace App\Domain\Records;

use App\Domain\Analysis\AnalysisScheduler;
use App\Domain\Memory\DecisionImporter;
use App\Jobs\AnalyseTaxpayers;
use App\Models\Analysis\Finding;
use App\Models\Cases\ReviewCase;
use App\Models\SourceRecords\Taxpayer;

/**
 * Plays the part of RSL's systems for the demo: a company's records "arrive"
 * from every channel (filtered from the mock files), and everything after
 * that happens exactly as it would in production: receipt, analysis, case,
 * notification. In a pilot this is replaced by RSL's real exports.
 */
class DemoIntake
{
    public function __construct(
        private CsvImporter $importer,
        private DecisionImporter $decisions,
        private AnalysisScheduler $scheduler,
    ) {}

    public static function dataPath(string $file = ''): string
    {
        return rtrim(config('rsl.mock_data_path'), '/').($file ? '/'.$file : '');
    }

    /** Companies in the mock registration file, for the "simulate" list: tin => name. */
    public static function mockCompanies(): array
    {
        return collect(self::rows(self::dataPath('companies.csv'))[1])->mapWithKeys(fn ($r) => [$r['tin'] => $r['name']])->all();
    }

    /** Registration, group register and earlier decisions: what RSL knows before any filing. */
    public function receiveBackground(): void
    {
        $this->scheduler->deferring(function () {
            foreach (['companies', 'group_register'] as $key) {
                $this->quietly(fn () => $this->importer->import(Channels::get($key), self::dataPath(Channels::get($key)->file)));
            }
        });
        $this->quietly(fn () => $this->decisions->import(self::dataPath('officer_decisions.csv'), self::dataPath('decision_conditions.json')));
    }

    /**
     * Every channel's rows for one company arrive, then the company is analysed.
     *
     * @return array{rows: int, channels: list<string>, findings: list<string>, case: ?ReviewCase}
     */
    public function receiveCompany(string $tin): array
    {
        $this->receiveBackground();
        $rows = 0;
        $channels = [];

        $this->scheduler->deferring(function () use ($tin, &$rows, &$channels) {
            foreach (Channels::all() as $channel) {
                if (! $channel->tinAttribute || $channel->key === 'companies') {
                    continue;
                }
                [$header, $all] = self::rows(self::dataPath($channel->file));
                $mine = array_values(array_filter($all, fn ($r) => $r[$channel->tinAttribute] === $tin));
                if (! $mine) {
                    continue;
                }
                $path = $this->write($header, $mine);
                $import = $this->quietly(fn () => $this->importer->import($channel, $path, filename: pathinfo($channel->file, PATHINFO_FILENAME)."-{$tin}.csv"));
                @unlink($path);
                if ($import) {
                    $rows += $import->rows_new + $import->rows_revised;
                    $channels[] = $channel->label;
                }
            }
        });

        AnalyseTaxpayers::dispatchSync([$tin], 'import');

        return [
            'rows' => $rows,
            'channels' => $channels,
            'findings' => Finding::open()->where('tin', $tin)->orderBy('pattern_code')->get()->map(fn ($f) => "{$f->pattern_code} {$f->title}")->all(),
            'case' => ReviewCase::active()->where('tin', $tin)->with('team')->first(),
        ];
    }

    /** Every mock file at once, analysed once at the end. */
    public function receiveEverything(): int
    {
        $this->receiveBackground();
        $tins = $this->scheduler->deferring(function () {
            foreach (Channels::all() as $channel) {
                $this->quietly(fn () => $this->importer->import($channel, self::dataPath($channel->file)));
            }
        });
        $tins = $tins ?: Taxpayer::current()->pluck('tin')->all();
        AnalyseTaxpayers::dispatchSync($tins, 'import');

        return count($tins);
    }

    private function quietly(callable $import)
    {
        try {
            return $import();
        } catch (AlreadyImported) {
            return null;
        }
    }

    /** @return array{0: list<string>, 1: list<array<string, string>>} */
    private static function rows(string $path): array
    {
        $handle = fopen($path, 'r');
        $header = array_map(fn ($h) => trim(preg_replace('/^\xEF\xBB\xBF/', '', $h)), fgetcsv($handle, escape: ''));
        $rows = [];
        while (($values = fgetcsv($handle, escape: '')) !== false) {
            if ($values !== [null] && count($values) === count($header)) {
                $rows[] = array_combine($header, $values);
            }
        }
        fclose($handle);

        return [$header, $rows];
    }

    private function write(array $header, array $rows): string
    {
        $path = tempnam(sys_get_temp_dir(), 'rsl-intake');
        $handle = fopen($path, 'w');
        fputcsv($handle, $header, escape: '');
        foreach ($rows as $row) {
            fputcsv($handle, array_values($row), escape: '');
        }
        fclose($handle);

        return $path;
    }
}
