<?php

namespace App\Domain\Audit;

use App\Models\User;

/** Who did something: an officer, the system itself, or a taxpayer (e.g. sending a document). */
final class AuditActor
{
    private function __construct(
        public readonly string $type,
        public readonly ?int $id,
        public readonly string $label,
    ) {}

    public static function user(User $user): self
    {
        $roles = $user->getRoleNames()->implode(', ');

        return new self('user', $user->id, $roles ? "{$user->name} ({$roles})" : $user->name);
    }

    public static function system(string $label = 'System'): self
    {
        return new self('system', null, $label);
    }

    public static function taxpayer(string $tin): self
    {
        return new self('taxpayer', null, "Taxpayer {$tin}");
    }

    /**
     * The signed-in officer, or the system when nobody is (queued jobs,
     * console). While an administrator is acting as another officer (demo
     * mode), the label names both: "Super Admin acting as L. Thamae (risk_analyst)".
     */
    public static function current(): self
    {
        $user = auth()->user();

        if ($user instanceof User) {
            if (session()->has('acting_for') && (int) session('acting_for') === $user->id && ($real = User::find(session('acting_by')))) {
                $roles = $user->getRoleNames()->implode(', ');

                return new self('user', $user->id, "{$real->name} acting as {$user->name}".($roles ? " ({$roles})" : ''));
            }

            return self::user($user);
        }

        return self::system(app()->runningInConsole() ? 'System (console)' : 'System');
    }
}
