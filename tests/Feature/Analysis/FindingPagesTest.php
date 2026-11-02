<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'demo-pass']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->actingAs(User::where('email', 'p.mokoena@rsl.test')->first());
});

it('lists findings by company, ordinary companies with their summary', function () {
    $this->get('/findings')->assertOk()
        ->assertSee('Highveld Retail Lesotho (Pty) Ltd')
        ->assertSee('P04 Related-party payments without matching withholding tax')
        ->assertSee('Lower risk')
        ->assertSee('No significant unusual changes were identified');
});

it('shows the five parts of a finding and links to every cited record', function () {
    $finding = Finding::open()->where('tin', 'TN1000430-6')->where('pattern_code', 'P03')->sole();

    $this->get("/findings/{$finding->reference}")->assertOk()
        ->assertSee('Related-party fees rose 200% against revenue growth of 20%.')
        ->assertSee('Possible lawful explanations')->assertSee('Expanded services from the parent')
        ->assertSee('What services were delivered, and how was the charge set?')
        ->assertSee('M540,000')->assertSee('never an assessed amount')
        ->assertSee('/records/CIT-00015?v=1', false)->assertSee('/records/CIT-00016?v=1', false)
        ->assertSee('not a conclusion');

    expect(AuditEvent::where('action', 'opened')->where('subject_ref', $finding->reference)->exists())->toBeTrue();
});

it('shows all 14 checks on a filing review, with reasons', function () {
    $review = FilingReview::where('tin', 'TN1001314-6')->sole();

    $this->get("/reviews/{$review->reference}")->assertOk()
        ->assertSee('All 14 checks')
        ->assertSee('gap 5 points');
});

it('refuses users without access', function () {
    $this->actingAs(User::factory()->create())->get('/findings')->assertForbidden();
});
