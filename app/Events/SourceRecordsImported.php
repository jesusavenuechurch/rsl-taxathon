<?php

namespace App\Events;

use App\Models\SourceImport;
use Illuminate\Foundation\Events\Dispatchable;

/** New or revised records arrived; Phase 2 analyses the affected taxpayers. */
class SourceRecordsImported
{
    use Dispatchable;

    /** @param list<string> $tins taxpayers with new or revised rows */
    public function __construct(public SourceImport $import, public array $tins) {}
}
