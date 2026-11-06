<?php

use App\Contracts\AI\AIProvider;
use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Analysis\Wording\WordingPolicy;
use App\Domain\Chat\ChatAnswer;
use App\Domain\Chat\ChatRetriever;
use App\Domain\Chat\RecordsChat;
use App\Livewire\Chat\AskRecords;
use App\Models\AuditEvent;
use App\Models\Cases\ReviewCase;
use App\Models\ChatMessage;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;
use Tests\Fakes\FakeAIProvider;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->ask = fn (string $q) => app(RecordsChat::class)->ask($q, $this->analyst);
    $this->text = fn (ChatAnswer $a) => collect($a->statements)->pluck('text')->implode(' ');
    $this->cited = fn (ChatAnswer $a) => collect($a->statements)->pluck('citations')->flatten()->all();
});

it('answers the related-party question from the records: Thaba +200%, Qiloane +300%, Tsenola +40% under DEC-0004', function () {
    $answer = ($this->ask)('Which companies had a significant increase in related-party payments this year?');
    $text = ($this->text)($answer);

    expect($answer->source)->toBe('lookup')
        ->and($answer->statements)->toHaveCount(3)
        ->and($text)->toContain('Thaba Trading', '+200%')->toContain('Qiloane Hardware', '+300%')->toContain('Tsenola Distributors', '+40%')
        ->and($text)->not->toContain('Highveld')
        ->and(($this->cited)($answer))->toContain('DEC-0004')
        ->and($answer->note)->toContain('not evidence of wrongdoing');
});

it('says why Highveld Retail Lesotho was flagged, citing findings and records', function () {
    $answer = ($this->ask)('Why was Highveld Retail Lesotho flagged?');
    $text = ($this->text)($answer);

    expect($answer->statements)->not->toBeEmpty()
        ->and($text)->toContain('Highveld Retail')
        ->and(collect(($this->cited)($answer))->filter(fn ($id) => str_starts_with($id, 'F-')))->not->toBeEmpty()
        ->and(collect(($this->cited)($answer))->filter(fn ($id) => str_starts_with($id, 'CIT-')))->not->toBeEmpty();
});

it('sorts recurring VAT credits into lower risk and needs review', function () {
    $text = ($this->text)(($this->ask)('Which companies have recurring VAT credits, and which look lower risk?'));

    expect($text)->toContain('Maluti Foods')->toContain('Semonkong Fresh')->toContain('Senqu Imports')
        ->and($text)->toContain('lower risk')->toContain('needs review');
});

it('knows Qiloane Hardware was reviewed before, and that the new fees fall outside DEC-0007', function () {
    $answer = ($this->ask)('Has Qiloane Hardware been reviewed before?');

    expect(($this->text)($answer))->toContain('Yes')->toContain('Outside its conditions')
        ->and(($this->cited)($answer))->toContain('DEC-0007');
});

it('lists companies paying entities in low-tax jurisdictions: Pitseng Beverages', function () {
    $answer = ($this->ask)('Show me companies paying entities in low-tax jurisdictions.');

    expect(($this->text)($answer))->toContain('Pitseng Beverages')->toContain('Islandia');
});

it('keeps every direct-lookup answer inside the wording policy', function () {
    foreach (AskRecords::SUGGESTED as $q) {
        expect(WordingPolicy::violations(($this->text)(($this->ask)($q)), 4))->toBe([], $q);
    }
});

it('says plainly when the records cannot answer, instead of inventing', function () {
    $answer = ($this->ask)('What is the weather in Maseru?');

    expect($answer->source)->toBe('none')->and($answer->statements)->toBe([]);
});

it('uses a grounded AI answer, and records it', function () {
    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode(['statements' => [
        ['text' => 'Related-party payments at Thaba Trading rose by +200% between 2024/25 and 2025/26.', 'citations' => ['CIT-00001']],
    ]])));
    $retrieval = app(ChatRetriever::class)->retrieve('Which companies had a significant increase in related-party payments?');
    $cit = collect($retrieval->sheet->ids())->first(fn ($id) => str_starts_with($id, 'CIT-'));
    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode(['statements' => [
        ['text' => 'Related-party payments at Thaba Trading rose by +200%.', 'citations' => [$cit]],
    ]])));

    $answer = ($this->ask)('Which companies had a significant increase in related-party payments?');

    expect($answer->source)->toBe('ai')->and($answer->provider)->toBe('fake/test-model')
        ->and(ChatMessage::count())->toBe(1)
        ->and(AuditEvent::where('action', 'chat_asked')->count())->toBe(1);
});

it('rejects an AI answer with an invented citation, a forbidden word or an unsupported figure, and uses the lookup', function (array $statement) {
    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning(json_encode(['statements' => [$statement]])));

    $answer = ($this->ask)('Which companies had a significant increase in related-party payments this year?');

    expect($answer->source)->toBe('lookup')->and($answer->rejectionReason)->not->toBeNull()
        ->and($answer->statements)->toHaveCount(3);
})->with([
    'invented citation' => [['text' => 'Thaba Trading rose.', 'citations' => ['CIT-99999']]],
    'uncited' => [['text' => 'Thaba Trading rose.', 'citations' => []]],
    'forbidden word' => [['text' => 'Thaba Trading is guilty of fraud.', 'citations' => ['F-0001']]],
    'invented figure' => [['text' => 'Thaba Trading rose by 777%.', 'citations' => ['DEC-0004']]],
]);

it('falls back to the lookup when the model is unreachable or returns junk', function () {
    app()->bind(AIProvider::class, fn () => FakeAIProvider::returning('not json at all'));

    expect(($this->ask)('Has Qiloane Hardware been reviewed before?')->source)->toBe('lookup');
});

it('works as a page and as a case panel, for officers who may use it', function () {
    $this->actingAs($this->analyst);
    $this->get(route('ask'))->assertOk()->assertSee('Ask the records');

    Livewire::test(AskRecords::class)->call('ask', 'Show me companies paying entities in low-tax jurisdictions.')
        ->assertSee('Pitseng Beverages')->assertSee('Answered directly from the records');

    $case = ReviewCase::where('tin', 'TN1000430-6')->sole();
    $this->get(route('cases.show', $case->reference))->assertOk()->assertSee('Ask the records');

    $this->actingAs(User::where('email', 'k.molapo@rsl.test')->first());
    $this->get(route('ask'))->assertForbidden();
});

it('sends sign-out to the demo front page in demo mode, and to login otherwise', function () {
    config(['rsl.demo' => true]);
    $this->actingAs($this->analyst)->post(route('logout'))->assertRedirect(route('demo.front'));

    config(['rsl.demo' => false]);
    $this->actingAs($this->analyst)->post(route('logout'))->assertRedirect(route('login'));
});
