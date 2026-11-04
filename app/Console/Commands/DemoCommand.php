<?php

namespace App\Console\Commands;

use App\Domain\Cases\SignoffPolicy;
use App\Domain\Records\DemoIntake;
use App\Support\Settings;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'rsl:demo
        {--fresh : wipe the database first (migrate:fresh --seed): demo machines only}
        {--all : also receive every company\'s records now}';

    protected $description = 'Prepare a demo: RSL knows the registry and earlier decisions; filings arrive through Data intake';

    public function handle(DemoIntake $intake, Settings $settings): int
    {
        if ($this->option('fresh')) {
            // A hosted demo runs with APP_ENV=production; RSL_DEMO=true is what
            // marks the database as a disposable demo one.
            if (app()->isProduction() && ! config('rsl.demo')) {
                $this->error('Refusing to wipe a production database (set RSL_DEMO=true on a demo server).');

                return self::FAILURE;
            }
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        }

        // Rebuilding the demo opens every case at once: never message officers' phones for that.
        $muted = config('rsl.notifications_muted');
        config(['rsl.notifications_muted' => true]);

        try {
            $intake->receiveBackground();
            $this->info('Registry, group register and earlier decisions received.');

            // No story in the mock data reaches RSL's M1,000,000 default, so the demo
            // asks for a second sign-off on every "explained, legitimate" closure.
            // Every screen that relies on it says so (SignoffPolicy::demoNote()).
            $settings->set(SignoffPolicy::KEY, 0);

            if ($this->option('all')) {
                $this->info("Every company's records received and ".$intake->receiveEverything().' taxpayers analysed.');
            } else {
                $this->line('No filings yet. Sign in as an administrator and open Data intake to bring companies in one by one.');
            }
        } finally {
            config(['rsl.notifications_muted' => $muted]);
        }

        return self::SUCCESS;
    }
}
