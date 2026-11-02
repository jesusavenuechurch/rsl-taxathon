<?php

use App\Domain\Audit\AuditActor;
use App\Domain\Audit\AuditHasher;
use App\Domain\Audit\AuditLog;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

function writeEvents(int $n): void
{
    foreach (range(1, $n) as $i) {
        app(AuditLog::class)->record('opened', subjectType: 'case', subjectRef: "CASE-{$i}", payload: ['n' => $i, 'z' => ['b' => 1, 'a' => 2]], taxpayerTin: 'TN1000430-6');
    }
}

it('chains every event to the one before it', function () {
    writeEvents(3);

    $events = AuditEvent::orderBy('sequence')->get();
    expect($events->pluck('sequence')->all())->toBe([1, 2, 3])
        ->and($events[0]->prev_hash)->toBe(AuditHasher::GENESIS)
        ->and($events[1]->prev_hash)->toBe($events[0]->hash)
        ->and($events[2]->prev_hash)->toBe($events[1]->hash)
        ->and(app(AuditLog::class)->verify()->intact)->toBeTrue();
});

it('records who acted', function () {
    $officer = User::factory()->create(['name' => 'P. Mokoena']);
    $this->actingAs($officer);

    $event = app(AuditLog::class)->record('requested', subjectType: 'information_request', subjectRef: 'IR-001');

    expect($event->actor_type)->toBe('user')
        ->and($event->actor_id)->toBe($officer->id)
        ->and($event->actor_label)->toBe('P. Mokoena');

    $system = app(AuditLog::class)->record('analysed', actor: AuditActor::system());
    expect($system->actor_type)->toBe('system')->and($system->actor_id)->toBeNull();
});

it('refuses edits and deletes through the model', function () {
    writeEvents(1);
    $event = AuditEvent::first();

    expect(fn () => $event->update(['action' => 'concluded']))->toThrow(LogicException::class)
        ->and(fn () => $event->delete())->toThrow(LogicException::class);
});

it('refuses edits and deletes even through raw SQL', function () {
    writeEvents(1);

    expect(fn () => DB::table('audit_events')->update(['action' => 'concluded']))->toThrow(QueryException::class)
        ->and(fn () => DB::table('audit_events')->delete())->toThrow(QueryException::class);
});

it('detects an edited row if the database guard is bypassed', function () {
    writeEvents(3);
    DB::unprepared('DROP TRIGGER audit_events_no_update');
    DB::table('audit_events')->where('sequence', 2)->update(['payload' => '{"n":99}']);

    $result = app(AuditLog::class)->verify();

    expect($result->intact)->toBeFalse()
        ->and($result->brokenAtSequence)->toBe(2)
        ->and($result->problem)->toContain('edited');
    $this->artisan('audit:verify')->expectsOutputToContain('BROKEN at sequence 2')->assertFailed();
});

it('detects a removed row if the database guard is bypassed', function () {
    writeEvents(3);
    DB::unprepared('DROP TRIGGER audit_events_no_delete');
    DB::table('audit_events')->where('sequence', 2)->delete();

    $result = app(AuditLog::class)->verify();

    expect($result->intact)->toBeFalse()->and($result->problem)->toContain('missing');
});

it('reports an intact log from the console', function () {
    writeEvents(2);

    $this->artisan('audit:verify')->expectsOutputToContain('Audit log intact: 2 entries')->assertSuccessful();
});
