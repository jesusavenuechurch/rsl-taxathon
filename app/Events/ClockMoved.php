<?php

namespace App\Events;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Events\Dispatchable;

/** Fired when the demo calendar moves (advance or reset); later phases deliver due taxpayer documents on it. */
class ClockMoved
{
    use Dispatchable;

    public function __construct(public CarbonImmutable $from, public CarbonImmutable $to) {}
}
