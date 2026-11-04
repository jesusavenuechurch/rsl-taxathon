<?php

namespace App\Providers;

use App\Contracts\AI\AIProvider;
use App\Domain\Analysis\AnalysisScheduler;
use App\Domain\Audit\AuditLog;
use App\Domain\Audit\AuthenticationAuditor;
use App\Domain\Notifications\NotificationSender;
use App\Domain\Requests\DeliverDueDocuments;
use App\Events\CaseOpened;
use App\Events\CaseReferred;
use App\Events\ClockMoved;
use App\Events\SourceRecordsImported;
use App\Jobs\GenerateCaseBrief;
use App\Services\AI\Providers\OllamaProvider;
use App\Services\AI\Providers\OpenRouterProvider;
use App\Support\Clock;
use App\Support\Roles;
use App\Support\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped, not singleton: queue workers get a fresh instance per job,
        // so a clock advanced in the UI is seen by the next job.
        $this->app->scoped(Settings::class);
        $this->app->scoped(Clock::class);
        $this->app->scoped(AuditLog::class);

        // Singleton: a batch import defers analysis across many files.
        $this->app->singleton(AnalysisScheduler::class);

        // AI_PROVIDER chooses Ollama (inside RSL) or OpenRouter; see config/ai.php.
        $this->app->bind(AIProvider::class, fn () => config('ai.default') === 'openrouter'
            ? new OpenRouterProvider
            : new OllamaProvider);
    }

    public function boot(): void
    {
        Event::subscribe(AuthenticationAuditor::class);
        Event::listen(SourceRecordsImported::class, [AnalysisScheduler::class, 'onImported']);
        Event::listen(CaseOpened::class, fn (CaseOpened $e) => GenerateCaseBrief::dispatch($e->case));
        Event::listen(CaseReferred::class, fn (CaseReferred $e) => GenerateCaseBrief::dispatch($e->case));
        Event::listen(CaseOpened::class, fn (CaseOpened $e) => app(NotificationSender::class)->caseOpened($e->case));
        Event::listen(CaseReferred::class, fn (CaseReferred $e) => app(NotificationSender::class)->caseReferred($e->case, $e->to));
        Event::listen(ClockMoved::class, [DeliverDueDocuments::class, 'handle']);

        Gate::before(fn ($user) => $user->hasRole(Roles::SUPER_ADMIN) ? true : null);
    }
}
