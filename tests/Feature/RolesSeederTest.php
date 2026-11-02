<?php

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

it('creates a super admin once', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(User::role('super_admin')->count())->toBe(1);
});
