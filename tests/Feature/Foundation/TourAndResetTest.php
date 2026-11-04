<?php

use App\Livewire\Auth\DemoFront;
use App\Livewire\Tour\GuidedTour;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Artisan;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    config(['rsl.demo' => true]);
});

it('starts the guided tour signed in as the first step\'s officer', function () {
    Livewire::test(DemoFront::class)->call('startTour')->assertRedirect();

    $this->assertAuthenticatedAs(User::where('email', 'p.mokoena@rsl.test')->first());
    $this->get('/home')->assertOk()->assertSee('Step 1 of 9');
});

it('advances the tour and switches officer when a step needs it', function () {
    Livewire::test(DemoFront::class)->call('startTour');
    $this->get('/home'); // apply the sign-in, same as real use

    Livewire::actingAs(User::where('email', 'p.mokoena@rsl.test')->first())
        ->test(GuidedTour::class)->call('next')->assertRedirect();

    // step 2 is L. Thamae's
    $this->get('/worklist');
    $this->assertAuthenticatedAs(User::where('email', 'l.thamae@rsl.test')->first());
});

it('dismisses the tour', function () {
    session(['tour_step' => 3]);
    Livewire::actingAs(User::where('email', 'p.mokoena@rsl.test')->first())
        ->test(GuidedTour::class)->call('dismiss');

    expect(session('tour_step'))->toBeNull();
});

it('resets the demo from the front page, rate limited', function () {
    // migrate:fresh (VACUUM) cannot run inside Pest's wrapping transaction on
    // SQLite — the same reason the existing rsl:demo console test never
    // passes --fresh. Faking the command here checks resetDemo()'s own
    // logic (which command, which options, the rate limit); the command's
    // own behaviour is covered by hand against the owner's real MySQL (it is
    // literally the `rsl:demo --fresh` used throughout this project's own dev loop).
    Artisan::shouldReceive('call')->once()->with('rsl:demo', ['--fresh' => true, '--all' => true]);

    Livewire::test(DemoFront::class)->call('resetDemo')->assertHasNoErrors();

    Livewire::test(DemoFront::class)->call('resetDemo')->assertHasErrors('reset');
});

// render()'s own abort_unless(config('rsl.demo'), 404) already covers "unreachable outside demo mode"
// for the whole component (see DemoModeTest::'it is unreachable outside demo mode'); resetDemo()'s
// guard can never be reached on its own once that fires first.
