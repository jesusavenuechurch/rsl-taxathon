<?php

namespace App\Domain\Cases;

use App\Domain\Audit\AuditActor;
use App\Domain\Audit\AuditLog;
use App\Events\CaseOpened;
use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\Decision;
use App\Models\SourceRecords\RefundClaim;
use App\Models\Team;
use App\Support\Clock;
use App\Support\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Turns a review's findings into work. A company with open findings gets one
 * active case (new findings join it); a company with none gets no case, only
 * its filing review. Type, priority and team follow configurable settings.
 */
class CaseRouter
{
    public const DEFAULTS = [
        'cases.priority_high_from' => 500_000,
        'cases.priority_medium_from' => 100_000,
        'cases.special_sectors_patterns' => ['P08', 'P09'],
    ];

    public function __construct(private Clock $clock, private Settings $settings, private AuditLog $audit) {}

    public function route(FilingReview $review): ?ReviewCase
    {
        $tin = $review->tin;
        $open = Finding::open()->where('tin', $tin)->get();
        $case = ReviewCase::active()->where('tin', $tin)->first();

        if (! $case && $open->isEmpty()) {
            return null;
        }

        $now = $this->clock->now();
        $unassigned = $open->whereNull('case_id');

        if (! $case) {
            [$team, $reason] = $this->team($tin, $open);
            $case = ReviewCase::create([
                'tin' => $tin,
                ...$this->classify($open),
                'refund_claim_record_id' => $this->refundClaim($tin, $open),
                'status' => ReviewCase::OPEN,
                'stage' => $this->stageFor($team),
                'team_id' => $team->id,
                'routing_reason' => $reason,
                'opened_from_review_id' => $review->id,
                'opened_at' => $now,
            ]);
            $case->update(['reference' => sprintf('CASE-%04d', $case->id)]);
            Finding::whereIn('id', $unassigned->pluck('id'))->update(['case_id' => $case->id]);

            $this->event($case, 'opened', $now, toTeam: $team, note: $reason, data: ['findings' => $unassigned->pluck('reference')->all()]);
            $this->audit->record('case_opened', subjectType: 'case', subjectRef: $case->reference, taxpayerTin: $tin,
                actor: AuditActor::system(), payload: ['team' => $team->code, 'priority' => $case->priority, 'findings' => $unassigned->pluck('reference')->all()]);

            DB::afterCommit(fn () => CaseOpened::dispatch($case->fresh()));

            return $case;
        }

        if ($unassigned->isNotEmpty()) {
            Finding::whereIn('id', $unassigned->pluck('id'))->update(['case_id' => $case->id]);
            $this->event($case, 'findings_added', $now, data: ['findings' => $unassigned->pluck('reference')->all()]);
        }

        $withdrawn = Finding::where('case_id', $case->id)->where('status', '!=', Finding::OPEN)
            ->where('closed_at', '>=', $review->reviewed_at)->pluck('reference');
        if ($withdrawn->isNotEmpty()) {
            $this->event($case, 'findings_withdrawn', $now, note: 'A later analysis no longer supports these findings.', data: ['findings' => $withdrawn->all()]);
        }

        $all = Finding::open()->where('case_id', $case->id)->get();
        if ($all->isNotEmpty()) {
            $case->update($this->classify($all));
        }

        return $case;
    }

    /** @return array{type: string, refund_lane: ?string, priority: string, exposure_estimate: int} */
    private function classify(Collection $findings): array
    {
        $kinds = $findings->pluck('kind')->unique();
        $hasRefund = $findings->contains('pattern_code', 'P07');

        $type = match (true) {
            $hasRefund => ReviewCase::REFUND_REVIEW,
            $kinds->every(fn ($k) => $k === 'information_gap') => ReviewCase::INFORMATION_GAP,
            $kinds->every(fn ($k) => $k === 'data_quality') => ReviewCase::DATA_CHECK,
            default => ReviewCase::RISK_REVIEW,
        };

        // Estimates for one company often describe the same money (Highveld's
        // interest appears in P04 and P11), so the case shows the largest, not the sum.
        $exposure = (int) $findings->max('exposure_estimate');

        return [
            'type' => $type,
            'refund_lane' => $hasRefund ? ($kinds->every(fn ($k) => $k === 'green_lane') ? 'lower_risk' : 'needs_review') : null,
            'priority' => $this->priority($exposure),
            'exposure_estimate' => $exposure,
        ];
    }

    private function priority(int $exposure): string
    {
        return match (true) {
            $exposure >= $this->setting('cases.priority_high_from') => 'high',
            $exposure >= $this->setting('cases.priority_medium_from') => 'medium',
            default => 'low',
        };
    }

    /** @return array{0: Team, 1: string} */
    private function team(string $tin, Collection $findings): array
    {
        $byCode = Team::ensureDefaults();

        $known = Decision::current()->where('tin', $tin)->whereNotNull('team_code')->latest('decided_on')->first();
        if ($known && isset($byCode[$known->team_code])) {
            return [$byCode[$known->team_code], "Routed to {$byCode[$known->team_code]->name}: the team that decided {$known->reference} on this company."];
        }
        if ($findings->contains('pattern_code', 'P07')) {
            return [$byCode[Team::REFUND_AUDIT], 'Routed to Refund audit: recurring VAT credits.'];
        }
        $specialist = $findings->pluck('pattern_code')->intersect($this->setting('cases.special_sectors_patterns'));
        if ($specialist->isNotEmpty()) {
            return [$byCode[Team::SPECIAL_SECTORS], 'Routed to Special Sectors: '.$specialist->implode(', ').' needs specialist review.'];
        }

        return [$byCode[Team::RISK_ANALYSIS], 'Routed to Risk analysis for triage.'];
    }

    /** The refund letter a refund review is about: the latest one not yet paid. */
    private function refundClaim(string $tin, Collection $findings): ?string
    {
        if (! $findings->contains('pattern_code', 'P07')) {
            return null;
        }

        return RefundClaim::current()->where('tin', $tin)->where('status', '!=', 'Paid')->orderByDesc('letter_date')->orderByDesc('record_id')->value('record_id');
    }

    private function stageFor(Team $team): string
    {
        return $team->code === Team::SPECIAL_SECTORS ? ReviewCase::STAGE_SPECIALIST : ReviewCase::STAGE_TRIAGE;
    }

    private function setting(string $key): mixed
    {
        return $this->settings->get($key, self::DEFAULTS[$key]);
    }

    private function event(ReviewCase $case, string $type, $at, ?Team $toTeam = null, ?string $note = null, ?array $data = null): void
    {
        $case->events()->create([
            'type' => $type, 'actor_label' => 'System', 'to_team_id' => $toTeam?->id,
            'note' => $note, 'data' => $data, 'occurred_at' => $at,
        ]);
    }
}
