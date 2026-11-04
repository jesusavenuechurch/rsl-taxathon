<?php

namespace App\Domain\Tour;

use App\Models\Analysis\FilingReview;
use App\Models\Cases\ReviewCase;

/**
 * The guided tour's 9 steps (officer-desk.md section 8), looked up by TIN
 * rather than hard-coded references, so the tour survives a demo reset.
 */
class TourSteps
{
    public const MOHALE = 'TN1000101-3';

    public const THABA = 'TN1000430-6';

    public const KATSE = 'TN1000656-9';

    public const QILOANE = 'TN1000769-4';

    public const LEBELO = 'TN1000872-0';

    /** @return array<int, array{title: string, body: string, actAs: ?string, url: \Closure}> */
    public static function all(): array
    {
        $case = fn (string $tin) => ReviewCase::where('tin', $tin)->latest('id')->first();
        $review = fn (string $tin) => FilingReview::where('tin', $tin)->latest('id')->first();

        return [
            1 => [
                'title' => 'An ordinary company',
                'body' => "Mohale Wholesale's filing review: nothing found, no case opened. Most companies look like this.",
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $review(self::MOHALE) ? route('reviews.show', $review(self::MOHALE)->reference) : route('home'),
            ],
            2 => [
                'title' => 'An exception, and the AI brief',
                'body' => "L. Thamae's desk: Thaba Trading needs a look. Open it, read the AI brief, then hand it to Special Sectors with a reason.",
                'actAs' => 'l.thamae@rsl.test',
                'url' => fn () => route('worklist'),
            ],
            3 => [
                'title' => 'The hand-off, and a request',
                'body' => "P. Mokoena's desk: Thaba is under \"Handed to us\", with the note it was referred with. Open it and send an information request. Then do the same for Katse Retail Group, in the same lane: its documents are what the next step needs.",
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $case(self::THABA) ? route('cases.show', $case(self::THABA)->reference) : route('worklist'),
            ],
            4 => [
                'title' => "Katse's documents arrive",
                'body' => 'As an administrator, advance the demo clock by 14 days. Documents released for Katse raise a new finding (P09, fringe benefits) at the top of the evidence ladder.',
                'actAs' => 'admin@system.local',
                'url' => fn () => route('home'),
            ],
            5 => [
                'title' => 'A human decision',
                'body' => "Back on Special Sectors' desk: open Katse and record an outcome on the new finding.",
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $case(self::KATSE) ? route('cases.show', $case(self::KATSE)->reference) : route('worklist'),
            ],
            6 => [
                'title' => 'Memory, tested against new figures',
                'body' => 'Qiloane Hardware: an earlier decision (DEC-0007) is shown as context, and the system states plainly that the new fees fall outside what it covered.',
                'actAs' => 'p.mokoena@rsl.test',
                'url' => fn () => $case(self::QILOANE) ? route('cases.show', $case(self::QILOANE)->reference) : route('worklist'),
            ],
            7 => [
                'title' => 'A figure that looks wrong',
                'body' => 'Lebelo Cash & Carry: an officer can report a figure as a system error. It goes to the data team, never into company memory.',
                'actAs' => 'l.thamae@rsl.test',
                'url' => fn () => $case(self::LEBELO) ? route('cases.show', $case(self::LEBELO)->reference) : route('worklist'),
            ],
            8 => [
                'title' => 'Second sign-off',
                'body' => "M. Sello's desk: a decision above the threshold is waiting for a second signer. Approve it, and it is written to memory.",
                'actAs' => 'm.sello@rsl.test',
                'url' => fn () => route('worklist'),
            ],
            9 => [
                'title' => 'Notifications, and Results',
                'body' => 'M. Sello can see audit activity too: this is every WhatsApp message a hand-off raised, with no taxpayer name, TIN or amount in it — see for yourself. Then click Results in the top nav: a summary of work done, not a scoreboard of taxpayers flagged.',
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
