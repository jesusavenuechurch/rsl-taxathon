<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tamper-evident log: each row stores the hash of the row before it,
        // so editing or removing any row breaks every hash after it
        // (php artisan audit:verify). Rows can only ever be inserted.
        Schema::create('audit_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sequence')->unique();
            $table->dateTime('occurred_at');   // RSL clock (simulated in the demo)
            $table->dateTime('recorded_at');   // server's real time
            $table->string('actor_type', 16);  // user | system | taxpayer
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('actor_label');
            $table->string('action', 64);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_ref', 64)->nullable();
            $table->string('taxpayer_tin', 32)->nullable();
            $table->json('payload');
            $table->string('ip', 45)->nullable();
            $table->char('prev_hash', 64);
            $table->char('hash', 64)->unique();

            $table->index(['taxpayer_tin', 'sequence']);
            $table->index(['subject_type', 'subject_ref']);
            $table->index(['action', 'occurred_at']);
        });

        foreach ($this->appendOnlyTriggers() as $sql) {
            DB::unprepared($sql);
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS audit_events_append_only() CASCADE');
        }
        Schema::dropIfExists('audit_events');
    }

    /** Database-level guard, so even raw SQL cannot edit or delete rows. */
    private function appendOnlyTriggers(): array
    {
        $message = 'audit_events is append-only';

        return match (DB::getDriverName()) {
            'sqlite' => [
                "CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events BEGIN SELECT RAISE(ABORT, '{$message}'); END",
                "CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events BEGIN SELECT RAISE(ABORT, '{$message}'); END",
            ],
            'mysql', 'mariadb' => [
                "CREATE TRIGGER audit_events_no_update BEFORE UPDATE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
                "CREATE TRIGGER audit_events_no_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'",
            ],
            'pgsql' => [
                "CREATE FUNCTION audit_events_append_only() RETURNS trigger AS \$\$ BEGIN RAISE EXCEPTION '{$message}'; END; \$\$ LANGUAGE plpgsql",
                'CREATE TRIGGER audit_events_no_update BEFORE UPDATE OR DELETE ON audit_events FOR EACH ROW EXECUTE FUNCTION audit_events_append_only()',
            ],
            default => [],
        };
    }
};
