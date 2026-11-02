<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** "Credit (refund due)" is 19 characters; the column was created with 16. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vat_returns', function (Blueprint $table) {
            $table->string('position', 32)->change();
        });
    }

    public function down(): void
    {
        Schema::table('vat_returns', function (Blueprint $table) {
            $table->string('position', 16)->change();
        });
    }
};
