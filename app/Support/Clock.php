<?php

namespace App\Support;

use App\Domain\Audit\AuditLog;
use App\Events\ClockMoved;
use Carbon\CarbonImmutable;
use InvalidArgumentException;
use LogicException;

/**
 * The only source of "today" for domain code.
 *
 * In the demo, today is RSL_TODAY (2 November 2026) plus an offset an admin
 * can advance, so documents due "in 12 days" can be shown arriving. The time
 * of day follows the real clock. Never call now() in domain code; use this.
 */
class Clock
{
    public const OFFSET_KEY = 'clock.offset_days';

    public function __construct(private Settings $settings) {}

    public function isSimulated(): bool
    {
        return config('rsl.clock') !== 'real';
    }

    public function now(): CarbonImmutable
    {
        $real = CarbonImmutable::now();

        if (! $this->isSimulated()) {
            return $real;
        }

        return CarbonImmutable::parse(config('rsl.today'), $real->getTimezone())
            ->addDays($this->offsetDays())
            ->setTime($real->hour, $real->minute, $real->second);
    }

    public function today(): CarbonImmutable
    {
        return $this->now()->startOfDay();
    }

    public function offsetDays(): int
    {
        return (int) $this->settings->get(self::OFFSET_KEY, 0);
    }

    /** Move the demo calendar forward. Time never goes backwards except through reset(). */
    public function advance(int $days): CarbonImmutable
    {
        if (! $this->isSimulated()) {
            throw new LogicException('The clock follows real time (RSL_CLOCK=real) and cannot be advanced.');
        }
        if ($days < 1 || $days > 366) {
            throw new InvalidArgumentException('Advance by 1 to 366 days.');
        }

        return $this->moveTo($this->offsetDays() + $days, 'clock_advanced');
    }

    /** Back to RSL_TODAY. For resetting a demo, not for normal use. */
    public function reset(): CarbonImmutable
    {
        return $this->moveTo(0, 'clock_reset');
    }

    private function moveTo(int $offset, string $action): CarbonImmutable
    {
        $from = $this->today();
        $this->settings->set(self::OFFSET_KEY, $offset, audit: false);
        $to = $this->today();

        app(AuditLog::class)->record($action, subjectType: 'clock', payload: [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'offset_days' => $offset,
        ]);

        ClockMoved::dispatch($from, $to);

        return $to;
    }
}
