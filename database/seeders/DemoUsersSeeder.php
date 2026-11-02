<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One demo officer per role. The first three names are the officers who
 * signed the earlier decisions in the mock data (DEC-0004/0007/0009).
 * Never runs in production unless RSL_SEED_DEMO_USERS=true.
 */
class DemoUsersSeeder extends Seeder
{
    public const USERS = [
        ['l.thamae@rsl.test', 'L. Thamae', Roles::RISK_ANALYST, Team::RISK_ANALYSIS, 'Risk analyst'],
        ['n.lebona@rsl.test', 'N. Lebona', Roles::REFUND_AUDITOR, Team::REFUND_AUDIT, 'Refund auditor'],
        ['p.mokoena@rsl.test', 'P. Mokoena', Roles::SPECIAL_SECTORS_AUDITOR, Team::SPECIAL_SECTORS, 'Special Sectors auditor'],
        ['m.sello@rsl.test', 'M. Sello', Roles::TEAM_LEAD, Team::SPECIAL_SECTORS, 'Team lead, Special Sectors'],
        ['t.ramone@rsl.test', 'T. Ramone', Roles::RULE_OWNER, Team::RISK_ANALYSIS, 'Rule owner, risk patterns'],
        ['k.molapo@rsl.test', 'K. Molapo', Roles::DATA_TEAM, Team::DATA, 'Data quality officer'],
        ['r.nthako@rsl.test', 'R. Nthako', Roles::AUDIT_VIEWER, null, 'Internal audit'],
    ];

    public function run(): void
    {
        if (! config('rsl.seed_demo_users')) {
            $this->command?->info('Demo users skipped (production). Set RSL_SEED_DEMO_USERS=true to seed them.');

            return;
        }

        $password = config('rsl.demo_user_password') ?: Str::password(16, symbols: false);
        $created = 0;

        foreach (self::USERS as [$email, $name, $role, $teamCode, $title]) {
            $user = User::firstOrCreate(['email' => $email], [
                'name' => $name,
                'password' => $password,
                'email_verified_at' => now(),
                'team_id' => $teamCode ? Team::where('code', $teamCode)->value('id') : null,
                'job_title' => $title,
            ]);
            $user->syncRoles([$role]);
            $created += $user->wasRecentlyCreated ? 1 : 0;
        }

        if ($created) {
            $this->command?->info("{$created} demo users created (*@rsl.test).");
            if (! config('rsl.demo_user_password')) {
                $this->command?->warn("Demo password for all of them (shown once): {$password}");
            }
        }
    }
}
