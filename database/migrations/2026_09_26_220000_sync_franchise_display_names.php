<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sequences = DB::table('seasons')->pluck('sequence', 'season_id');
        $latest = [];

        foreach (DB::table('team_seasons')->select('franchise_id', 'season_id', 'original_name')->get() as $row) {
            if (!$row->franchise_id || !$row->original_name) continue;
            $sequence = (int) ($sequences[$row->season_id] ?? -1);
            if (!isset($latest[$row->franchise_id]) || $sequence > $latest[$row->franchise_id]['sequence']) {
                $latest[$row->franchise_id] = ['sequence' => $sequence, 'name' => $row->original_name];
            }
        }

        foreach ($latest as $franchiseId => $row) {
            DB::table('franchises')->where('franchise_id', $franchiseId)->update(['franchise_name' => $row['name']]);
        }
    }

    public function down(): void
    {
        // Display names are derived data; historical aliases/team-season names remain unchanged.
    }
};
