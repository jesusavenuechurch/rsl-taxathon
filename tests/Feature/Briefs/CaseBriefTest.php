<?php

use App\Contracts\AI\AIProvider;
use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Briefs\BriefReview;
use App\Domain\Briefs\CaseBriefGenerator;
use App\Domain\Briefs\FactSheetBuilder;
use App\Livewire\Cases\CaseBriefPanel;
use App\Livewire\Cases\CaseShow;
use App\Models\AuditEvent;
use App\Models\Cases\ReviewCase;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;
use Tests\Fakes\FakeAIProvider;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->specialist = User::where('email', 'p.mokoena@rsl.test')->first();
    $this->thaba = ReviewCase::where('tin', 'TN1000430-6')->sole();
});

it('drafts a template brief when the case opens (the default fake provider is unreachable)', function () {
    $brief = $this->thaba->latestBrief();

    expect($brief)->not->toBeNull()
        ->and($brief->source)->toBe('template')
        ->and($brief->status)->toBe('draft')
        ->and($brief->draft['statements'])->not->toBeEmpty()
        ->and(AuditEvent::where('action', 'brief_generated')->where('subject_ref', $brief->reference)->exists())->toBeTrue();
});

it('accepts a grounded AI draft that cites only the fact sheet', function () {
    $sheet = app(FactSheetBuilder::class)->build($this->thaba->fresh());
    $id = $sheet->ids()[0];

    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode([
        'statements' => [['text' => 'The records show related activity worth a second look.', 'citations' => [$id]]],
        'next_step' => ['text' => 'Consider reviewing the open findings above.', 'citations' => [$id]],
    ])));

    $brief = app(CaseBriefGenerator::class)->generate($this->thaba->fresh());

    expect($brief->source)->toBe('ai')
        ->and($brief->provider)->toBe('fake/test-model')
        ->and($brief->rejection_reason)->toBeNull()
        ->and($brief->draft['statements'][0]['citations'])->toBe([$id]);
});

it('rejects a draft that invents a citation, and falls back to the template', function () {
    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode([
        'statements' => [['text' => 'Something happened here.', 'citations' => ['CIT-99999']]],
        'next_step' => ['text' => 'Look into it.', 'citations' => ['CIT-99999']],
    ])));

    $brief = app(CaseBriefGenerator::class)->generate($this->thaba->fresh());

    expect($brief->source)->toBe('template')
        ->and($brief->rejection_reason)->toContain('not in the fact sheet')
        ->and($brief->draft['statements'])->not->toBeEmpty();
});

it('rejects a draft that uses a word only an officer may use, and falls back to the template', function () {
    $sheet = app(FactSheetBuilder::class)->build($this->thaba->fresh());
    $id = $sheet->ids()[0];

    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode([
        'statements' => [['text' => 'This looks like evasion.', 'citations' => [$id]]],
        'next_step' => ['text' => 'Consider next steps.', 'citations' => [$id]],
    ])));

    $brief = app(CaseBriefGenerator::class)->generate($this->thaba->fresh());

    expect($brief->source)->toBe('template')
        ->and($brief->rejection_reason)->toContain('conclusion only an officer can reach');
});

it('rejects an uncited statement, and falls back to the template', function () {
    $sheet = app(FactSheetBuilder::class)->build($this->thaba->fresh());
    $id = $sheet->ids()[0];

    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode([
        'statements' => [['text' => 'Something notable happened.', 'citations' => []]],
        'next_step' => ['text' => 'Consider this.', 'citations' => [$id]],
    ])));

    $brief = app(CaseBriefGenerator::class)->generate($this->thaba->fresh());

    expect($brief->source)->toBe('template')
        ->and($brief->rejection_reason)->toContain('cites nothing');
});

it('falls back to the template when the provider cannot be reached', function () {
    app()->bind(AIProvider::class, fn () => FakeAIProvider::unavailable('Model asleep.'));

    $brief = app(CaseBriefGenerator::class)->generate($this->thaba->fresh());

    expect($brief->source)->toBe('template')
        ->and($brief->provider)->toBeNull()
        ->and($brief->rejection_reason)->toContain('Model asleep.');
});

it('falls back to the template when the response is not valid JSON', function () {
    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning('not json at all'));

    $brief = app(CaseBriefGenerator::class)->generate($this->thaba->fresh());

    expect($brief->source)->toBe('template');
});

it('lets the holding team approve a brief, and keeps a reviewed brief from being reviewed twice', function () {
    $review = app(BriefReview::class);
    $brief = $this->thaba->latestBrief();

    $review->approve($this->analyst, $brief);

    expect($brief->fresh())->status->toBe('approved')->reviewer_id->toBe($this->analyst->id)
        ->and(AuditEvent::where('action', 'brief_reviewed')->where('subject_ref', $brief->reference)->sole()->payload)
        ->toMatchArray(['case' => $this->thaba->reference, 'status' => 'approved']);

    expect(fn () => $review->approve($this->analyst, $brief->fresh()))->toThrow(InvalidArgumentException::class);
});

it('keeps another team from reviewing a brief', function () {
    $review = app(BriefReview::class);
    $brief = $this->thaba->latestBrief();

    expect(fn () => $review->approve($this->specialist, $brief))->toThrow(AuthorizationException::class);
});

it("edits a brief into the officer's own words", function () {
    $review = app(BriefReview::class);
    $brief = $this->thaba->latestBrief();

    $review->edit($this->analyst, $brief, 'My own reading of this case.');

    expect($brief->fresh())->status->toBe('edited')->final_text->toBe('My own reading of this case.');
});

it('rejects a brief with a reason', function () {
    $review = app(BriefReview::class);
    $brief = $this->thaba->latestBrief();

    $review->reject($this->analyst, $brief, 'Missing context, ask the AI to redraft.');

    expect($brief->fresh())->status->toBe('rejected')->review_note->toBe('Missing context, ask the AI to redraft.');
    expect(fn () => $review->reject($this->analyst, $brief, ''))->toThrow(InvalidArgumentException::class);
});

it('shows the brief on the case page', function () {
    $brief = $this->thaba->latestBrief();

    Livewire::actingAs($this->analyst)->test(CaseShow::class, ['reference' => $this->thaba->reference])
        ->assertSee('AI case brief')
        ->assertSee($brief->draft['statements'][0]['text']);
});

it('lets the holding team approve, edit or reject the brief from the panel', function () {
    Livewire::actingAs($this->analyst)->test(CaseBriefPanel::class, ['case' => $this->thaba, 'canAct' => true])
        ->call('approve')
        ->assertSee('Approved by');

    expect($this->thaba->fresh()->latestBrief()->status)->toBe('approved');
});

it('keeps the panel read-only for a team that cannot act on the case', function () {
    Livewire::actingAs($this->specialist)->test(CaseBriefPanel::class, ['case' => $this->thaba, 'canAct' => false])
        ->assertDontSee('Approve')
        ->call('approve');

    expect($this->thaba->fresh()->latestBrief()->status)->toBe('draft');
});
