<?php

namespace App\Domain\Analysis\Rules;

use App\Domain\Analysis\Wording\PyFormat;
use Illuminate\Support\Collection;

/** Small helpers shared by rules, so explanations read the same everywhere. */
trait Support
{
    protected static function m(float|int $amount): string
    {
        return PyFormat::money($amount);
    }

    protected static function pct(float|int $fraction): string
    {
        return PyFormat::percent($fraction);
    }

    /** @return list<string> */
    protected static function ids(Collection|array ...$groups): array
    {
        $ids = [];
        foreach ($groups as $rows) {
            foreach ($rows as $row) {
                $ids[] = is_string($row) ? $row : $row->record_id;
            }
        }

        return $ids;
    }
}
