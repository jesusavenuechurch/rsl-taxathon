<?php

namespace App\Console\Commands;

use App\Support\Clock;
use Illuminate\Console\Command;

class ClockCommand extends Command
{
    protected $signature = 'rsl:clock
        {action=show : show, advance or reset}
        {days? : number of days to advance}';

    protected $description = "Show or move the demo calendar (RSL's \"today\")";

    public function handle(Clock $clock): int
    {
        $action = $this->argument('action');

        try {
            match ($action) {
                'show' => null,
                'advance' => $clock->advance((int) ($this->argument('days') ?? 1)),
                'reset' => $clock->reset(),
                default => throw new \InvalidArgumentException("Unknown action [{$action}]: use show, advance or reset."),
            };
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $mode = $clock->isSimulated() ? "simulated, {$clock->offsetDays()} day(s) after ".config('rsl.today') : 'real time';
        $this->info("Today is {$clock->today()->format('l j F Y')} ({$mode}).");

        return self::SUCCESS;
    }
}
