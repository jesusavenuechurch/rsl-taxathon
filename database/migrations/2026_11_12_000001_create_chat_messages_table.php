<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Ask the records": every question and the cited answer it got, so "what was RSL told, and from what" stays answerable. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('case_id')->nullable()->constrained('cases')->nullOnDelete();
            $table->text('question');
            $table->string('source', 16);               // ai | lookup | none
            $table->string('provider')->nullable();
            $table->json('answer');                      // {statements: [{text, citations}], note}
            $table->json('fact_sheet_ids');
            $table->text('rejection_reason')->nullable(); // why the AI answer was not used, when source = lookup
            $table->dateTime('asked_at');                 // RSL clock
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
