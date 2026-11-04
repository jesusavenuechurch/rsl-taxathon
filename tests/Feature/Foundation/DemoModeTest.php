<?php

use App\Livewire\Auth\DemoFront;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'demo-pass']);
    $this->seed(DatabaseSeeder::class);
});

it('sends guests to the login page when demo mode is off', function () {
    config(['rsl.demo' => false]);

    $this->get('/')->assertRedirect('/login');
    $this->get('/welcome')->assertNotFound();
});

it('shows a card per officer on the front page in demo mode', function () {
    config(['rsl.demo' => true]);

    $this->get('/')->assertRedirect('/welcome');
    $this->get('/welcome')->assertOk()
        ->assertSee('L. Thamae')->assertSee('Where should I look?')
        ->assertSee('N. Lebona')->assertSee('P. Mokoena')->assertSee('M. Sello')->assertSee('K. Molapo')
        ->assertSee('Sign in with a password');
});

it('lets a card sign straight in with no password, audited the same as a normal sign-in', function () {
    config(['rsl.demo' => true]);

    Livewire::test(DemoFront::class)->call('enterAs', 'l.thamae@rsl.test')->assertRedirect(route('home'));

    $this->assertAuthenticatedAs(User::where('email', 'l.thamae@rsl.test')->first());
    expect(AuditEvent::where('action', 'signed_in')->sole()->actor_label)->toContain('L. Thamae');
});

it('refuses to sign in as anyone outside the officer cards', function () {
    config(['rsl.demo' => true]);

    Livewire::test(DemoFront::class)->call('enterAs', 'admin@system.local')->assertStatus(404);
    $this->assertGuest();
});

it('is unreachable outside demo mode', function () {
    config(['rsl.demo' => false]);

    Livewire::test(DemoFront::class)->assertStatus(404);
});

it('lets the administrator act as an officer, labelling and recording it on every audit event', function () {
    config(['rsl.demo' => true]);
    $admin = User::role('super_admin')->first();
    $thamae = User::where('email', 'l.thamae@rsl.test')->first();

    $this->actingAs($admin)
        ->post(route('act-as', $thamae))
        ->assertRedirect(route('home'));

    // The session key is set by this request; the swap itself applies on the next one.
    $this->get('/home')->assertOk()->assertSee('You are acting as')->assertSee('L. Thamae');
    $this->assertAuthenticatedAs($thamae);

    $started = AuditEvent::where('action', 'acting_started')->sole();
    expect($started->actor_label)->toContain($admin->name)
        ->and($started->payload['acting_for'])->toBe($thamae->id);
});

it('returns to the administrator and stops recording acting_for', function () {
    config(['rsl.demo' => true]);
    $admin = User::role('super_admin')->first();
    $thamae = User::where('email', 'l.thamae@rsl.test')->first();

    $this->actingAs($admin)->post(route('act-as', $thamae));
    $this->get('/home'); // apply the swap, same as real use
    $this->post(route('act-as.stop'))->assertRedirect(route('home'));

    $this->get('/home')->assertOk()->assertDontSee('You are acting as');
    $this->assertAuthenticatedAs($admin);
    expect(AuditEvent::where('action', 'acting_stopped')->exists())->toBeTrue();
});

it('keeps a non-admin from acting as anyone, even in demo mode', function () {
    config(['rsl.demo' => true]);
    $thamae = User::where('email', 'l.thamae@rsl.test')->first();
    $mokoena = User::where('email', 'p.mokoena@rsl.test')->first();

    $this->actingAs($thamae)->post(route('act-as', $mokoena))->assertStatus(404);
    $this->assertAuthenticatedAs($thamae);
});

it('keeps Act as unreachable outside demo mode, even for the administrator', function () {
    config(['rsl.demo' => false]);
    $admin = User::role('super_admin')->first();
    $thamae = User::where('email', 'l.thamae@rsl.test')->first();

    $this->actingAs($admin)->post(route('act-as', $thamae))->assertStatus(404);
    $this->assertAuthenticatedAs($admin);
});
