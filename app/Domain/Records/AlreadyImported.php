<?php

namespace App\Domain\Records;

use App\Models\SourceImport;
use RuntimeException;

class AlreadyImported extends RuntimeException
{
    public function __construct(public readonly SourceImport $previous)
    {
        parent::__construct("This exact file was already imported on {$previous->received_at->toDateString()} (import #{$previous->id}).");
    }
}
