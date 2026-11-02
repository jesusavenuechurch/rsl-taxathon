<?php

use App\Events\ClockMoved;
use App\Models\AuditEvent;
use App\Support\Clock;
use Illuminate\Support\Facades\Event;

it('starts on the configured demo day', function () {
    expect(app(Clock::class)->today()->toDateString())->toBe('2026-11-02');
});

it('advances, persists and audits the move', function () {
    Event::fake([ClockMoved::class]);

    app(Clock::class)->advance(12);

    // A fresh instance (as the next request or queued job would get) sees it.
    app()->forgetScopedInstances();
    expect(app(Clock::class)->today()->toDateString())->toBe('2026-11-14');

    $event = AuditEvent::where('action', 'clock_advanced')->sole();
    expect($event->payload)->toMatchArray(['from' => '2026-11-02', 'to' => '2026-11-14', 'offset_days' => 12])
        ->and($event->occurred_at->toDateString())->toBe('2026-11-14');
    Event::assertDispatched(ClockMoved::class, fn ($e) => $e->to->toDateString() === '2026-11-14');
});

it('resets to the start day', function () {
    $clock = app(Clock::class);
    $clock->advance(5);
    $clock->reset();

    expect($clock->today()->toDateString())->toBe('2026-11-02')
        ->and(AuditEvent::where('action', 'clock_reset')->count())->toBe(1);
});

it('refuses to go backwards or jump absurdly', function (int $days) {
    app(Clock::class)->advance($days);
})->with([0, -3, 400])->throws(InvalidArgumentException::class);

it('follows real time when configured', function () {
    config(['rsl.clock' => 'real']);

    expect(app(Clock::class)->today()->toDateString())->toBe(now()->toDateString());
    expect(fn () => app(Clock::class)->advance(1))->toThrow(LogicException::class);
});

it('can be driven from the console', function () {
    $this->artisan('rsl:clock advance 3')->expectsOutputToContain('Thursday 5 November 2026')->assertSuccessful();
    $this->artisan('rsl:clock reset')->expectsOutputToContain('Monday 2 November 2026')->assertSuccessful();
});
