<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class TeamsSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            Team::RISK_ANALYSIS => 'Risk analysis',
            Team::REFUND_AUDIT => 'Refund audit',
            Team::SPECIAL_SECTORS => 'Special Sectors',
            Team::DATA => 'Data team',
        ] as $code => $name) {
            Team::updateOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
