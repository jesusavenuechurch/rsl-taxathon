<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoint 5: the AI case brief. Append-only, like source records — a
 * regeneration is a new row, never an edit, so the officer can always see
 * what the AI actually drafted and what was decided about it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('case_briefs', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable(); // BRIEF-0001
            $table->foreignId('case_id')->constrained('cases')->cascadeOnDelete();
            $table->string('tin', 32)->index();
            $table->string('source', 16);               // ai | template
            $table->string('provider')->nullable();     // ollama/gpt-oss:20b, openrouter/..., or null for template
            $table->string('prompt_hash', 64)->nullable();
            $table->json('fact_sheet_ids');
            $table->json('draft');                       // {statements: [{text, citations}], next_step: {text, citations}}
            $table->text('rejection_reason')->nullable(); // why the AI draft was rejected, when source = template
            $table->string('status', 16);                 // draft | approved | edited | rejected
            $table->text('final_text')->nullable();       // the officer's edited wording, when status = edited
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->dateTime('generated_at');             // RSL clock
            $table->timestamps();

            $table->index(['case_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('case_briefs');
    }
};
