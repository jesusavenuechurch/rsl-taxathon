<?php

namespace App\Console\Commands;

use App\Contracts\AI\AIProvider;
use App\Domain\Briefs\CaseBriefGenerator;
use App\Models\Cases\ReviewCase;
use Illuminate\Console\Command;

/** Draft (or redraft) a case's AI brief against the configured provider, and print it. For checking the real model by hand. */
class BriefCommand extends Command
{
    protected $signature = 'rsl:brief {case : Case reference, e.g. CASE-0001}';

    protected $description = 'Generate an AI case brief and print it, so the real model can be checked by hand';

    public function handle(CaseBriefGenerator $generator, AIProvider $provider): int
    {
        $case = ReviewCase::where('reference', strtoupper($this->argument('case')))->first();
        if (! $case) {
            $this->error('No such case.');

            return self::FAILURE;
        }

        $this->info("Provider: {$provider->name()} (".($provider->isAvailable() ? 'reachable' : 'NOT reachable').')');

        $brief = $generator->generate($case);

        $this->info("{$brief->reference}: source={$brief->source}".($brief->provider ? " provider={$brief->provider}" : ''));
        if ($brief->rejection_reason) {
            $this->warn("Fell back to the template because: {$brief->rejection_reason}");
        }

        $this->newLine();
        foreach ($brief->draft['statements'] ?? [] as $statement) {
            $this->line('- '.$statement['text'].'  ['.implode(', ', $statement['citations']).']');
        }
        if ($next = $brief->draft['next_step'] ?? null) {
            $this->newLine();
            $this->line('Next step: '.$next['text'].'  ['.implode(', ', $next['citations']).']');
        }

        return self::SUCCESS;
    }
}
