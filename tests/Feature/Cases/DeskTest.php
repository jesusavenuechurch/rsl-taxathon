<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
});

it('shows the refund auditor lower-risk and needs-review lanes', function () {
    $auditor = User::where('email', 'n.lebona@rsl.test')->first();

    $this->actingAs($auditor)->get('/worklist')->assertOk()
        ->assertSee('Lower risk')->assertSee('Needs review')->assertSee('Verification due soon')
        ->assertSee('Maluti Foods')->assertSee('Semonkong Fresh Produce')
        ->assertSee('Senqu Imports');
});

it('shows the team lead a read-only overview of every team, and empty sign-off lanes', function () {
    $lead = User::where('email', 'm.sello@rsl.test')->first();

    $this->actingAs($lead)->get('/worklist')->assertOk()
        ->assertSee('Team overview')
        ->assertSee('Waiting for my sign-off')->assertSee('Nothing waiting here')
        ->assertSee('Risk analysis')->assertSee('Special Sectors')->assertSee('Refund audit');
});

it('shows the data team an empty figures-to-check lane', function () {
    $data = User::where('email', 'k.molapo@rsl.test')->first();

    $this->actingAs($data)->get('/worklist')->assertOk()
        ->assertSee('Figures to check')->assertSee('Nothing waiting here');
});
