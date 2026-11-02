<?php

use App\Domain\Records\Channels;
use App\Domain\Records\CsvImporter;

const RSL_DATA = __DIR__.'/../../../database/data/rsl';

function importChannel(string $key, ?string $path = null, bool $force = false)
{
    $channel = Channels::get($key);

    return app(CsvImporter::class)->import($channel, $path ?? RSL_DATA.'/'.$channel->file, $force);
}

function importAll(): void
{
    foreach (array_keys(Channels::all()) as $key) {
        importChannel($key);
    }
}

/** Copy a channel file to a temp path, changing it on the way. */
function editedCopy(string $file, callable $edit): string
{
    $path = tempnam(sys_get_temp_dir(), 'rsl').'.csv';
    file_put_contents($path, $edit(file_get_contents(RSL_DATA.'/'.$file)));

    return $path;
}
