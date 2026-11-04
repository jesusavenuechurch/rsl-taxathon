<?php

namespace App\Domain\Cases;

use App\Domain\Audit\AuditLog;
use App\Domain\Memory\DecisionRecorder;
use App\Domain\Notifications\NotificationSender;
use App\Models\Analysis\DataReviewItem;
use App\Models\Analysis\Finding;
use App\Models\Analysis\OfficerOutcome;
use App\Models\Cases\ReviewCase;
use App\Models\User;
use App\Support\Clock;
use App\Support\Settings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Four outcomes, five parts, never a plain dismiss (handoff rule 6). Concern
 * supported, explained, more information needed, or system error — recorded
 * per finding. Explained outcomes at or above the sign-off threshold wait for
 * a second signer before they reach company memory; system errors never
 * reach it at all (rule 9).
 */
class OutcomeWorkflow
{
    private const PARTS = ['observed_pattern', 'conclusion', 'evidence', 'conditions', 'reassessment_point'];

    public function __construct(
        private CaseWorkflow $workflow,
        private Clock $clock,
        private AuditLog $audit,
        private Settings $settings,
        private DecisionRecorder $decisions,
        private NotificationSender $notifications,
    ) {}

    /** @param  array<string, string>  $data  the five parts, outcome, and (system_error only) record_id/record_version */
    public function record(User $user, Finding $finding, array $data): OfficerOutcome
    {
        $case = $finding->case;
        if (! $case || ! $this->workflow->canAct($user, $case)) {
            throw new AuthorizationException('Only the team holding the case (or a team lead) can record an outcome.');
        }
        if ($finding->status !== Finding::OPEN) {
            throw new InvalidArgumentException('This finding already has an outcome.');
        }

        $outcome = $data['outcome'] ?? '';
        if (! array_key_exists($outcome, OfficerOutcome::LABELS)) {
            throw new InvalidArgumentException('Choose one of the four outcomes.');
        }
        foreach (self::PARTS as $part) {
            if (trim($data[$part] ?? '') === '') {
                throw new InvalidArgumentException('Fill in all five parts: observed pattern, conclusion, evidence, conditions and when to reassess.');
            }
        }
        if ($outcome === OfficerOutcome::SYSTEM_ERROR && trim($data['record_id'] ?? '') === '') {
            throw new InvalidArgumentException('Say which record is wrong.');
        }

        $requiresSignoff = $outcome === OfficerOutcome::EXPLAINED && $finding->exposure_estimate >= $this->threshold();

        return DB::transaction(function () use ($user, $finding, $case, $data, $outcome, $requiresSignoff) {
            $row = OfficerOutcome::create([
                'finding_id' => $finding->id,
                'case_id' => $case->id,
                'tin' => $finding->tin,
                'outcome' => $outcome,
                'observed_pattern' => trim($data['observed_pattern']),
                'conclusion' => trim($data['conclusion']),
                'evidence' => trim($data['evidence']),
                'conditions' => trim($data['conditions']),
                'reassessment_point' => trim($data['reassessment_point']),
                'exposure_at_decision' => $finding->exposure_estimate,
                'decided_by' => $user->id,
                'decided_at' => $this->clock->now(),
                'requires_second_signoff' => $requiresSignoff,
                'signoff_status' => $requiresSignoff ? OfficerOutcome::PENDING : null,
            ]);
            $row->update(['reference' => sprintf('OUT-%04d', $row->id)]);

            $finding->update(['status' => Finding::CONCLUDED, 'closed_at' => $this->clock->now()]);

            if ($outcome === OfficerOutcome::SYSTEM_ERROR) {
                $item = DataReviewItem::create([
                    'record_id' => strtoupper(trim($data['record_id'])),
                    'record_version' => (int) ($data['record_version'] ?? 1),
                    'finding_id' => $finding->id,
                    'raised_from_outcome_id' => $row->id,
                    'reason' => trim($data['conclusion']),
                ]);
                $item->update(['reference' => sprintf('DR-%04d', $item->id)]);
            } elseif (! $requiresSignoff) {
                $this->decisions->record($row->fresh(['decider', 'secondSigner']), $finding);
            }

            $case->events()->create([
                'type' => 'outcome_recorded', 'actor_id' => $user->id, 'actor_label' => $user->name,
                'note' => "{$finding->pattern_code}: {$row->outcomeLabel()}".($requiresSignoff ? ' (awaiting sign-off)' : ''),
                'occurred_at' => $this->clock->now(),
            ]);
            $this->audit->record('outcome_recorded', subjectType: 'officer_outcome', subjectRef: $row->reference, taxpayerTin: $finding->tin,
                payload: ['finding' => $finding->reference, 'outcome' => $outcome, 'requires_second_signoff' => $requiresSignoff]);

            $this->recomputeStage($case->fresh());

            if ($requiresSignoff) {
                $this->notifications->signoffNeeded($case->fresh());
            }

            return $row;
        });
    }

    public function signOff(User $signer, OfficerOutcome $outcome, bool $approve, string $note = ''): void
    {
        if (! $signer->can('second_signoff')) {
            throw new AuthorizationException('Only a team lead can give the second sign-off.');
        }
        if ((int) $outcome->decided_by === $signer->id) {
            throw new AuthorizationException('The second signer cannot be the officer who decided the outcome.');
        }
        if ($outcome->signoff_status !== OfficerOutcome::PENDING) {
            throw new InvalidArgumentException('This outcome is not waiting for sign-off.');
        }
        if (! $approve && trim($note) === '') {
            throw new InvalidArgumentException('Say why the outcome is being returned.');
        }

        DB::transaction(function () use ($signer, $outcome, $approve, $note) {
            $outcome->update([
                'signoff_status' => $approve ? OfficerOutcome::APPROVED : OfficerOutcome::RETURNED,
                'second_signer_id' => $signer->id,
                'signed_off_at' => $this->clock->now(),
                'signoff_note' => trim($note) ?: null,
            ]);

            $finding = $outcome->finding;
            if ($approve) {
                $this->decisions->record($outcome->fresh(['decider', 'secondSigner']), $finding);
            } else {
                $finding->update(['status' => Finding::OPEN, 'closed_at' => null]);
            }

            $this->audit->record('outcome_signed_off', subjectType: 'officer_outcome', subjectRef: $outcome->reference, taxpayerTin: $outcome->tin,
                payload: ['approved' => $approve, 'finding' => $finding->reference]);

            $this->recomputeStage($outcome->case->fresh());
        });
    }

    private function recomputeStage(ReviewCase $case): void
    {
        $case->loadMissing('findings');
        $stillOpen = $case->findings->contains(fn (Finding $f) => $f->status === Finding::OPEN);

        if ($stillOpen) {
            if ($case->stage === ReviewCase::STAGE_SIGNOFF) {
                $case->update(['stage' => ReviewCase::STAGE_DECISION]);
            }

            return;
        }

        $pending = OfficerOutcome::where('case_id', $case->id)->where('signoff_status', OfficerOutcome::PENDING)->exists();
        $case->update($pending
            ? ['stage' => ReviewCase::STAGE_SIGNOFF]
            : ['stage' => ReviewCase::STAGE_CLOSED, 'status' => ReviewCase::CLOSED, 'closed_at' => $this->clock->now()]);
    }

    private function threshold(): int
    {
        return (int) $this->settings->get('outcomes.second_signoff_threshold', 1_000_000);
    }
}
