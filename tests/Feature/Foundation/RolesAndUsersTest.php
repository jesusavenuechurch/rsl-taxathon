<?php

use App\Models\User;
use App\Support\Roles;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(fn () => config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'demo-pass']));

it('seeds one demo officer per role, in a team', function () {
    $this->seed(DatabaseSeeder::class);

    foreach (Roles::matrix() as $role => $_) {
        expect(User::role($role)->count())->toBe(1, "role {$role}");
    }
    expect(User::where('email', 'p.mokoena@rsl.test')->first()->team->name)->toBe('Special Sectors');
});

it('is safe to run twice', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);

    expect(User::role(Roles::SUPER_ADMIN)->count())->toBe(1)
        ->and(User::count())->toBe(count(DemoUsersSeeder::USERS) + 1);
});

it('skips demo users when switched off (production)', function () {
    config(['rsl.seed_demo_users' => false]);
    $this->seed(DatabaseSeeder::class);

    expect(User::count())->toBe(1);
});

it('gives each role only its own powers', function () {
    $this->seed(DatabaseSeeder::class);
    $user = fn (string $email) => User::where('email', $email)->first();

    expect($user('l.thamae@rsl.test')->can('record_outcomes'))->toBeTrue()
        ->and($user('l.thamae@rsl.test')->can('second_signoff'))->toBeFalse()
        ->and($user('m.sello@rsl.test')->can('second_signoff'))->toBeTrue()
        ->and($user('k.molapo@rsl.test')->can('record_outcomes'))->toBeFalse()
        ->and($user('k.molapo@rsl.test')->can('review_data_errors'))->toBeTrue()
        ->and($user('t.ramone@rsl.test')->can('configure_rules'))->toBeTrue()
        ->and($user('r.nthako@rsl.test')->can('view_audit_log'))->toBeTrue()
        ->and($user('r.nthako@rsl.test')->can('manage_cases'))->toBeFalse()
        ->and(User::role(Roles::SUPER_ADMIN)->first()->can('anything_at_all'))->toBeTrue();
});

it('registers every permission the matrix uses', function () {
    $used = collect(Roles::matrix())->flatten()->unique();

    expect($used->diff(Roles::PERMISSIONS)->all())->toBe([]);
});

it('seeds the super admin through the roles seeder', function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(User::role(Roles::SUPER_ADMIN)->count())->toBe(1);
});
