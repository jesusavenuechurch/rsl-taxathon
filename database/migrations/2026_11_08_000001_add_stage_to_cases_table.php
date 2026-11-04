<?php

use App\Models\Team;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The officer's desk (docs/design/officer-desk.md section 2): a case's stage
 * is the backbone every lane, strip and the Results tab reads from. `status`
 * stays for open/closed; `stage` is where in the journey an open case is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('stage', 24)->default('triage')->after('status');
        });

        $specialSectors = DB::table('teams')->where('code', Team::SPECIAL_SECTORS)->value('id');

        DB::table('cases')->where('status', 'closed')->update(['stage' => 'closed']);
        if ($specialSectors) {
            DB::table('cases')->where('status', '!=', 'closed')->where('team_id', $specialSectors)->update(['stage' => 'specialist']);
        }
    }

    public function down(): void
    {
        Schema::table('cases', fn (Blueprint $table) => $table->dropColumn('stage'));
    }
};
