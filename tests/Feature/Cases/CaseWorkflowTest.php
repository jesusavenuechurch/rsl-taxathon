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

it('shows the worklist ranked for the officer\'s team', function () {
    $this->actingAs($this->analyst)->get('/worklist')->assertOk()
        ->assertSee('Risk analysis (5)')
        ->assertSeeInOrder(['Highveld Retail Lesotho', 'Thaba Trading', 'Pitseng Beverages', 'Lebelo Cash', 'Leribe Pharma'])
        ->assertDontSee('Katse Retail');

    $this->get('/worklist?tab=all')->assertSee('Katse Retail')->assertSee('Maluti Foods');
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
