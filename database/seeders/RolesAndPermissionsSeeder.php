<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\Roles;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Roles::PERMISSIONS as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }
        foreach (Roles::matrix() as $role => $permissions) {
            Role::firstOrCreate(['name' => $role])->syncPermissions($permissions);
        }

        $this->createSuperAdmin();
    }

    private function createSuperAdmin(): void
    {
        if (User::role(Roles::SUPER_ADMIN)->exists()) {
            $this->command?->info('Super admin already exists.');

            return;
        }

        // Credentials come from the environment. Without a password, a
        // random one is generated and printed once.
        $email = env('SUPER_ADMIN_EMAIL', 'admin@system.local');
        $password = env('SUPER_ADMIN_PASSWORD') ?: Str::password(20);

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => $password, 'email_verified_at' => now()],
        );
        $user->assignRole(Roles::SUPER_ADMIN);

        $this->command?->info("Super admin: {$email}");
        if (! env('SUPER_ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown once): {$password}");
        }
    }
}
