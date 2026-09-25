<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $seasonId = '2026-27';
        $loneTsarId = 'F012';
        $currentName = 'Ꮮ૦ท૯⚡️𐌕รคг';

        // The 2026-27 import used a new stylized spelling of Lone Tsar that did not
        // exactly match the existing alias, so the import created a duplicate franchise.
        $duplicate = DB::table('team_seasons')
            ->where('season_id', $seasonId)
            ->where('original_name', $currentName)
            ->where('franchise_id', '<>', $loneTsarId)
            ->first();

        if (!$duplicate) {
            return;
        }

        $duplicateId = $duplicate->franchise_id;

        // Recreate the season membership and team-season row under the real Lone Tsar franchise.
        DB::table('season_members')->updateOrInsert(
            ['season_member_id' => $seasonId.'-'.$loneTsarId],
            ['season_id' => $seasonId, 'franchise_id' => $loneTsarId, 'source_id' => 'fantrax-092zcn40molvao69']
        );

        $row = (array) $duplicate;
        $row['team_season_id'] = $seasonId.'-'.$loneTsarId;
        $row['franchise_id'] = $loneTsarId;
        DB::table('team_seasons')->updateOrInsert(
            ['team_season_id' => $row['team_season_id']],
            $row
        );

        // Keep the new spelling as an alias so future imports resolve it correctly.
        DB::table('franchise_aliases')->updateOrInsert(
            ['alias_name' => $currentName],
            [
                'alias_id' => 'A040',
                'franchise_id' => $loneTsarId,
                'source_id' => 'fantrax-092zcn40molvao69',
            ]
        );

        // Remove the incorrectly-created duplicate season records, then the orphan franchise.
        DB::table('team_seasons')->where('team_season_id', $duplicate->team_season_id)->delete();
        DB::table('season_members')->where('season_id', $seasonId)->where('franchise_id', $duplicateId)->delete();
        DB::table('franchises')->where('franchise_id', $duplicateId)->delete();
    }

    public function down(): void
    {
        // This migration repairs identity data. Reversing it would deliberately recreate
        // the duplicate franchise, so no destructive rollback is performed.
    }
};
