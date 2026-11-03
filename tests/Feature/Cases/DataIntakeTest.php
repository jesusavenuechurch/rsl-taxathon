<?php

use App\Livewire\Admin\DataIntake;
use App\Models\Cases\ReviewCase;
use App\Models\Memory\Decision;
use App\Models\SourceRecords\CitReturn;
use App\Models\SourceRecords\Taxpayer;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::role('super_admin')->first();
});

it('brings one company in and opens its case, with no command line', function () {
    Livewire::actingAs($this->admin)->test(DataIntake::class)
        ->set('tin', 'TN1000430-6')->call('receiveCompany')
        ->assertSee('Thaba Trading (Pty) Ltd: records received')
        ->assertSee('Analysis raised: P03 Fees growing faster than revenue; P12 Profit squeeze.')
        ->assertSee('is on the Risk analysis worklist');

    expect(ReviewCase::where('tin', 'TN1000430-6')->sole()->priority)->toBe('high')
        ->and(CitReturn::distinct()->pluck('tin')->all())->toBe(['TN1000430-6']);
});

it('saves only a filing review for an ordinary company', function () {
    Livewire::actingAs($this->admin)->test(DataIntake::class)
        ->set('tin', 'TN1000101-3')->call('receiveCompany')
        ->assertSee('Analysis found nothing unusual');

    expect(ReviewCase::count())->toBe(0);
});

it('receives an uploaded channel file', function () {
    Livewire::actingAs($this->admin)->test(DataIntake::class)->set('tin', 'TN1000430-6')->call('receiveCompany');

    $file = UploadedFile::fake()->createWithContent('cit_returns.csv', file_get_contents(RSL_DATA.'/cit_returns.csv'));
    Livewire::actingAs($this->admin)->test(DataIntake::class)
        ->set('channel', 'cit')->set('file', $file)->call('upload')
        ->assertSee('Income tax return received')
        ->assertSee('54 rows: 50 new, 0 revised, 4 unchanged');
});

it('explains a rejected file', function () {
    $file = UploadedFile::fake()->createWithContent('vat.csv', "record_id,tin\nVAT-1,TN1\n");

    Livewire::actingAs($this->admin)->test(DataIntake::class)
        ->set('channel', 'vat')->set('file', $file)->call('upload')
        ->assertSee('Missing column(s)');
});

it('is open to the data team and closed to case officers', function () {
    $this->actingAs(User::where('email', 'k.molapo@rsl.test')->first())->get('/intake')->assertOk()->assertSee('Simulate a company');
    $this->actingAs(User::where('email', 'l.thamae@rsl.test')->first())->get('/intake')->assertForbidden();
});

it('prepares a demo from the console', function () {
    $this->artisan('rsl:demo')->expectsOutputToContain('No filings yet')->assertSuccessful();

    expect(Taxpayer::count())->toBe(14)
        ->and(Decision::count())->toBe(3)
        ->and(CitReturn::count())->toBe(0);
});
