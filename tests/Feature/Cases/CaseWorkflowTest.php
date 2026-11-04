<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Cases\CaseWorkflow;
use App\Events\CaseReferred;
use App\Livewire\Cases\CaseShow;
use App\Models\AuditEvent;
use App\Models\Cases\ReviewCase;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->specialist = User::where('email', 'p.mokoena@rsl.test')->first();
    $this->thaba = ReviewCase::where('tin', 'TN1000430-6')->sole();
});

it('lets the analyst take a case and refer it to Special Sectors', function () {
    Event::fake([CaseReferred::class]);
    $workflow = app(CaseWorkflow::class);

    $workflow->take($this->analyst, $this->thaba);
    expect($this->thaba->fresh())->status->toBe('in_progress')->assigned_user_id->toBe($this->analyst->id);

    $workflow->refer($this->analyst, $this->thaba, Team::where('code', 'special_sectors')->first(), 'Group charges need a specialist review');

    $case = $this->thaba->fresh();
    expect($case->team->code)->toBe('special_sectors')
        ->and($case->assigned_user_id)->toBeNull()
        ->and($case->events->pluck('type')->all())->toBe(['opened', 'assigned', 'referred'])
        ->and($case->events->last()->note)->toBe('Group charges need a specialist review')
        ->and(AuditEvent::where('action', 'case_referred')->sole()->payload)->toMatchArray(['from' => 'risk_analysis', 'to' => 'special_sectors']);
    Event::assertDispatched(CaseReferred::class, fn ($e) => $e->to->code === 'special_sectors');
});

it('hands control to the receiving team', function () {
    $workflow = app(CaseWorkflow::class);
    $workflow->refer($this->analyst, $this->thaba, Team::where('code', 'special_sectors')->first(), 'Specialist review');

    expect($workflow->canAct($this->specialist, $this->thaba->fresh()))->toBeTrue()
        ->and($workflow->canAct($this->analyst, $this->thaba->fresh()))->toBeFalse();
});

it('keeps other teams and read-only roles out', function () {
    $workflow = app(CaseWorkflow::class);

    expect(fn () => $workflow->take(User::where('email', 'n.lebona@rsl.test')->first(), $this->thaba))->toThrow(AuthorizationException::class)
        ->and(fn () => $workflow->take(User::where('email', 'r.nthako@rsl.test')->first(), $this->thaba))->toThrow(AuthorizationException::class)
        ->and($workflow->canAct(User::where('email', 'm.sello@rsl.test')->first(), $this->thaba))->toBeTrue();
});

it('requires a reason to refer', function () {
    expect(fn () => app(CaseWorkflow::class)->refer($this->analyst, $this->thaba, Team::where('code', 'special_sectors')->first(), '  '))
        ->toThrow(InvalidArgumentException::class);
});

it('runs the same steps from the case page', function () {
    Livewire::actingAs($this->analyst)->test(CaseShow::class, ['reference' => $this->thaba->reference])
        ->assertSee('Your next step')
        ->call('take')
        ->set('referTo', 'special_sectors')->set('referReason', 'Group charges need a specialist review')->call('refer')
        ->assertHasNoErrors()
        ->assertSee('referred it from Risk analysis to Special Sectors')
        ->assertSee('This case is with Special Sectors');

    Livewire::actingAs($this->analyst)->test(CaseShow::class, ['reference' => $this->thaba->reference])
        ->set('referTo', '')->call('refer')->assertHasErrors('referTo');
});

it('shows the analyst\'s desk with new cases to triage, ranked', function () {
    $this->actingAs($this->analyst)->get('/worklist')->assertOk()
        ->assertSee('New to triage')
        ->assertSeeInOrder(['Highveld Retail Lesotho', 'Thaba Trading', 'Pitseng Beverages', 'Lebelo Cash', 'Leribe Pharma'])
        ->assertDontSee('Katse Retail');
});

it('moves a taken case from "New to triage" to "Working on" on the analyst\'s desk', function () {
    app(CaseWorkflow::class)->take($this->analyst, $this->thaba);

    $this->actingAs($this->analyst)->get('/worklist')->assertOk()
        ->assertSeeInOrder(['Working on', 'Thaba Trading']);
});

it('shows Katse on the Special Sectors desk, not the analyst\'s', function () {
    $katse = ReviewCase::where('tin', 'TN1000656-9')->sole();
    expect($katse->team->code)->toBe('special_sectors');

    $this->actingAs($this->specialist)->get('/worklist')->assertOk()
        ->assertSee('Handed to us')->assertSee('Katse Retail');
});

it('shows a case with its findings, memory and refund letter', function () {
    $this->actingAs($this->specialist);
    $qiloane = ReviewCase::where('tin', 'TN1000769-4')->sole();
    $this->get("/cases/{$qiloane->reference}")->assertOk()
        ->assertSee('P12 Profit squeeze')->assertSee('P03 Fees growing faster than revenue')
        ->assertSee('DEC-0007')->assertSee('Outside its conditions')
        ->assertSee('the team that decided DEC-0007');

    $maluti = ReviewCase::where('tin', 'TN1000327-1')->sole();
    $this->get("/cases/{$maluti->reference}")->assertOk()
        ->assertSee('Refund letter')->assertSee('RF-0007')->assertSee('Lower risk')
        ->assertDontSee('valid');

    expect(AuditEvent::where('action', 'opened')->where('subject_type', 'case')->count())->toBe(2);
});

it('opens a case at the triage stage, or specialist when routed straight there', function () {
    expect($this->thaba->stage)->toBe('triage');

    $qiloane = ReviewCase::where('tin', 'TN1000769-4')->sole();
    expect($qiloane->stage)->toBe('specialist');
});

it('moves the stage with the case when it is referred, and back again', function () {
    $workflow = app(CaseWorkflow::class);

    $workflow->refer($this->analyst, $this->thaba, Team::where('code', 'special_sectors')->first(), 'Specialist review');
    expect($this->thaba->fresh()->stage)->toBe('specialist');

    $workflow->refer($this->specialist, $this->thaba->fresh(), Team::where('code', 'risk_analysis')->first(), 'Back to triage');
    expect($this->thaba->fresh()->stage)->toBe('triage');
});

it('shows the journey strip on the case page, with Specialist faint until the case actually reaches it', function () {
    $this->actingAs($this->analyst)->get("/cases/{$this->thaba->reference}")
        ->assertOk()->assertSeeInOrder(['Received', 'Triage', 'Specialist', 'Waiting', 'Decision', 'Sign-off', 'Closed']);

    app(CaseWorkflow::class)->refer($this->analyst, $this->thaba, Team::where('code', 'special_sectors')->first(), 'Specialist review');
    $this->actingAs($this->specialist)->get("/cases/{$this->thaba->reference}")->assertOk()->assertSee('Specialist');
});
