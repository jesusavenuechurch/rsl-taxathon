<?php

use App\Livewire\Records\RecordLookup;
use App\Models\AuditEvent;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'demo-pass']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    $this->officer = User::where('email', 'l.thamae@rsl.test')->first();
});

it('shows a record exactly as received', function () {
    $this->actingAs($this->officer)->get('/records/CIT-00036')
        ->assertOk()
        ->assertSee('Income tax return')
        ->assertSee('Highveld Retail Lesotho (Pty) Ltd')
        ->assertSeeInOrder(['Related party interest', 'M3,000,000'])
        ->assertSeeInOrder(['Net profit before tax', '-M3,510,000'])
        ->assertSee('cit_returns.csv');
});

it('logs who opened which record', function () {
    $this->actingAs($this->officer)->get('/records/wht-00119')->assertOk();

    $event = AuditEvent::where('action', 'opened')->sole();
    expect($event->subject_ref)->toBe('WHT-00119')
        ->and($event->taxpayer_tin)->toBe('TN1001098-7')
        ->and($event->actor_id)->toBe($this->officer->id);
});

it('shows a replaced version with a warning and links to the current one', function () {
    importChannel('vat', editedCopy('vat_returns.csv', fn ($csv) => str_replace(',346250,519380,', ',346250,51938,', $csv)));

    $this->actingAs($this->officer)->get('/records/VAT-00145?v=1')
        ->assertOk()->assertSee('You are viewing version 1')->assertSee('M519,380');
    $this->get('/records/VAT-00145')
        ->assertOk()->assertDontSee('You are viewing version')->assertSee('M51,938')->assertSee('Version 2');
});

it('returns 404 for unknown records and refuses users without access', function () {
    $this->actingAs($this->officer)->get('/records/CIT-99999')->assertNotFound();
    $this->actingAs(User::factory()->create())->get('/records/CIT-00036')->assertForbidden();
});

it('sends guests to sign in', function () {
    $this->get('/records/CIT-00036')->assertRedirect('/login');
});

it('opens a record from the lookup box', function () {
    Livewire::actingAs($this->officer)->test(RecordLookup::class)
        ->set('recordId', ' rf-0001 ')->call('open')->assertRedirect('/records/RF-0001');

    Livewire::actingAs($this->officer)->test(RecordLookup::class)
        ->set('recordId', 'XYZ-1')->call('open')->assertHasErrors('recordId');
});

it('summarises what has been received on Home (the administrator\'s overview)', function () {
    $this->actingAs(User::role('super_admin')->first())->get('/home')
        ->assertOk()
        ->assertSee('RSL records received')
        ->assertSeeInOrder(['VAT return', 'eTax', '266'])
        ->assertSee('1 warning(s)');
});
