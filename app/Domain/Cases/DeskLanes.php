<?php

namespace App\Domain\Cases;

use App\Models\Analysis\DataReviewItem;
use App\Models\Analysis\Finding;
use App\Models\Analysis\OfficerOutcome;
use App\Models\Cases\ReviewCase;
use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Support\Collection;

/**
 * Builds each officer's desk: the lanes their role sees, left to right in
 * the order work flows, empty-state friendly (officer-desk.md section 3).
 * Lanes that depend on work not yet built (information requests, outcomes,
 * data review) are always present but stay empty until those steps land.
 */
class DeskLanes
{
    private const EAGER = ['taxpayer', 'team', 'assignee', 'findings', 'events.fromTeam', 'briefs', 'refundClaim'];

    /** @return list<array{title: string, cases: Collection, hint?: string}> */
    public function for(User $user): array
    {
        return match (true) {
            $user->hasRole(Roles::RISK_ANALYST) => $this->caseworkLanes($user, 'New to triage'),
            $user->hasRole(Roles::SPECIAL_SECTORS_AUDITOR) => $this->specialSectorsLanes($user),
            $user->hasRole(Roles::REFUND_AUDITOR) => $this->refundLanes($user),
            $user->hasRole(Roles::TEAM_LEAD) => $this->teamLeadLanes($user),
            $user->hasRole(Roles::DATA_TEAM) => $this->dataTeamLanes(),
            default => [],
        };
    }

    /** Risk analysis's lanes also cover any other caseworking team by the same shape. */
    private function caseworkLanes(User $user, string $firstLaneTitle): array
    {
        $teamId = $user->team_id;

        return [
            ['title' => $firstLaneTitle, 'cases' => $this->base()->where('team_id', $teamId)
                ->where('stage', ReviewCase::STAGE_TRIAGE)->whereNull('assigned_user_id')->ranked()->get()],
            ['title' => 'Working on', 'cases' => $this->base()->where('assigned_user_id', $user->id)
                ->whereIn('stage', [ReviewCase::STAGE_TRIAGE, ReviewCase::STAGE_DECISION])->ranked()->get()],
            ['title' => 'Waiting on taxpayer', 'cases' => $this->base()->where('team_id', $teamId)
                ->where('stage', ReviewCase::STAGE_WAITING_TAXPAYER)->ranked()->get()],
            ['title' => 'Ready to decide', 'cases' => $this->base()->where('team_id', $teamId)
                ->where('stage', ReviewCase::STAGE_DECISION)->ranked()->get()],
        ];
    }

    private function specialSectorsLanes(User $user): array
    {
        $teamId = $user->team_id;

        return [
            ['title' => 'Handed to us', 'hint' => 'Nothing yet. Cases arrive here when a Risk analyst refers one to Special Sectors: sign in as L. Thamae, open Thaba Trading and refer it.',
                'cases' => $this->base()->where('team_id', $teamId)
                    ->where('stage', ReviewCase::STAGE_SPECIALIST)->whereNull('assigned_user_id')->ranked()->get()],
            ['title' => 'Investigating', 'cases' => $this->base()->where('assigned_user_id', $user->id)
                ->where('stage', ReviewCase::STAGE_SPECIALIST)->ranked()->get()],
            ['title' => 'Waiting on taxpayer', 'cases' => $this->base()->where('team_id', $teamId)
                ->where('stage', ReviewCase::STAGE_WAITING_TAXPAYER)->ranked()->get()],
            // "with released documents" narrows once checkpoint 7 exists; stage alone for now.
            ['title' => 'Documents arrived', 'cases' => $this->base()->where('team_id', $teamId)
                ->where('stage', ReviewCase::STAGE_DECISION)->ranked()->get()],
        ];
    }

    private function refundLanes(User $user): array
    {
        $teamId = $user->team_id;
        $refunds = fn () => $this->base()->where('team_id', $teamId)->whereNotNull('refund_lane');

        return [
            ['title' => 'Lower risk', 'cases' => $refunds()->where('refund_lane', 'lower_risk')->ranked()->get()],
            ['title' => 'Needs review', 'cases' => $refunds()->where('refund_lane', 'needs_review')->ranked()->get()],
            ['title' => 'Verification due soon', 'cases' => $refunds()->get()
                ->sortBy(fn (ReviewCase $c) => $c->refundClaim?->verification_due)->values()],
        ];
    }

    private function teamLeadLanes(User $user): array
    {
        $pendingCaseIds = OfficerOutcome::where('signoff_status', OfficerOutcome::PENDING)->pluck('case_id')->unique();
        $returnedCaseIds = OfficerOutcome::where('signoff_status', OfficerOutcome::RETURNED)
            ->where('second_signer_id', $user->id)
            ->whereHas('finding', fn ($q) => $q->where('status', Finding::OPEN))
            ->pluck('case_id')->unique();

        return [
            ['title' => 'Waiting for my sign-off', 'hint' => 'Nothing yet. A closure as "Explained, legitimate" lands here once an officer records it: try Leribe Pharma as L. Thamae.', 'cases' => $this->base()->whereIn('id', $pendingCaseIds)->ranked()->get()],
            ['title' => 'Returned', 'cases' => $this->base()->whereIn('id', $returnedCaseIds)->ranked()->get()],
        ];
    }

    /** @return Collection<int, array{team: Team, counts: array<string, int>}> */
    public function teamOverview(): Collection
    {
        $counts = ReviewCase::active()->selectRaw('team_id, stage, count(*) as total')
            ->groupBy('team_id', 'stage')->get()->groupBy('team_id');

        return Team::ensureDefaults()->values()->map(fn ($team) => [
            'team' => $team,
            'counts' => $counts->get($team->id, collect())->pluck('total', 'stage')->all(),
        ]);
    }

    private function dataTeamLanes(): array
    {
        return [[
            'title' => 'Figures to check',
            'hint' => 'Nothing yet. An officer sends a figure here when it looks keyed wrongly: try Lebelo Cash & Carry as L. Thamae, and record "System error" on the VAT finding.',
            'cases' => collect(),
            'reviewItems' => DataReviewItem::where('status', DataReviewItem::OPEN)
                ->with(['finding.taxpayer', 'outcome.decider'])->orderBy('created_at')->get(),
        ]];
    }

    private function base()
    {
        return ReviewCase::active()->with(self::EAGER);
    }
}
