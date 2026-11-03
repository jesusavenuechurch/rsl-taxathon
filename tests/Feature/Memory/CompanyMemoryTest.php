<?php

/*
 * Acceptance: show DEC-0007 as context for Qiloane and state that the new
 * fees fall outside its conditions. Memory informs, never exempts (rule 8);
 * system errors never enter memory (rule 9); conclusions keep five parts (rule 7).
 */

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Memory\DecisionImporter;
use App\Domain\Records\AlreadyImported;
use App\Models\Analysis\Finding;
use App\Models\Memory\Decision;
use App\Models\Memory\MemoryCheck;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RuleCatalogueSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function memoryFor(string $reference): MemoryCheck
{
    return MemoryCheck::whereHas('decision', fn ($q) => $q->where('reference', $reference))->latest('id')->firstOrFail();
}

it('loads the three earlier decisions with their five parts', function () {
    importDecisions();

    $d = Decision::where('reference', 'DEC-0007')->sole();
    expect(Decision::count())->toBe(3)
        ->and($d->tin)->toBe('TN1000769-4')
        ->and($d->pattern_code)->toBe('P12')
        ->and($d->outcome)->toBe(Decision::EXPLAINED)
        ->and($d->observed_pattern)->toStartWith('Net profit fell from M1.2m')
        ->and($d->conclusion)->toStartWith('New warehouse at Ha Tsolo')
        ->and($d->evidence)->toContain('Standard Lesotho Bank loan agreement')
        ->and($d->conditions)->toContain('related-party fees unchanged')
        ->and($d->reassessment_point)->toBe('2025/26 return')
        ->and($d->conditionList)->toHaveCount(4);
});

it('shows DEC-0007 for Qiloane and states the new fees fall outside its conditions', function () {
    analyseEverything();
    $check = memoryFor('DEC-0007');

    expect($check->result)->toBe(MemoryCheck::OUTSIDE)
        ->and($check->statement)->toContain('DEC-0007')
        ->and($check->statement)->toContain('The pattern has moved beyond what that explanation covers')
        ->and($check->statement)->toContain('Outside its conditions: Related-party fees (management fees and royalties): M1,600,000 (condition: about M400,000)')
        ->and($check->statement)->toContain('Still within: Depreciation')
        ->and($check->finding->pattern_code)->toBe('P12');
});

it('still raises Qiloane\'s findings: memory informs, never exempts', function () {
    analyseEverything();

    expect(Finding::open()->where('tin', 'TN1000769-4')->pluck('pattern_code')->sort()->values()->all())->toBe(['P03', 'P12']);
});

it('confirms the conditions still hold for Tsenola and Maluti', function () {
    analyseEverything();

    expect(memoryFor('DEC-0004')->result)->toBe(MemoryCheck::WITHIN)
        ->and(memoryFor('DEC-0004')->statement)->toContain('5.2% (condition: at most 6.0%)')
        ->and(memoryFor('DEC-0009')->result)->toBe(MemoryCheck::WITHIN)
        ->and(memoryFor('DEC-0009')->statement)->toContain('55% (condition: between 50% and 60%)')
        ->and(memoryFor('DEC-0009')->finding->pattern_code)->toBe('P07');
});

it('leaves conditions it cannot measure to the officer', function () {
    analyseEverything();
    $open = collect(memoryFor('DEC-0007')->condition_results)->where('result', 'not_checked')->pluck('text')->all();

    expect($open)->toBe(['Margin expected to recover within two years'])
        ->and(memoryFor('DEC-0007')->statement)->toContain('For the officer to judge: Margin expected to recover within two years');
});

it('never stores a system error in company memory', function () {
    importDecisions();
    $row = Decision::first()->only(['tin', 'tax_year_reviewed', 'pattern_code', 'pattern_label', 'observed_pattern', 'conclusion', 'evidence', 'conditions', 'reassessment_point', 'officer', 'decided_on', 'source']);

    expect(fn () => Decision::create([...$row, 'reference' => 'DEC-9999', 'outcome' => Decision::SYSTEM_ERROR]))
        ->toThrow(LogicException::class);
    expect(fn () => DB::table('decisions')->insert([...$row, 'reference' => 'DEC-9998', 'outcome' => 'system_error', 'decided_on' => '2026-01-01']))
        ->toThrow(QueryException::class, 'never into company memory');
});

it('skips system errors in an imported file and says so', function () {
    $path = editedCopy('officer_decisions.csv', fn ($csv) => str_replace('DEC-0004,TN1001314-6,2023/24,P03 Fees growing faster than revenue,Management fees rose 19% against revenue growth of 9%,Explained - legitimate',
        'DEC-0004,TN1001314-6,2023/24,P03 Fees growing faster than revenue,Management fees rose 19% against revenue growth of 9%,System error', $csv));

    $import = app(DecisionImporter::class)->import($path, RSL_DATA.'/decision_conditions.json');

    expect(Decision::pluck('reference')->sort()->values()->all())->toBe(['DEC-0007', 'DEC-0009'])
        ->and($import->warnings[0])->toContain('DEC-0004 is a system error');
});

it('treats decisions as part of the record', function () {
    importDecisions();

    expect(fn () => Decision::first()->update(['conclusion' => 'changed']))->toThrow(LogicException::class)
        ->and(fn () => importDecisions())->toThrow(AlreadyImported::class);
});

it('shows memory on the finding page', function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $finding = Finding::open()->where('tin', 'TN1000769-4')->where('pattern_code', 'P12')->sole();

    $this->actingAs(User::where('email', 'p.mokoena@rsl.test')->first())
        ->get("/findings/{$finding->reference}")->assertOk()
        ->assertSee('Company memory')->assertSee('DEC-0007')->assertSee('Outside its conditions')
        ->assertSee('Margin expected to recover within two years')
        ->assertSee('Standard Lesotho Bank loan agreement');
});

it('loads memory and analyses from the console', function () {
    $this->seed(RuleCatalogueSeeder::class);

    $this->artisan('rsl:import --all')->expectsOutputToContain('Company memory (officer_decisions.csv): 3 decisions, 3 new')->assertSuccessful();
    expect(MemoryCheck::count())->toBe(3);
});
