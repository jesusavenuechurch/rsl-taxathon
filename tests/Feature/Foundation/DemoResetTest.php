<?php

use App\Domain\Cases\SignoffPolicy;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Support\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
});

it('rebuilds the demo without messaging anyone, and sets the demo sign-off rule', function () {
    config(['services.whatsapp.phone_number_id' => '1234567890', 'services.whatsapp.access_token' => 'test-token']);
    User::where('email', 'l.thamae@rsl.test')->first()->update(['whatsapp_number' => '26658123456', 'whatsapp_opt_in' => true]);
    Http::fake();

    // --fresh cannot run inside the test transaction on SQLite; the rebuild after it is what matters here.
    $this->artisan('rsl:demo --all')->assertSuccessful();

    Http::assertNothingSent();
    expect(NotificationDelivery::count())->toBe(0)
        ->and(app(Settings::class)->get(SignoffPolicy::KEY))->toBe(0)
        ->and(config('rsl.notifications_muted'))->toBeFalse();
});

it('refuses to wipe a production database unless it is a demo server', function () {
    app()->detectEnvironment(fn () => 'production');

    config(['rsl.demo' => false]);
    $this->artisan('rsl:demo --fresh')->expectsOutputToContain('Refusing to wipe')->assertFailed();
});
