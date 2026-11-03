<?php

namespace App\Domain\Briefs;

/** The AI's draft failed grounding or wording checks; the generator falls back to the template brief. */
class BriefRejected extends \RuntimeException
{
    /** @param list<string> $problems */
    public function __construct(public readonly array $problems)
    {
        parent::__construct(implode(' ', $problems));
    }
}
