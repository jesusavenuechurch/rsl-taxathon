<?php

/*
 * Acceptance test 1 of the handoff: the app produces the same pattern set per
 * company as test/expected_system_findings.csv, citing the same record IDs,
 * with the same wording and estimated exposure.
 */

use App\Models\Analysis\Finding;

beforeEach(fn () => analyseEverything());

it('raises exactly the expected findings', function () {
    $expected = collect(fixture('expected_system_findings.csv'))->map(fn ($r) => "{$r['tin']} {$r['pattern']}")->sort()->values();
    $actual = Finding::open()->get()->map(fn ($f) => "{$f->tin} {$f->pattern_code}")->sort()->values();

    expect($actual->all())->toBe($expected->all());
});

it('matches finding :dataset word for word', function (array $row) {
    $finding = Finding::open()->where('tin', $row['tin'])->where('pattern_code', $row['pattern'])->with('evidence')->sole();

    expect($finding->title)->toBe($row['pattern_name'])
        ->and($finding->evidence_level)->toBe((int) $row['evidence_level'])
        ->and($finding->wording)->toBe($row['system_wording'])
        ->and($finding->exposure_estimate)->toBe((int) $row['estimated_exposure'])
        ->and($finding->evidenceRecordIds())->toBe(explode(';', $row['evidence_record_ids']));
})->with(function () {
    foreach (fixture('expected_system_findings.csv') as $row) {
        yield "{$row['finding_id']} {$row['pattern']} ".strtok($row['name'], ' ') => [$row];
    }
});

it('flags nothing for the ordinary companies and the decoys, apart from Semonkong\'s lower-risk P07', function () {
    $quiet = ['TN1000101-3' => [], 'TN1000214-8' => [], 'TN1001314-6' => [], 'TN1001530-8' => [], 'TN1001427-2' => ['P07']];

    foreach ($quiet as $tin => $patterns) {
        expect(Finding::open()->where('tin', $tin)->pluck('pattern_code')->all())->toBe($patterns, $tin);
    }
    expect(Finding::open()->where('tin', 'TN1001427-2')->sole()->kind)->toBe('green_lane');
});
