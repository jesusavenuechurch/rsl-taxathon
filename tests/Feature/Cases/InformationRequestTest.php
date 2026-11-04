<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Cases\CaseWorkflow;
use App\Domain\Requests\RequestWorkflow;
use App\Livewire\Cases\CaseShow;
use App\Livewire\Cases\InformationRequestPanel;
use App\Models\Analysis\Finding;
use App\Models\AuditEvent;
use App\Models\Cases\ReleasedDocument;
use App\Models\Cases\ReviewCase;
use App\Models\User;
use App\Support\Clock;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->specialist = User::where('email', 'p.mokoena@rsl.test')->first();
    $this->katse = ReviewCase::where('tin', 'TN1000656-9')->sole();
});

it('prefills the request from the open findings\' documents to request, deduplicated', function () {
    $items = app(RequestWorkflow::class)->prefill($this->katse);
    $types = collect($items)->pluck('document_type');

    expect($types)->toContain('Housing and vehicle records')
        ->and($types->duplicates())->toBeEmpty();
});

it('sends a request, schedules delivery against the taxpayer\'s own documents, and moves the case to waiting', function () {
    $workflow = app(RequestWorkflow::class);
    $items = $workflow->prefill($this->katse);

    $request = $workflow->send($this->specialist, $this->katse, $items, 'Please send the following.');

    expect($request->reference)->toStartWith('IR-')
        ->and($this->katse->fresh()->stage)->toBe('waiting_taxpayer')
        ->and(AuditEvent::where('action', 'information_requested')->where('subject_ref', $request->reference)->exists())->toBeTrue();

    $housing = $request->items->firstWhere('document_type', 'Housing and vehicle records');
    expect($housing->delivery)->not->toBeNull()
        ->and($housing->delivery->due_at->toDateString())->toBe(app(Clock::class)->today()->addDays(14)->toDateString());
});

it('refuses an empty request and keeps a team that does not hold the case out', function () {
    $workflow = app(RequestWorkflow::class);

    expect(fn () => $workflow->send($this->specialist, $this->katse, [['document_type' => '  ']], ''))
        ->toThrow(InvalidArgumentException::class);

    $analyst = User::where('email', 'l.thamae@rsl.test')->first();
    expect(fn () => $workflow->send($analyst, $this->katse, [['document_type' => 'Housing and vehicle records']], ''))
        ->toThrow(AuthorizationException::class);
});

it('releases documents when due on a clock advance, moves the case to decision, and raises P09 at level 4', function () {
    $workflow = app(RequestWorkflow::class);
    $workflow->send($this->specialist, $this->katse, $workflow->prefill($this->katse), 'Please send the following.');

    expect(Finding::where('tin', 'TN1000656-9')->where('pattern_code', 'P09')->exists())->toBeFalse();

    app(Clock::class)->advance(14);

    $katse = $this->katse->fresh();
    expect($katse->stage)->toBe('decision')
        ->and(ReleasedDocument::where('case_id', $katse->id)->where('document_type', 'Housing and vehicle records')->exists())->toBeTrue();

    $p09 = Finding::where('tin', 'TN1000656-9')->where('pattern_code', 'P09')->where('status', 'open')->sole();
    expect($p09->evidence_level)->toBe(4)
        ->and($p09->wording)->toContain('The declared charge does not appear to match the evidence provided.')
        ->and($p09->wording)->toContain('show company-leased houses in Maseru West and two company vehicles for the managers')
        ->and($p09->wording)->not->toContain('raises P09')
        ->and(AuditEvent::where('action', 'released')->exists())->toBeTrue();
});

it('lets the officer decide now without waiting for every document', function () {
    $workflow = app(RequestWorkflow::class);
    $workflow->send($this->specialist, $this->katse, $workflow->prefill($this->katse), '');

    app(CaseWorkflow::class)->decideNow($this->specialist, $this->katse->fresh());

    expect($this->katse->fresh()->stage)->toBe('decision');
});

it('shows the requests panel on the case page, and the composer prefills from the panel itself', function () {
    Livewire::actingAs($this->specialist)->test(CaseShow::class, ['reference' => $this->katse->reference])
        ->assertSee('Requests and documents');

    Livewire::actingAs($this->specialist)->test(InformationRequestPanel::class, ['case' => $this->katse, 'canAct' => true])
        ->call('startComposing')
        ->assertSet('composing', true)
        ->assertSee('Prefilled from what the open findings suggest');

    expect(collect(Livewire::actingAs($this->specialist)->test(InformationRequestPanel::class, ['case' => $this->katse, 'canAct' => true])
        ->call('startComposing')->get('draftItems'))->pluck('document_type'))->toContain('Housing and vehicle records');
});
