<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            TeamsSeeder::class,
            RolesAndPermissionsSeeder::class,
            DemoUsersSeeder::class,
            RuleCatalogueSeeder::class,
        ]);
    }
}
