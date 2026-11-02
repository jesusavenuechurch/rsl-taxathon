<?php

namespace App\Console\Commands;

use App\Domain\Audit\AuditLog;
use Illuminate\Console\Command;

class AuditVerifyCommand extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Check the audit log has not been edited, reordered or had rows removed';

    public function handle(AuditLog $audit): int
    {
        $result = $audit->verify();

        if (! $result->intact) {
            $this->error("Audit log BROKEN at sequence {$result->brokenAtSequence}: {$result->problem}.");
            $this->line("The {$result->checked} entries before it are intact.");

            return self::FAILURE;
        }

        $this->info("Audit log intact: {$result->checked} entries.");
        if ($result->tipHash) {
            // Removing the newest rows can only be caught by comparing with a
            // tip hash recorded somewhere else (a ticket, an email, a printout).
            $this->line("Latest hash: {$result->tipHash}");
            $this->line('Record it outside the system to detect removal of the newest entries later.');
        }

        return self::SUCCESS;
    }
}
