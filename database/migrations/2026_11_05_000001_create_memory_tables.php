<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Company memory: decisions from earlier reviews, the conditions they hold
 * under, and each analysis's check of those conditions (handoff rules 7-9).
 */
return new class extends Migration
{
    public function up(): void
    {
        // A conclusion stored as five separate parts (rule 7). System errors
        // never enter memory (rule 9): enforced here as well as in code.
        Schema::create('decisions', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique();       // DEC-0007
            $table->string('tin', 32)->index();
            $table->string('tax_year_reviewed', 7);
            $table->char('pattern_code', 3)->nullable();
            $table->string('pattern_label');
            $table->string('outcome', 32);                     // concern_supported_referred | explained_legitimate | more_information_needed
            $table->text('observed_pattern');                  // 1. what was observed
            $table->text('conclusion');                        // 2. the officer's conclusion and reason
            $table->text('evidence');                          // 3. what it rests on
            $table->text('conditions');                        // 4. when it holds (as the officer wrote it)
            $table->string('reassessment_point');              // 5. when to look again
            $table->string('officer');
            $table->string('second_signoff')->nullable();
            $table->date('decided_on');
            $table->string('source', 32);                      // imported | officer_outcome
            $table->foreignId('source_import_id')->nullable()->constrained()->nullOnDelete();
            $table->dateTime('superseded_at')->nullable();
            $table->timestamps();
        });

        $this->forbidSystemErrors();

        // The checkable reading of a decision's conditions. Conditions with no
        // metric stay as text for the officer to judge.
        Schema::create('decision_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('decision_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('text');                            // the part of the officer's wording it reads
            $table->string('metric', 64)->nullable();          // see App\Domain\Memory\Metrics
            $table->string('operator', 16)->nullable();        // approx | lte | gte | between
            $table->decimal('value', 20, 4)->nullable();
            $table->decimal('value_max', 20, 4)->nullable();
            $table->decimal('tolerance', 8, 4)->nullable();    // for approx: allowed relative difference
            $table->timestamps();
        });

        Schema::create('memory_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filing_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('decision_id')->constrained()->restrictOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained()->nullOnDelete();
            $table->string('result', 32);                      // within_conditions | outside_conditions | not_checkable
            $table->text('statement');
            $table->json('condition_results');
            $table->timestamps();

            $table->unique(['filing_review_id', 'decision_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('memory_checks');
        Schema::dropIfExists('decision_conditions');
        if (in_array(DB::getDriverName(), ['sqlite', 'mysql', 'mariadb'])) {
            foreach (['decisions_no_system_error_insert', 'decisions_no_system_error_update'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
        if (DB::getDriverName() === 'pgsql') {
            DB::unprepared('DROP FUNCTION IF EXISTS decisions_no_system_error() CASCADE');
        }
        Schema::dropIfExists('decisions');
    }

    /** Triggers rather than a CHECK constraint, which SQLite cannot add to an existing table portably. */
    private function forbidSystemErrors(): void
    {
        $message = 'System errors go to data review, never into company memory';

        $statements = match (DB::getDriverName()) {
            'sqlite' => [
                "CREATE TRIGGER decisions_no_system_error_insert BEFORE INSERT ON decisions WHEN NEW.outcome = 'system_error' BEGIN SELECT RAISE(ABORT, '{$message}'); END",
                "CREATE TRIGGER decisions_no_system_error_update BEFORE UPDATE ON decisions WHEN NEW.outcome = 'system_error' BEGIN SELECT RAISE(ABORT, '{$message}'); END",
            ],
            'mysql', 'mariadb' => [
                "CREATE TRIGGER decisions_no_system_error_insert BEFORE INSERT ON decisions FOR EACH ROW BEGIN IF NEW.outcome = 'system_error' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END",
                "CREATE TRIGGER decisions_no_system_error_update BEFORE UPDATE ON decisions FOR EACH ROW BEGIN IF NEW.outcome = 'system_error' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END",
            ],
            'pgsql' => [
                "CREATE FUNCTION decisions_no_system_error() RETURNS trigger AS \$\$ BEGIN IF NEW.outcome = 'system_error' THEN RAISE EXCEPTION '{$message}'; END IF; RETURN NEW; END; \$\$ LANGUAGE plpgsql",
                'CREATE TRIGGER decisions_no_system_error BEFORE INSERT OR UPDATE ON decisions FOR EACH ROW EXECUTE FUNCTION decisions_no_system_error()',
            ],
            default => [],
        };

        foreach ($statements as $sql) {
            DB::unprepared($sql);
        }
    }
};
