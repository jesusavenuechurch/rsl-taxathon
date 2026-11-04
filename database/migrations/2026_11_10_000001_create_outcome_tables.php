<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoint 8, thin: four officer outcomes with five parts each, second
 * sign-off at the configured threshold, and where a system error goes
 * instead of company memory (docs/implementation-plan.md section 3.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('officer_outcomes', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable(); // OUT-0001
            $table->foreignId('finding_id')->constrained('findings')->restrictOnDelete();
            $table->foreignId('case_id')->constrained('cases')->restrictOnDelete();
            $table->string('tin', 32)->index();
            $table->string('outcome', 32); // concern_supported_referred | explained_legitimate | more_information_needed | system_error
            // The five parts (handoff rule 7), same shape as a decision.
            $table->text('observed_pattern');
            $table->text('conclusion');
            $table->text('evidence');
            $table->text('conditions');
            $table->string('reassessment_point');
            $table->bigInteger('exposure_at_decision');
            $table->foreignId('decided_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('decided_at'); // RSL clock
            $table->boolean('requires_second_signoff')->default(false);
            $table->string('signoff_status', 16)->nullable(); // pending | approved | returned
            $table->foreignId('second_signer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('signed_off_at')->nullable();
            $table->text('signoff_note')->nullable();
            $table->timestamps();
        });

        $this->forbidSelfSignoff();

        // Where a system error goes instead of company memory (rule 9).
        Schema::create('data_review_items', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable(); // DR-0001
            $table->string('record_id', 20);
            $table->unsignedSmallInteger('record_version');
            $table->foreignId('finding_id')->nullable()->constrained('findings')->nullOnDelete();
            $table->foreignId('raised_from_outcome_id')->constrained('officer_outcomes')->restrictOnDelete();
            $table->text('reason');
            $table->string('status', 16)->default('open'); // open | resolved
            $table->text('resolution')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('data_review_items');
        if (in_array(DB::getDriverName(), ['sqlite', 'mysql', 'mariadb'])) {
            foreach (['officer_outcomes_no_self_signoff_insert', 'officer_outcomes_no_self_signoff_update'] as $trigger) {
                DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
            }
        }
        Schema::dropIfExists('officer_outcomes');
    }

    /** A second signer can never be the officer who decided (enforced in code too). */
    private function forbidSelfSignoff(): void
    {
        $message = 'The second signer cannot be the officer who decided the outcome';

        $statements = match (DB::getDriverName()) {
            'sqlite' => [
                "CREATE TRIGGER officer_outcomes_no_self_signoff_insert BEFORE INSERT ON officer_outcomes WHEN NEW.second_signer_id IS NOT NULL AND NEW.second_signer_id = NEW.decided_by BEGIN SELECT RAISE(ABORT, '{$message}'); END",
                "CREATE TRIGGER officer_outcomes_no_self_signoff_update BEFORE UPDATE ON officer_outcomes WHEN NEW.second_signer_id IS NOT NULL AND NEW.second_signer_id = NEW.decided_by BEGIN SELECT RAISE(ABORT, '{$message}'); END",
            ],
            'mysql', 'mariadb' => [
                "CREATE TRIGGER officer_outcomes_no_self_signoff_insert BEFORE INSERT ON officer_outcomes FOR EACH ROW BEGIN IF NEW.second_signer_id IS NOT NULL AND NEW.second_signer_id = NEW.decided_by THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END",
                "CREATE TRIGGER officer_outcomes_no_self_signoff_update BEFORE UPDATE ON officer_outcomes FOR EACH ROW BEGIN IF NEW.second_signer_id IS NOT NULL AND NEW.second_signer_id = NEW.decided_by THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = '{$message}'; END IF; END",
            ],
            default => [],
        };

        foreach ($statements as $sql) {
            DB::unprepared($sql);
        }
    }
};
