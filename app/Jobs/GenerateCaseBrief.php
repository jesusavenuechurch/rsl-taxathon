<?php

namespace App\Jobs;

use App\Domain\Briefs\CaseBriefGenerator;
use App\Models\Cases\ReviewCase;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Drafts an AI case brief after a case opens or is referred. Queued so the officer's page never waits on the model. */
class GenerateCaseBrief implements ShouldQueue
{
    use Queueable;

    public function __construct(public ReviewCase $case) {}

    public function handle(CaseBriefGenerator $generator): void
    {
        $generator->generate($this->case);
    }
}
