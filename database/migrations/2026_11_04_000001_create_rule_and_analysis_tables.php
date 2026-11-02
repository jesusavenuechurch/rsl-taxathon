<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Layer 2 (rule configuration) and layer 3 (analysis), see
 * docs/implementation-plan.md sections 3.2 and 3.3.
 */
return new class extends Migration
{
    public function up(): void
    {
        // The catalogue of patterns. Logic lives in PHP (one class per code);
        // every threshold and every word officers see lives in a version.
        Schema::create('pattern_rules', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();          // P01..P14
            $table->string('name');
            $table->text('plain_language');
            $table->string('data_channels');
            $table->string('legal_source');
            $table->string('trigger', 16);              // filing | document
            $table->boolean('enabled')->default(true);
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        // Editing a rule creates a new version; findings record the version
        // that produced them, so a changed threshold never rewrites history.
        Schema::create('pattern_rule_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pattern_rule_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version');
            $table->unsignedTinyInteger('evidence_level');
            $table->json('parameters');                 // thresholds
            $table->json('variants');                   // per variant: title, kind, wording template
            $table->json('lawful_explanations');
            $table->json('must_establish');
            $table->json('documents_to_request');
            $table->string('exposure_basis')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('change_reason')->nullable();
            $table->timestamps();

            $table->unique(['pattern_rule_id', 'version']);
        });

        Schema::create('analysis_runs', function (Blueprint $table) {
            $table->id();
            $table->string('trigger', 32);              // import | manual | rule_change | document_released
            $table->foreignId('initiated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('source_import_id')->nullable()->constrained()->nullOnDelete();
            $table->json('rule_versions');              // code => pattern_rule_version_id used
            $table->unsignedInteger('taxpayers')->default(0);
            $table->string('status', 16);               // running | completed | failed
            $table->dateTime('started_at');             // RSL clock
            $table->dateTime('finished_at')->nullable();
            $table->timestamps();
        });

        // One review each time a taxpayer is analysed, anchored to the filing
        // that prompted it. Saved even when nothing fires (the one-line summary).
        Schema::create('filing_reviews', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable();
            $table->foreignId('analysis_run_id')->constrained()->cascadeOnDelete();
            $table->string('tin', 32)->index();
            $table->string('anchor_record_id', 20)->nullable();
            $table->string('anchor_label')->nullable();
            $table->text('summary');
            $table->unsignedSmallInteger('findings_count');
            $table->dateTime('reviewed_at');            // RSL clock
            $table->timestamps();
        });

        // Every rule's result for a review, including why it did NOT fire
        // (how the decoy companies explain themselves).
        Schema::create('rule_checks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('filing_review_id')->constrained()->cascadeOnDelete();
            $table->foreignId('pattern_rule_version_id')->constrained()->restrictOnDelete();
            $table->char('pattern_code', 3);
            $table->string('result', 16);               // fired | not_fired | not_applicable
            $table->text('explanation');
            $table->json('metrics');
            $table->timestamps();

            $table->unique(['filing_review_id', 'pattern_code']);
        });

        Schema::create('findings', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable();
            $table->string('tin', 32);
            $table->char('pattern_code', 3);
            $table->foreignId('pattern_rule_version_id')->constrained()->restrictOnDelete();
            $table->foreignId('first_review_id')->constrained('filing_reviews')->restrictOnDelete();
            $table->foreignId('last_review_id')->constrained('filing_reviews')->restrictOnDelete();
            $table->string('fingerprint', 128);         // tin + pattern + scope: same finding across re-runs
            $table->string('variant', 32);
            $table->string('kind', 32);                 // risk | green_lane | information_gap | data_quality
            $table->string('title');
            $table->unsignedTinyInteger('evidence_level');
            $table->text('wording');
            $table->json('metrics');
            $table->bigInteger('exposure_estimate');    // estimated, never an assessed amount
            $table->string('exposure_basis')->nullable();
            $table->string('status', 16);               // open | superseded | withdrawn (cases add more later)
            $table->string('raised_by', 32);            // system_rule | released_document
            $table->foreignId('superseded_by_id')->nullable()->constrained('findings')->nullOnDelete();
            $table->dateTime('raised_at');              // RSL clock
            $table->dateTime('closed_at')->nullable();
            $table->timestamps();

            $table->index(['tin', 'status']);
            $table->index(['fingerprint', 'status']);
            $table->index(['pattern_code', 'status']);
        });

        // The exact source rows behind a finding, pinned to the version seen.
        Schema::create('finding_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('record_id', 20)->index();
            $table->unsignedSmallInteger('record_version');
            $table->string('channel', 32);
            $table->char('row_hash', 64);

            $table->unique(['finding_id', 'record_id']);
        });

        // The rule's lawful explanations and "what the officer must establish",
        // copied onto the finding so officers can work through them.
        Schema::create('finding_checklist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('finding_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);                 // lawful_explanation | must_establish
            $table->unsignedSmallInteger('position');
            $table->text('text');
            $table->string('status', 16)->default('open');
            $table->text('note')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['finding_checklist_items', 'finding_evidence', 'findings', 'rule_checks', 'filing_reviews',
            'analysis_runs', 'pattern_rule_versions', 'pattern_rules'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
