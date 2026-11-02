<?php

namespace App\Domain\Analysis\Wording;

use RuntimeException;

class WordingRejected extends RuntimeException
{
    public function __construct(public readonly string $text, public readonly array $problems)
    {
        parent::__construct('Wording rejected: '.implode(' ', $problems));
    }
}
