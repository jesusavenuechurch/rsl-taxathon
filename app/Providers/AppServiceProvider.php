<?php

namespace App\Providers;

use App\Domain\Audit\AuditLog;
use App\Domain\Audit\AuthenticationAuditor;
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
    }

    public function boot(): void
    {
        Event::subscribe(AuthenticationAuditor::class);

        Gate::before(fn ($user) => $user->hasRole(Roles::SUPER_ADMIN) ? true : null);
    }
}
