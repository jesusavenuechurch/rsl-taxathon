<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // RSL units cases are routed between (Risk analysis, Refund audit,
        // Special Sectors, Data team). Roles say what a user may do; the team
        // says whose worklist a case lands on.
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('team_id')->nullable()->after('email')->constrained()->nullOnDelete();
            $table->string('job_title')->nullable()->after('team_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('team_id');
            $table->dropColumn('job_title');
        });
        Schema::dropIfExists('teams');
    }
};
