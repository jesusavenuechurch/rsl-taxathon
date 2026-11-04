<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Cases\OutcomeWorkflow;
use App\Livewire\Cases\OutcomeForm;
use App\Models\Analysis\DataReviewItem;
use App\Models\Analysis\OfficerOutcome;
use App\Models\AuditEvent;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\Decision;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->lead = User::where('email', 'm.sello@rsl.test')->first();
    $this->thaba = ReviewCase::where('tin', 'TN1000430-6')->sole();
    $this->lebelo = ReviewCase::where('tin', 'TN1000872-0')->sole();
});

function fiveParts(array $overrides = []): array
{
    return array_merge([
        'outcome' => OfficerOutcome::CONCERN_SUPPORTED,
        'observed_pattern' => 'Fees rose sharply against revenue.',
        'conclusion' => 'Refer for specialist review.',
        'evidence' => 'CIT-00015, CIT-00016.',
        'conditions' => 'Holds while the group structure is unchanged.',
        'reassessment_point' => 'Next annual return.',
    ], $overrides);
}

it('records concern supported immediately and writes it to company memory', function () {
    $finding = $this->thaba->findings->firstWhere('status', 'open');
    $workflow = app(OutcomeWorkflow::class);

    $row = $workflow->record($this->analyst, $finding, fiveParts());

    expect($row->reference)->toStartWith('OUT-')
        ->and($row->requires_second_signoff)->toBeFalse()
        ->and($finding->fresh()->status)->toBe('concluded')
        ->and(Decision::where('tin', 'TN1000430-6')->where('pattern_code', $finding->pattern_code)->where('source', 'officer_outcome')->exists())->toBeTrue()
        ->and(AuditEvent::where('action', 'outcome_recorded')->where('subject_ref', $row->reference)->exists())->toBeTrue();
});

it('requires second sign-off for "explained, legitimate" at or above the threshold, and keeps it out of memory until signed off', function () {
    $finding = $this->thaba->findings->where('status', 'open')->sortByDesc('exposure_estimate')->first();
    expect($finding->exposure_estimate)->toBeGreaterThanOrEqual(500_000);

    $workflow = app(OutcomeWorkflow::class);
    $row = $workflow->record($this->analyst, $finding, fiveParts(['outcome' => OfficerOutcome::EXPLAINED]));

    expect($row->requires_second_signoff)->toBeTrue()
        ->and($row->signoff_status)->toBe('pending')
        ->and(Decision::where('tin', 'TN1000430-6')->where('pattern_code', $finding->pattern_code)->exists())->toBeFalse();
});

it('lets a team lead sign off a pending outcome, recording the decision; a self-signoff or non-lead is refused', function () {
    $finding = $this->thaba->findings->where('status', 'open')->sortByDesc('exposure_estimate')->first();
    $workflow = app(OutcomeWorkflow::class);
    $row = $workflow->record($this->analyst, $finding, fiveParts(['outcome' => OfficerOutcome::EXPLAINED]));

    expect(fn () => $workflow->signOff($this->analyst, $row, true))->toThrow(AuthorizationException::class);

    $otherAnalyst = User::where('email', 'n.lebona@rsl.test')->first();
    expect(fn () => $workflow->signOff($otherAnalyst, $row, true))->toThrow(AuthorizationException::class);

    $workflow->signOff($this->lead, $row->fresh(), true);

    expect($row->fresh()->signoff_status)->toBe('approved')
        ->and(Decision::where('tin', 'TN1000430-6')->where('pattern_code', $finding->pattern_code)->where('second_signoff', $this->lead->name)->exists())->toBeTrue();
});

it('reopens the finding when a team lead returns an outcome, with no decision recorded', function () {
    $finding = $this->thaba->findings->where('status', 'open')->sortByDesc('exposure_estimate')->first();
    $workflow = app(OutcomeWorkflow::class);
    $row = $workflow->record($this->analyst, $finding, fiveParts(['outcome' => OfficerOutcome::EXPLAINED]));

    $workflow->signOff($this->lead, $row->fresh(), false, 'Not enough evidence yet.');

    expect($finding->fresh()->status)->toBe('open')
        ->and($row->fresh())->signoff_status->toBe('returned')->signoff_note->toBe('Not enough evidence yet.')
        ->and(Decision::where('tin', 'TN1000430-6')->where('pattern_code', $finding->pattern_code)->exists())->toBeFalse();

    expect(fn () => $workflow->signOff($this->lead, $row->fresh(), true))->toThrow(InvalidArgumentException::class);
});

it('sends a system error to data review, never to company memory', function () {
    $finding = $this->lebelo->findings->firstWhere('status', 'open');
    $workflow = app(OutcomeWorkflow::class);

    $row = $workflow->record($this->analyst, $finding, fiveParts([
        'outcome' => OfficerOutcome::SYSTEM_ERROR,
        'conclusion' => 'The amount was mis-keyed.',
        'record_id' => $finding->evidenceRecordIds()[0] ?? 'CIT-00001',
    ]));

    expect(DataReviewItem::where('raised_from_outcome_id', $row->id)->exists())->toBeTrue()
        ->and(Decision::where('tin', 'TN1000872-0')->where('source', 'officer_outcome')->exists())->toBeFalse();

    // Belt and suspenders: the model (and the DB trigger beneath it) refuse a system_error decision.
    expect(fn () => Decision::create([
        'reference' => 'DEC-9999', 'tin' => 'TN1000872-0', 'tax_year_reviewed' => '2025/26',
        'pattern_label' => 'x', 'outcome' => 'system_error', 'observed_pattern' => 'x', 'conclusion' => 'x',
        'evidence' => 'x', 'conditions' => 'x', 'reassessment_point' => 'x', 'officer' => 'x', 'decided_on' => today(), 'source' => 'officer_outcome',
    ]))->toThrow(LogicException::class);
});

it('closes the case once every finding is concluded and nothing is waiting on sign-off', function () {
    $workflow = app(OutcomeWorkflow::class);
    foreach ($this->lebelo->findings->where('status', 'open') as $finding) {
        $workflow->record($this->analyst, $finding, fiveParts());
    }

    $case = $this->lebelo->fresh();
    expect($case->status)->toBe('closed')
        ->and($case->stage)->toBe('closed');
});

it('shows the outcome form on the case page and records an outcome through it', function () {
    $finding = $this->thaba->findings->firstWhere('status', 'open');

    Livewire::actingAs($this->analyst)->test(OutcomeForm::class, ['finding' => $finding, 'canAct' => true])
        ->call('startForm')
        ->set('outcome', OfficerOutcome::CONCERN_SUPPORTED)
        ->set('conclusion', 'Refer for specialist review.')
        ->set('evidence', 'CIT-00015, CIT-00016.')
        ->set('conditions', 'Holds while unchanged.')
        ->set('reassessment_point', 'Next annual return.')
        ->call('submit')
        ->assertHasNoErrors();

    expect($finding->fresh()->status)->toBe('concluded');
});
