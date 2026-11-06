<?php

namespace App\Domain\Tour;

use App\Models\Analysis\FilingReview;
use App\Models\Cases\ReviewCase;

/**
 * The guided tour's steps (officer-desk.md section 8), looked up by TIN
 * rather than hard-coded references, so the tour survives a demo reset.
 */
class TourSteps
{
    public const MOHALE = 'TN1000101-3';

    public const THABA = 'TN1000430-6';

    public const KATSE = 'TN1000656-9';

    public const QILOANE = 'TN1000769-4';

    public const LEBELO = 'TN1000872-0';

    public const LERIBE = 'TN1001201-1';

    /** @return array<int, array{title: string, body: string, actAs: ?string, url: \Closure, clockDays?: int}> */
    public static function all(): array
    {
        $case = fn (string $tin) => ReviewCase::where('tin', $tin)->latest('id')->first();
        $review = fn (string $tin) => FilingReview::where('tin', $tin)->latest('id')->first();

        return [
            1 => [
                'title' => 'An ordinary company',
                'body' => "Mohale Wholesale's filing review: all 14 checks ran, nothing was found, no case was opened and nobody's desk filled up. Most companies look like this.",
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $review(self::MOHALE) ? route('reviews.show', $review(self::MOHALE)->reference) : route('worklist'),
            ],
            2 => [
                'title' => 'An exception, and a hand-off',
                'body' => "L. Thamae's desk. Open Thaba Trading, read the AI brief, then refer it to Special Sectors with a reason. Then open Leribe Pharma (an information gap, not suspicion) and request its documents.",
                'actAs' => 'l.thamae@rsl.test',
                'url' => fn () => route('worklist'),
            ],
            3 => [
                'title' => 'The case arrives on the next desk',
                'body' => "P. Mokoena's desk: Thaba is under \"Handed to us\", with L. Thamae's note. Open it and request documents. Do the same for Katse Retail Group: its documents are what step 5 needs.",
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => route('worklist'),
            ],
            4 => [
                'title' => 'Two weeks pass',
                'body' => 'Taxpayers take days to answer. Use the button below to move the demo calendar forward 14 days: the requested documents arrive, and each case moves from "Waiting on taxpayer" to "Documents arrived".',
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => route('worklist'),
                'clockDays' => 14,
            ],
            5 => [
                'title' => 'A human decision',
                'body' => "Katse's documents show housing and vehicles for the seconded managers, so a new finding appeared (fringe benefits). Consultancy or employment is the officer's call: record an outcome on it.",
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $case(self::KATSE) ? route('cases.show', $case(self::KATSE)->reference) : route('worklist'),
            ],
            6 => [
                'title' => 'An information gap, closed',
                'body' => "Back with L. Thamae: Leribe's financial statements and services agreement have arrived. If they explain the charges, record \"Explained, legitimate\" on the finding. That closure goes to a team lead for a second sign-off.",
                'actAs' => 'l.thamae@rsl.test',
                'url' => fn () => $case(self::LERIBE) ? route('cases.show', $case(self::LERIBE)->reference) : route('worklist'),
            ],
            7 => [
                'title' => 'Second sign-off',
                'body' => "M. Sello's desk: Leribe's decision is waiting for a second signer. Open it and sign off (or return it with a reason). Only then is it written to company memory.",
                'actAs' => 'm.sello@rsl.test',
                'url' => fn () => route('worklist'),
            ],
            8 => [
                'title' => 'Memory, tested against new figures',
                'body' => 'Qiloane Hardware: an earlier decision (DEC-0007) is shown as context, and the system states plainly that the new fees fall outside what it covered. Memory informs; it never exempts.',
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $case(self::QILOANE) ? route('cases.show', $case(self::QILOANE)->reference) : route('worklist'),
            ],
            9 => [
                'title' => 'A figure that looks wrong',
                'body' => 'Lebelo Cash & Carry: one VAT figure is about nine times its usual level, keyed from a paper return. An officer records it as a system error: it goes to the data team, never into company memory.',
                'actAs' => 'l.thamae@rsl.test',
                'url' => fn () => $case(self::LEBELO) ? route('cases.show', $case(self::LEBELO)->reference) : route('worklist'),
            ],
            10 => [
                'title' => 'Ask the records',
                'body' => 'A chat that answers only from the records, every statement cited. Try the suggested questions: which companies grew related-party payments, why Highveld was flagged, what was decided about Qiloane before. If the AI model is unreachable, the answer comes straight from the records.',
                'actAs' => 'l.thamae@rsl.test',
                'url' => fn () => route('ask'),
            ],
            11 => [
                'title' => 'Every hand-off, notified',
                'body' => 'Every WhatsApp message the hand-offs raised, with no taxpayer name, TIN or amount in any of them. Then open Results in the top bar: a summary of the work done, not a scoreboard of taxpayers flagged.',
                'actAs' => 'm.sello@rsl.test',
                'url' => fn () => route('notifications.sent'),
            ],
        ];
    }

    public static function count(): int
    {
        return count(self::all());
    }
}
