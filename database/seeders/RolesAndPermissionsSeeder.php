<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        // Permissions and further roles get added once the domain is defined.
        Role::firstOrCreate(['name' => 'super_admin']);

        if (User::role('super_admin')->exists()) {
            $this->command?->info('Super admin already exists.');

            return;
        }

        // Credentials come from the environment. Without a password, a
        // random one is generated and printed once.
        $email = env('SUPER_ADMIN_EMAIL', 'admin@system.local');
        $password = env('SUPER_ADMIN_PASSWORD') ?: Str::password(20);

        $user = User::firstOrCreate(
            ['email' => $email],
            ['name' => 'Super Admin', 'password' => Hash::make($password), 'email_verified_at' => now()],
        );
        $user->assignRole('super_admin');

        $this->command?->info("Super admin: {$email}");
        if (! env('SUPER_ADMIN_PASSWORD')) {
            $this->command?->warn("Generated password (shown once): {$password}");
        }
    }
}
