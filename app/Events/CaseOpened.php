<?php

namespace App\Events;

use App\Models\Cases\ReviewCase;
use Illuminate\Foundation\Events\Dispatchable;

/** A new case landed on a team's worklist (WhatsApp hooks in here at checkpoint 6). */
class CaseOpened
{
    use Dispatchable;

    public function __construct(public ReviewCase $case) {}
}
