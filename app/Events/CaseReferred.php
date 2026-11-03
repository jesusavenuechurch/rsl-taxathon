<?php

namespace App\Events;

use App\Models\Cases\ReviewCase;
use App\Models\Team;
use Illuminate\Foundation\Events\Dispatchable;

/** A case moved to another team's worklist. */
class CaseReferred
{
    use Dispatchable;

    public function __construct(public ReviewCase $case, public ?Team $from, public Team $to) {}
}
