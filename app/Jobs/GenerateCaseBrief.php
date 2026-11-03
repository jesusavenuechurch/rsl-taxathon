<?php

namespace App\Jobs;

use App\Domain\Briefs\CaseBriefGenerator;
use App\Models\Cases\ReviewCase;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drafts an AI case brief after a case opens or is referred. Queued so the
 * officer's page never waits on the model. A real model call can run well
 * past a worker's default 60s timeout, and opening then quickly referring a
 * case would otherwise queue two drafts for the same case.
 */
class GenerateCaseBrief implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /** Generous: a 20B model drafting a brief can take a couple of minutes. */
    public int $timeout = 360;

    /** A failed draft already falls back to the template inside the job; retrying would just draft twice. */
    public int $tries = 1;

    public function __construct(public ReviewCase $case) {}

    public function uniqueId(): string
    {
        return (string) $this->case->id;
    }

    /**
     * The lock releases as soon as the job finishes (success or failure);
     * this is only a ceiling in case a worker dies mid-job. Longer than
     * $timeout, so a slow real draft can never outlive its own lock.
     */
    public int $uniqueFor = 600;

    public function handle(CaseBriefGenerator $generator): void
    {
        $generator->generate($this->case);
    }
}
