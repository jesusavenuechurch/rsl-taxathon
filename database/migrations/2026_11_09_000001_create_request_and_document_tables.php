<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoint 7, thin: information requests, simulated delivery on the clock,
 * released documents (docs/implementation-plan.md section 3.4; officer-desk.md
 * section 4.5). A document is invisible until a request names its type, then
 * arrives responds_after_days later (handoff rule 3).
 */
return new class extends Migration
{
    public function up(): void
    {
        // The taxpayer's filing cabinet: not a source record, never linked to
        // directly from a case. Only the delivery mechanism reads it.
        Schema::create('simulated_taxpayer_documents', function (Blueprint $table) {
            $table->id();
            $table->string('document_id', 20)->unique(); // DOC-001
            $table->string('tin', 32)->index();
            $table->string('document_type');
            $table->string('title');
            $table->unsignedSmallInteger('responds_after_days');
            $table->text('content_summary');
            $table->text('what_it_shows');
            $table->timestamps();
        });

        Schema::create('information_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable(); // IR-0001
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('composed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 16)->default('draft'); // draft | sent
            $table->text('message')->nullable();
            $table->dateTime('sent_at')->nullable(); // RSL clock
            $table->timestamps();
        });

        Schema::create('information_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_id')->constrained('information_requests')->cascadeOnDelete();
            $table->string('document_type');
            $table->foreignId('finding_id')->nullable()->constrained('findings')->nullOnDelete();
            $table->string('reason')->nullable();
            $table->string('status', 16)->default('requested'); // requested | received | not_supplied
            $table->timestamps();
        });

        // Created when a request is sent; a scheduled check (on every clock
        // move) turns due rows into released documents.
        Schema::create('document_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('request_item_id')->constrained('information_request_items')->cascadeOnDelete();
            $table->foreignId('simulated_document_id')->constrained('simulated_taxpayer_documents')->restrictOnDelete();
            $table->dateTime('due_at'); // RSL clock
            $table->dateTime('delivered_at')->nullable();
            $table->timestamps();
        });

        // The only taxpayer documents the UI ever shows.
        Schema::create('released_documents', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 20)->unique(); // same DOC- id as the pool row it came from
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->foreignId('request_item_id')->constrained('information_request_items')->restrictOnDelete();
            $table->foreignId('finding_id')->nullable()->constrained('findings')->nullOnDelete();
            $table->string('document_type');
            $table->string('title');
            $table->text('content_summary');
            $table->text('what_it_shows');
            $table->dateTime('received_at'); // RSL clock
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('released_documents');
        Schema::dropIfExists('document_deliveries');
        Schema::dropIfExists('information_request_items');
        Schema::dropIfExists('information_requests');
        Schema::dropIfExists('simulated_taxpayer_documents');
    }
};
