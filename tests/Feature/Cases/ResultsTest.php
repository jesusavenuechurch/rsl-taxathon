<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Cases\OutcomeWorkflow;
use App\Models\Analysis\OfficerOutcome;
use App\Models\Cases\ReviewCase;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->lebelo = ReviewCase::where('tin', 'TN1000872-0')->sole();
});

function parts(array $overrides = []): array
{
    return array_merge([
        'outcome' => OfficerOutcome::CONCERN_SUPPORTED,
        'observed_pattern' => 'x', 'conclusion' => 'Refer it.', 'evidence' => 'x', 'conditions' => 'x', 'reassessment_point' => 'x',
    ], $overrides);
}

it('counts only final outcomes, excludes pending sign-offs, and shows the closed case on the Results page', function () {
    $workflow = app(OutcomeWorkflow::class);
    foreach ($this->lebelo->findings->where('status', 'open') as $finding) {
        $workflow->record($this->analyst, $finding, parts());
    }

    $this->actingAs($this->analyst)->get('/results')->assertOk()
        ->assertSee('Concern supported, referred')
        ->assertSee($this->lebelo->reference)
        ->assertSee('Refer it.');
});

it('leaves a pending sign-off out of the results until it is approved', function () {
    $thaba = ReviewCase::where('tin', 'TN1000430-6')->sole();
    $finding = $thaba->findings->sortByDesc('exposure_estimate')->first(); // M540,000, above the 500k demo threshold
    app(OutcomeWorkflow::class)->record($this->analyst, $finding, parts(['outcome' => OfficerOutcome::EXPLAINED]));

    $this->actingAs($this->analyst)->get('/results')->assertOk()->assertDontSee('Explained, legitimate');

    $lead = User::where('email', 'm.sello@rsl.test')->first();
    app(OutcomeWorkflow::class)->signOff($lead, $finding->fresh()->outcome, true);

    $this->actingAs($this->analyst)->get('/results')->assertOk()->assertSee('Explained, legitimate');
});
