<?php

namespace App\Console\Commands;

use App\Domain\Records\DemoIntake;
use Illuminate\Console\Command;

class DemoCommand extends Command
{
    protected $signature = 'rsl:demo
        {--fresh : wipe the database first (migrate:fresh --seed): demo machines only}
        {--all : also receive every company\'s records now}';

    protected $description = 'Prepare a demo: RSL knows the registry and earlier decisions; filings arrive through Data intake';

    public function handle(DemoIntake $intake): int
    {
        if ($this->option('fresh')) {
            if (app()->isProduction()) {
                $this->error('Refusing to wipe a production database.');

                return self::FAILURE;
            }
            $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        }

        $intake->receiveBackground();
        $this->info('Registry, group register and earlier decisions received.');

        if ($this->option('all')) {
            $this->info("Every company's records received and ".$intake->receiveEverything().' taxpayers analysed.');
        } else {
            $this->line('No filings yet. Sign in as an administrator and open Data intake to bring companies in one by one.');
        }

        return self::SUCCESS;
    }
}
