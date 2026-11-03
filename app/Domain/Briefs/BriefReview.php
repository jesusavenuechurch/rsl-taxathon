<?php

namespace App\Domain\Briefs;

use App\Domain\Audit\AuditLog;
use App\Domain\Cases\CaseWorkflow;
use App\Models\Cases\CaseBrief;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Auth\Access\AuthorizationException;
use InvalidArgumentException;

/**
 * The officer's say over the AI's brief: approve it as drafted, edit it into
 * their own words, or reject it outright. The AI drafts; it never concludes
 * (checkpoint 5) — this is where a human always has the last word.
 */
class BriefReview
{
    public function __construct(private CaseWorkflow $workflow, private Clock $clock, private AuditLog $audit) {}

    public function approve(User $user, CaseBrief $brief): void
    {
        $this->authorize($user, $brief);
        $this->apply($brief, CaseBrief::APPROVED, $user);
    }

    public function edit(User $user, CaseBrief $brief, string $finalText): void
    {
        $this->authorize($user, $brief);
        if (trim($finalText) === '') {
            throw new InvalidArgumentException('Write the brief as you want it recorded.');
        }
        $this->apply($brief, CaseBrief::EDITED, $user, finalText: trim($finalText));
    }

    public function reject(User $user, CaseBrief $brief, string $reason): void
    {
        $this->authorize($user, $brief);
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Say why the brief is being rejected.');
        }
        $this->apply($brief, CaseBrief::REJECTED, $user, note: trim($reason));
    }

    private function authorize(User $user, CaseBrief $brief): void
    {
        if ($brief->isReviewed()) {
            throw new InvalidArgumentException('This brief was already reviewed.');
        }
        if (! $this->workflow->canAct($user, $brief->case)) {
            throw new AuthorizationException('Only the team holding the case (or a team lead) can review its brief.');
        }
    }

    private function apply(CaseBrief $brief, string $status, User $user, ?string $finalText = null, ?string $note = null): void
    {
        $brief->update([
            'status' => $status,
            'final_text' => $finalText,
            'review_note' => $note,
            'reviewer_id' => $user->id,
            'reviewed_at' => $this->clock->now(),
        ]);

        $this->audit->record('brief_reviewed', subjectType: 'case_brief', subjectRef: $brief->reference, taxpayerTin: $brief->tin,
            payload: ['case' => $brief->case->reference, 'status' => $status]);
    }
}
