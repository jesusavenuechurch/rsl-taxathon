<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Analysis\AnalysisScheduler;
use App\Domain\Records\Channels;
use App\Domain\Records\CsvImporter;
use App\Models\Analysis\AnalysisRun;
use Database\Seeders\RuleCatalogueSeeder;

const RSL_DATA = __DIR__.'/../../../database/data/rsl';

function importChannel(string $key, ?string $path = null, bool $force = false)
{
    $channel = Channels::get($key);

    return app(CsvImporter::class)->import($channel, $path ?? RSL_DATA.'/'.$channel->file, $force);
}

/** Receive every file without analysing (like rsl:import --all --no-analyse). */
function importAll(): void
{
    app(AnalysisScheduler::class)->deferring(function () {
        foreach (array_keys(Channels::all()) as $key) {
            importChannel($key);
        }
    });
}

/** Copy a channel file to a temp path, changing it on the way. */
function editedCopy(string $file, callable $edit): string
{
    $path = tempnam(sys_get_temp_dir(), 'rsl').'.csv';
    file_put_contents($path, $edit(file_get_contents(RSL_DATA.'/'.$file)));

    return $path;
}

const RSL_FIXTURES = __DIR__.'/../../Fixtures/rsl';

/** Rows of an answer-key CSV (tests only; the app never reads tests/Fixtures). */
function fixture(string $name): array
{
    $handle = fopen(RSL_FIXTURES.'/'.$name, 'r');
    $header = array_map(fn ($h) => preg_replace('/^\xEF\xBB\xBF/', '', $h), fgetcsv($handle, escape: ''));
    $rows = [];
    while (($values = fgetcsv($handle, escape: '')) !== false) {
        if ($values !== [null]) {
            $rows[] = array_combine($header, $values);
        }
    }
    fclose($handle);

    return $rows;
}

/** Seed the rule catalogue, receive every file and analyse everyone once. */
function analyseEverything(): AnalysisRun
{
    test()->seed(RuleCatalogueSeeder::class);
    importAll();

    return app(AnalysisEngine::class)->run();
}
