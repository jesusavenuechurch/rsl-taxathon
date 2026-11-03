<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Events\CaseOpened;
use App\Models\Analysis\Finding;
use App\Models\Cases\ReviewCase;
use Illuminate\Support\Facades\Event;

function caseFor(string $tin): ?ReviewCase
{
    return ReviewCase::active()->where('tin', $tin)->with('team')->first();
}

it('opens one case per company with findings, and none for the quiet ones', function () {
    analyseEverything();

    expect(ReviewCase::count())->toBe(10);
    foreach (['TN1000101-3', 'TN1000214-8', 'TN1001314-6', 'TN1001530-8'] as $quiet) {
        expect(caseFor($quiet))->toBeNull($quiet);
    }
    expect(Finding::open()->whereNull('case_id')->count())->toBe(0);
});

it('sets priority and first team as the answer key\'s journeys say', function (array $row) {
    analyseEverything();
    $case = caseFor($row['tin']);
    $firstTeam = match (true) {
        str_starts_with($row['journey'], 'Refund auditor') => 'refund_audit',
        str_starts_with($row['journey'], 'Special Sectors') => 'special_sectors',
        default => 'risk_analysis', // Risk analyst, Analyst -> ..., Any officer
    };

    expect($case->priority)->toBe(strtolower($row['priority']))
        ->and($case->team->code)->toBe($firstTeam)
        ->and($case->exposure_estimate)->toBe((int) $row['estimated_exposure_M']);
})->with(function () {
    foreach (fixture('answer_key.csv') as $row) {
        if ($row['priority'] !== 'None') {
            yield strtok($row['company'], ' ') => [$row];
        }
    }
});

it('types refund cases by lane and never calls them valid or refused', function () {
    analyseEverything();

    expect(caseFor('TN1000327-1'))->type->toBe('refund_review')->refund_lane->toBe('lower_risk')->refund_claim_record_id->toBe('RF-0007')
        ->and(caseFor('TN1000543-2'))->type->toBe('refund_review')->refund_lane->toBe('needs_review')
        ->and(caseFor('TN1001427-2'))->refund_lane->toBe('lower_risk')
        ->and(caseFor('TN1001201-1')->type)->toBe('information_gap')
        ->and(caseFor('TN1000872-0')->type)->toBe('data_check');
});

it('explains where it routed each case', function () {
    analyseEverything();

    expect(caseFor('TN1000769-4')->routing_reason)->toBe('Routed to Special Sectors: the team that decided DEC-0007 on this company.')
        ->and(caseFor('TN1000656-9')->routing_reason)->toContain('P08 needs specialist review');
});

it('does not open duplicate cases when analysis runs again', function () {
    analyseEverything();
    app(AnalysisEngine::class)->run();

    expect(ReviewCase::count())->toBe(10);
});

it('records a withdrawn finding on the case timeline', function () {
    analyseEverything();
    importChannel('vat', editedCopy('vat_returns.csv', fn ($csv) => str_replace(',346250,519380,', ',346250,51938,', $csv)));

    $case = caseFor('TN1000872-0');
    expect($case->events->pluck('type')->all())->toBe(['opened', 'findings_withdrawn']);
});

it('announces each new case, for notifications', function () {
    Event::fake([CaseOpened::class]);
    analyseEverything();

    Event::assertDispatchedTimes(CaseOpened::class, 10);
});
