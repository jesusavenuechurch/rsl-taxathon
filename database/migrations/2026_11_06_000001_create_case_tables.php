<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Layer 4: cases and their timeline (docs/implementation-plan.md section 3.4). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cases', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable();
            $table->string('tin', 32)->index();
            $table->string('type', 32);                       // risk_review | refund_review | information_gap | data_check
            $table->string('refund_lane', 16)->nullable();    // lower_risk | needs_review (never "valid"/"refused")
            $table->string('refund_claim_record_id', 20)->nullable();
            $table->string('priority', 8);                    // high | medium | low
            $table->bigInteger('exposure_estimate');          // largest single estimate (estimates overlap)
            $table->string('status', 24);                     // open | in_progress | closed
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->foreignId('assigned_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('routing_reason');
            $table->foreignId('opened_from_review_id')->constrained('filing_reviews')->restrictOnDelete();
            $table->dateTime('opened_at');                    // RSL clock
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['assigned_user_id', 'status']);
        });

        // What happened to a case, in order: the story an officer (and a judge) reads.
        Schema::create('case_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('type', 32);                       // opened | findings_added | findings_withdrawn | assigned | referred | note
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('actor_label');
            $table->foreignId('from_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->foreignId('to_team_id')->nullable()->constrained('teams')->nullOnDelete();
            $table->text('note')->nullable();
            $table->json('data')->nullable();
            $table->dateTime('occurred_at');                  // RSL clock
            $table->timestamps();
        });

        Schema::table('findings', function (Blueprint $table) {
            $table->foreignId('case_id')->nullable()->after('tin')->constrained('cases')->nullOnDelete();
        });

        // The team that made a decision, so a company returns to the officers who know it.
        Schema::table('decisions', function (Blueprint $table) {
            $table->string('team_code', 32)->nullable()->after('officer');
        });
    }

    public function down(): void
    {
        Schema::table('decisions', fn (Blueprint $t) => $t->dropColumn('team_code'));
        Schema::table('findings', fn (Blueprint $t) => $t->dropConstrainedForeignId('case_id'));
        Schema::dropIfExists('case_events');
        Schema::dropIfExists('cases');
    }
};
