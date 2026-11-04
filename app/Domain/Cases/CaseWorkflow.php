<?php

namespace App\Domain\Cases;

use App\Domain\Audit\AuditLog;
use App\Events\CaseReferred;
use App\Models\Cases\ReviewCase;
use App\Models\Team;
use App\Models\User;
use App\Support\Clock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * What officers do with a case before concluding: take it on, refer it to
 * another team, add a note. Closing a case is an officer outcome (four
 * outcomes, five parts), never a plain "dismiss" (rule 6).
 */
class CaseWorkflow
{
    public function __construct(private Clock $clock, private AuditLog $audit) {}

    public function canAct(User $user, ReviewCase $case): bool
    {
        return $case->status !== ReviewCase::CLOSED
            && $user->can('manage_cases')
            && ($user->team_id === $case->team_id || $user->hasRole(['team_lead', 'super_admin']));
    }

    public function take(User $user, ReviewCase $case): void
    {
        $this->authorize($user, $case);

        DB::transaction(function () use ($user, $case) {
            $case->update(['assigned_user_id' => $user->id, 'status' => ReviewCase::IN_PROGRESS]);
            $this->event($case, $user, 'assigned', note: "{$user->name} took the case.");
            $this->audit->record('case_assigned', subjectType: 'case', subjectRef: $case->reference, taxpayerTin: $case->tin, payload: ['to' => $user->id]);
        });
    }

    public function refer(User $user, ReviewCase $case, Team $to, string $reason): void
    {
        $this->authorize($user, $case);
        if ($to->id === $case->team_id) {
            throw new InvalidArgumentException("The case is already with {$to->name}.");
        }
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Say why the case is being referred.');
        }

        $from = $case->team;
        $stage = $to->code === Team::SPECIAL_SECTORS ? ReviewCase::STAGE_SPECIALIST : ReviewCase::STAGE_TRIAGE;
        DB::transaction(function () use ($user, $case, $to, $from, $reason, $stage) {
            $case->update(['team_id' => $to->id, 'assigned_user_id' => null, 'status' => ReviewCase::OPEN, 'stage' => $stage]);
            $this->event($case, $user, 'referred', note: trim($reason), from: $from, to: $to);
            $this->audit->record('case_referred', subjectType: 'case', subjectRef: $case->reference, taxpayerTin: $case->tin,
                payload: ['from' => $from->code, 'to' => $to->code, 'reason' => trim($reason)]);
            DB::afterCommit(fn () => CaseReferred::dispatch($case->fresh(), $from, $to));
        });
    }

    /** The officer chooses to decide now, whether or not every requested document has arrived (section 2: "or none outstanding and the officer chooses Decide"). */
    public function decideNow(User $user, ReviewCase $case): void
    {
        $this->authorize($user, $case);
        if (! in_array($case->stage, [ReviewCase::STAGE_TRIAGE, ReviewCase::STAGE_SPECIALIST, ReviewCase::STAGE_WAITING_TAXPAYER], true)) {
            throw new InvalidArgumentException('This case is already at or past the decision stage.');
        }

        $case->update(['stage' => ReviewCase::STAGE_DECISION]);
        $this->event($case, $user, 'decide_now', note: "{$user->name} moved the case to decision.");
        $this->audit->record('case_moved_to_decision', subjectType: 'case', subjectRef: $case->reference, taxpayerTin: $case->tin);
    }

    public function note(User $user, ReviewCase $case, string $text): void
    {
        $this->authorize($user, $case);
        if (trim($text) === '') {
            throw new InvalidArgumentException('The note is empty.');
        }

        $this->event($case, $user, 'note', note: trim($text));
        $this->audit->record('case_note_added', subjectType: 'case', subjectRef: $case->reference, taxpayerTin: $case->tin);
    }

    private function authorize(User $user, ReviewCase $case): void
    {
        if (! $this->canAct($user, $case)) {
            throw new AuthorizationException('Only the team holding the case (or a team lead) can act on it.');
        }
    }

    private function event(ReviewCase $case, User $user, string $type, ?string $note = null, ?Team $from = null, ?Team $to = null): void
    {
        $case->events()->create([
            'type' => $type, 'actor_id' => $user->id, 'actor_label' => $user->name,
            'from_team_id' => $from?->id, 'to_team_id' => $to?->id, 'note' => $note,
            'occurred_at' => $this->clock->now(),
        ]);
    }
}
