<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Checkpoint 6: WhatsApp notifications on hand-off events, with no taxpayer
 * details (handoff rule 13; officer-desk.md section 7).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('whatsapp_number', 32)->nullable()->after('job_title');
            $table->boolean('whatsapp_opt_in')->default(false)->after('whatsapp_number');
        });

        Schema::create('notification_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 16)->unique()->nullable(); // NOTE-0001
            $table->string('event', 32); // case_opened | case_referred | documents_received | signoff_needed
            $table->string('template', 32);
            $table->foreignId('recipient_id')->constrained('users')->restrictOnDelete();
            $table->string('case_reference', 16)->nullable();
            $table->text('rendered_text'); // exactly what was sent, for the Notifications sent page
            $table->string('meta_message_id')->nullable();
            $table->string('status', 16); // queued | sent | delivered | read | failed | skipped
            $table->text('status_detail')->nullable(); // why skipped/failed, in plain words
            $table->dateTime('sent_at')->nullable(); // RSL clock
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_deliveries');
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn(['whatsapp_number', 'whatsapp_opt_in']));
    }
};
