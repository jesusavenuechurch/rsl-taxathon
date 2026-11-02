<?php

use App\Livewire\Admin\DemoClock;
use App\Livewire\Auth\Login;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'demo-pass']);
    $this->seed(DatabaseSeeder::class);
});

it('sends guests to the sign-in page', function () {
    $this->get('/')->assertRedirect('/login');
    $this->get('/home')->assertRedirect('/login');
    $this->get('/login')->assertOk()->assertSee('Sign in');
});

it('signs an officer in and audits it', function () {
    Livewire::test(Login::class)
        ->set('email', 'p.mokoena@rsl.test')
        ->set('password', 'demo-pass')
        ->call('login')
        ->assertRedirect(route('home'));

    $this->assertAuthenticated();
    expect(AuditEvent::where('action', 'signed_in')->sole()->actor_label)->toContain('P. Mokoena');
});

it('rejects a wrong password and audits the attempt', function () {
    Livewire::test(Login::class)
        ->set('email', 'p.mokoena@rsl.test')
        ->set('password', 'wrong')
        ->call('login')
        ->assertHasErrors('email');

    $this->assertGuest();
    expect(AuditEvent::where('action', 'sign_in_failed')->count())->toBe(1);
});

it('locks out after repeated failures', function () {
    $component = Livewire::test(Login::class)->set('email', 'p.mokoena@rsl.test');
    foreach (range(1, 5) as $_) {
        $component->set('password', 'wrong')->call('login');
    }

    $component->set('password', 'demo-pass')->call('login')->assertHasErrors('email');
    $this->assertGuest();
});

it('shows the demo date on every page', function () {
    $this->actingAs(User::where('email', 'p.mokoena@rsl.test')->first())
        ->get('/home')
        ->assertOk()
        ->assertSee('Today: 2 Nov 2026')
        ->assertSee('Special Sectors')
        ->assertDontSee('Demo clock');
});

it('lets only an admin move the clock', function () {
    $admin = User::role('super_admin')->first();
    $this->actingAs($admin)->get('/home')->assertSee('Demo clock');

    Livewire::actingAs($admin)->test(DemoClock::class)->set('days', 12)->call('advance');
    $this->get('/home')->assertSee('Today: 14 Nov 2026');

    $officer = User::where('email', 'l.thamae@rsl.test')->first();
    Livewire::actingAs($officer)->test(DemoClock::class)->call('advance')->assertForbidden();
});

it('signs out and audits it', function () {
    $this->actingAs(User::where('email', 'l.thamae@rsl.test')->first())
        ->post('/logout')
        ->assertRedirect('/login');

    $this->assertGuest();
    expect(AuditEvent::where('action', 'signed_out')->count())->toBe(1);
});
