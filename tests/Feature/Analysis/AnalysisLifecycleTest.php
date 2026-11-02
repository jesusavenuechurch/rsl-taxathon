<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Jobs\AnalyseTaxpayers;
use App\Models\Analysis\AnalysisRun;
use App\Models\Analysis\FilingReview;
use App\Models\Analysis\Finding;
use App\Models\Analysis\PatternRule;
use App\Models\Analysis\PatternRuleVersion;
use App\Models\AuditEvent;
use App\Models\SourceRecords\VatReturn;
use Database\Seeders\RuleCatalogueSeeder;
use Illuminate\Support\Facades\Bus;

function review(string $tin): FilingReview
{
    return FilingReview::where('tin', $tin)->latest('id')->firstOrFail();
}

it('records all 14 rule checks for every taxpayer, fired or not', function () {
    analyseEverything();

    expect(FilingReview::count())->toBe(14);
    FilingReview::all()->each(fn ($r) => expect($r->checks()->count())->toBe(14));
});

it('saves the one-line summary for an ordinary company and creates no work', function () {
    analyseEverything();

    expect(review('TN1000101-3')->summary)->toBe(AnalysisEngine::NOTHING_FOUND)
        ->and(review('TN1000101-3')->anchor_label)->toBe('2025/26 income tax return')
        ->and(Finding::where('tin', 'TN1000101-3')->count())->toBe(0);
});

it('explains why the decoys did not fire', function (string $tin, string $code, string $result, string $explanation) {
    analyseEverything();
    $check = review($tin)->checks->firstWhere('pattern_code', $code);

    expect($check->result)->toBe($result)->and($check->explanation)->toContain($explanation);
})->with([
    'Tsenola: fees +40% vs revenue +35%' => ['TN1001314-6', 'P03', 'not_fired', 'Fees +40% against revenue +35%: gap 5 points'],
    'Semonkong: start-up losses without group charges' => ['TN1001427-2', 'P01', 'not_fired', '2 consecutive loss year(s) up to 2025/26, 0 of them with related-party charges'],
    'Mafeteng: interest is to a bank' => ['TN1001530-8', 'P11', 'not_fired', 'bank interest of M1,400,000 is not a related-party charge'],
    'P09 only from documents' => ['TN1000656-9', 'P09', 'not_applicable', 'Raised only from taxpayer documents'],
]);

it('gives each finding the rule\'s lawful explanations and questions to establish', function () {
    analyseEverything();
    $finding = Finding::open()->where('tin', 'TN1000430-6')->where('pattern_code', 'P03')->sole();

    expect($finding->checklist->where('type', 'lawful_explanation')->pluck('text')->all())
        ->toBe(['New systems', 'Restructuring', 'Expanded services from the parent'])
        ->and($finding->checklist->where('type', 'must_establish')->pluck('text')->all())
        ->toBe(['What services were delivered, and how was the charge set?'])
        ->and($finding->exposure_basis)->not->toBeNull();
});

it('pins each piece of evidence to the version of the row it saw', function () {
    analyseEverything();
    $link = Finding::open()->where('pattern_code', 'P14')->sole()->evidence->sole();

    expect($link->record_id)->toBe('VAT-00145')
        ->and($link->record_version)->toBe(1)
        ->and($link->row_hash)->toBe(VatReturn::where('record_id', 'VAT-00145')->value('row_hash'));
});

it('keeps the same findings when nothing has changed', function () {
    analyseEverything();
    $before = Finding::open()->pluck('reference', 'fingerprint')->all();

    app(AnalysisEngine::class)->run();

    expect(Finding::count())->toBe(17)
        ->and(Finding::open()->pluck('reference', 'fingerprint')->all())->toBe($before)
        ->and(Finding::open()->first()->last_review_id)->not->toBe(Finding::open()->first()->first_review_id);
});

it('withdraws a finding the corrected records no longer support', function () {
    analyseEverything();
    $p14 = Finding::open()->where('pattern_code', 'P14')->sole();

    // The data team re-keys Lebelo's March return; the import queues analysis of Lebelo only.
    importChannel('vat', editedCopy('vat_returns.csv', fn ($csv) => str_replace(',346250,519380,', ',346250,51938,', $csv)));

    expect($p14->fresh()->status)->toBe(Finding::WITHDRAWN)
        ->and(review('TN1000872-0')->anchor_record_id)->toBe('VAT-00145')
        ->and(AnalysisRun::latest('id')->first()->trigger)->toBe('import')
        ->and(Finding::open()->count())->toBe(16);
});

it('treats thresholds as configuration: a new rule version changes the outcome', function () {
    analyseEverything();

    $rule = PatternRule::where('code', 'P07')->first();
    $v1 = $rule->currentVersion;
    PatternRuleVersion::create([
        ...$v1->only(['pattern_rule_id', 'evidence_level', 'variants', 'lawful_explanations', 'must_establish', 'documents_to_request', 'exposure_basis']),
        'version' => 2,
        'parameters' => [...$v1->parameters, 'min_credit_months' => 7],
        'change_reason' => 'Test: only flag credits in 7+ months',
    ]);

    app(AnalysisEngine::class)->run(trigger: 'rule_change');

    expect(Finding::open()->where('pattern_code', 'P07')->count())->toBe(0)
        ->and(Finding::where('pattern_code', 'P07')->where('status', Finding::WITHDRAWN)->count())->toBe(3)
        ->and(review('TN1000327-1')->checks->firstWhere('pattern_code', 'P07')->explanation)->toContain('fires at 7');
});

it('never lets a rule version be edited in place', function () {
    analyseEverything();

    expect(fn () => PatternRule::where('code', 'P03')->first()->currentVersion->update(['parameters' => ['min_gap' => 0.9]]))
        ->toThrow(LogicException::class);
});

it('logs each analysis in the audit trail', function () {
    analyseEverything();

    $event = AuditEvent::where('action', 'analysed')->where('taxpayer_tin', 'TN1000985-5')->sole();
    expect($event->payload['patterns'])->toHaveCount(4);
});

it('queues analysis when a single file arrives', function () {
    Bus::fake([AnalyseTaxpayers::class]);
    importChannel('companies');

    Bus::assertDispatched(AnalyseTaxpayers::class, fn ($job) => count($job->tins) === 14 && $job->trigger === 'import');
});

it('analyses a batch once, after every file, from the console', function () {
    $this->seed(RuleCatalogueSeeder::class);

    $this->artisan('rsl:import --all')
        ->expectsOutputToContain('Analysed 14 taxpayer(s)')
        ->assertSuccessful();

    expect(AnalysisRun::count())->toBe(1)->and(Finding::open()->count())->toBe(17);
});
