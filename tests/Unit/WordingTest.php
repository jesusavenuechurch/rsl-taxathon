<?php

use App\Domain\Analysis\AnalysisContext;
use App\Domain\Analysis\Wording\PyFormat;
use App\Domain\Analysis\Wording\TemplateRenderer;
use App\Domain\Analysis\Wording\WordingPolicy;

it('rounds exact halves to even, like Python', function (float $x, int $expected) {
    expect(PyFormat::roundHalfEven($x))->toBe($expected);
})->with([[0.5, 0], [1.5, 2], [2.5, 2], [3.5, 4], [-2.5, -2], [2.4999, 2], [2.5001, 3], [296874.99999999994, 296875]]);

it('formats money the way rules.py does', function () {
    expect(PyFormat::money(2_000_000))->toBe('M2,000,000')
        ->and(PyFormat::money(-400_000))->toBe('a loss of M400,000')
        ->and(PyFormat::money(56_360.0))->toBe('M56,360')
        ->and(PyFormat::percent(0.2))->toBe('20%')
        ->and(PyFormat::percent(2.0))->toBe('200%')
        ->and(PyFormat::percent(0.1000488))->toBe('10%');
});

it('fills templates and refuses unknown placeholders', function () {
    expect(TemplateRenderer::render('Fees rose {g|percent} to {f|money}.', ['g' => 0.5, 'f' => 750_000]))->toBe('Fees rose 50% to M750,000.')
        ->and(TemplateRenderer::placeholders('{a} and {b|money} and {a}'))->toBe(['a', 'b']);

    expect(fn () => TemplateRenderer::render('{missing}', []))->toThrow(InvalidArgumentException::class)
        ->and(fn () => TemplateRenderer::render('{a|shout}', ['a' => 1]))->toThrow(InvalidArgumentException::class);
});

it('never lets the system conclude', function (string $text) {
    expect(WordingPolicy::violations($text, 4))->not->toBeEmpty();
})->with([
    'This is tax evasion.',
    'The arrangement is abusive.',
    'Possible fraud.',
    'The refund claim is valid.',
]);

it('does not let wording claim more than the evidence level', function () {
    expect(WordingPolicy::violations('These records appear inconsistent with each other.', 1))->not->toBeEmpty()
        ->and(WordingPolicy::violations('These records appear inconsistent with each other.', 2))->toBeEmpty()
        ->and(WordingPolicy::violations('The declared charge does not appear to match the evidence provided.', 2))->not->toBeEmpty()
        ->and(WordingPolicy::violations('The declared charge does not appear to match the evidence provided.', 4))->toBeEmpty()
        ->and(WordingPolicy::violations('Credits look lower risk; the claim is invalid?', 2))->toBeEmpty();
});

it('accepts every expected finding at its own evidence level', function () {
    $handle = fopen(__DIR__.'/../Fixtures/rsl/expected_system_findings.csv', 'r');
    $header = fgetcsv($handle, escape: '');
    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $row = array_combine($header, $row);
        expect(WordingPolicy::violations($row['system_wording'], (int) $row['evidence_level']))->toBe([], $row['finding_id']);
    }
});

it('derives the review window from a tax year', function () {
    expect(AnalysisContext::previousTaxYear('2025/26'))->toBe('2024/25')
        ->and(AnalysisContext::previousTaxYear('2000/01'))->toBe('1999/00')
        ->and(AnalysisContext::monthsOf('2025/26'))->toBe(['2025-04', '2025-05', '2025-06', '2025-07', '2025-08', '2025-09', '2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03']);
});
