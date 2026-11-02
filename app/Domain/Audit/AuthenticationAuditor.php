<?php

namespace App\Domain\Audit;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;

/** Sign-ins and sign-outs belong in the audit log alongside case actions. */
class AuthenticationAuditor
{
    public function __construct(private AuditLog $audit) {}

    public function handleLogin(Login $event): void
    {
        $this->audit->record('signed_in', subjectType: 'user', subjectRef: (string) $event->user->getAuthIdentifier(), actor: $this->actor($event->user));
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user) {
            $this->audit->record('signed_out', subjectType: 'user', subjectRef: (string) $event->user->getAuthIdentifier(), actor: $this->actor($event->user));
        }
    }

    public function handleFailed(Failed $event): void
    {
        $this->audit->record('sign_in_failed', subjectType: 'user', payload: ['email' => $event->credentials['email'] ?? null], actor: AuditActor::system());
    }

    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Logout::class => 'handleLogout',
            Failed::class => 'handleFailed',
        ];
    }

    private function actor(mixed $user): AuditActor
    {
        return $user instanceof User ? AuditActor::user($user) : AuditActor::system();
    }
}
