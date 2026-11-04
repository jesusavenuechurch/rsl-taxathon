<?php

use App\Domain\Analysis\AnalysisEngine;
use App\Domain\Notifications\NotificationSender;
use App\Models\Cases\ReviewCase;
use App\Models\NotificationDelivery;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['rsl.seed_demo_users' => true, 'rsl.demo_user_password' => 'x']);
    $this->seed(DatabaseSeeder::class);
    importAll();
    app(AnalysisEngine::class)->run();
    $this->analyst = User::where('email', 'l.thamae@rsl.test')->first();
    $this->thaba = ReviewCase::where('tin', 'TN1000430-6')->sole();
});

it('skips a recipient with no WhatsApp number, never calling out to Meta', function () {
    Http::fake();

    // Opening Thaba's case in beforeEach already fired one case_opened notification for real; call again for a clean sample.
    app(NotificationSender::class)->caseOpened($this->thaba);

    Http::assertNothingSent();
    $delivery = NotificationDelivery::where('recipient_id', $this->analyst->id)->where('event', 'case_opened')->latest('id')->first();
    expect($delivery->status)->toBe('skipped');
});

it('sends to an opted-in recipient and records the real result', function () {
    $this->analyst->update(['whatsapp_number' => '26658123456', 'whatsapp_opt_in' => true]);

    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.success']]], 200)]);
    app(NotificationSender::class)->caseOpened($this->thaba);

    $sent = NotificationDelivery::where('recipient_id', $this->analyst->id)->where('event', 'case_opened')->latest('id')->first();
    expect($sent->status)->toBe('sent')->and($sent->meta_message_id)->toBe('wamid.success');
});

it('records a failed send when Meta rejects the template', function () {
    $lead = User::where('email', 'm.sello@rsl.test')->first();
    $lead->update(['whatsapp_number' => '26658654321', 'whatsapp_opt_in' => true]);

    Http::fake(['graph.facebook.com/*' => Http::response(['error' => ['message' => 'Template not approved']], 400)]);
    app(NotificationSender::class)->signoffNeeded($this->thaba);

    $failed = NotificationDelivery::where('event', 'signoff_needed')->where('recipient_id', $lead->id)->latest('id')->first();
    expect($failed->status)->toBe('failed')->and($failed->status_detail)->toBe('Template not approved');
});

it('never puts a company name, TIN or amount in a rendered notification', function () {
    Http::fake();
    $this->analyst->update(['whatsapp_number' => '26658123456', 'whatsapp_opt_in' => true]);
    $sender = app(NotificationSender::class);

    $sender->caseOpened($this->thaba);
    $sender->caseReferred($this->thaba, Team::where('code', 'special_sectors')->first());
    $sender->documentsReceived($this->thaba);
    $sender->signoffNeeded($this->thaba);

    foreach (NotificationDelivery::all() as $delivery) {
        expect($delivery->rendered_text)->not->toContain($this->thaba->tin)
            ->not->toContain('Thaba Trading')
            ->not->toMatch('/\bM[\d,]+\b/'); // no Maloti amount
    }
});

it('gates the Notifications sent page to those who can view the audit log', function () {
    $this->actingAs(User::role('super_admin')->first())->get('/notifications')->assertOk();
    $this->actingAs($this->analyst)->get('/notifications')->assertForbidden();
});
