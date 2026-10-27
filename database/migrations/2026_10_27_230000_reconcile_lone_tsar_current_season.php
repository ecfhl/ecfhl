<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $season = '2026-27';
        $canonical = 'F012';
        $name = 'Ꮮ૦ท૯⚡️𐌕รคг';
        $leagueSource = 'fantrax-092zcn40molvao69';

        if (!DB::table('franchises')->where('franchise_id', $canonical)->exists()) {
            throw new RuntimeException('Cannot repair Lone Tsar identity: historical franchise F012 is missing.');
        }

        DB::transaction(function () use ($season, $canonical, $name, $leagueSource) {
            $records = DB::table('team_seasons')->where('season_id', $season)
                ->where('original_name', $name)->get();
            foreach ($records as $record) {
                if ($record->franchise_id === $canonical) continue;
                $oldId = $record->franchise_id;
                $canonicalRecord = DB::table('team_seasons')->where('season_id', $season)
                    ->where('franchise_id', $canonical)->first();
                if (!$canonicalRecord) {
                    DB::table('team_seasons')->where('team_season_id', $record->team_season_id)
                        ->update(['franchise_id' => $canonical]);
                } else {
                    DB::table('team_seasons')->where('team_season_id', $record->team_season_id)->delete();
                }
                DB::table('season_members')->where('season_id', $season)
                    ->where('franchise_id', $oldId)->delete();
            }

            DB::table('season_members')->updateOrInsert(
                ['season_member_id' => $season.'-'.$canonical],
                ['season_id' => $season, 'franchise_id' => $canonical, 'source_id' => $leagueSource]
            );
            DB::table('franchise_aliases')->updateOrInsert(
                ['alias_name' => $name],
                ['franchise_id' => $canonical, 'source_id' => $leagueSource]
            );

            $draftIds = DB::table('drafts')->where('season_id', $season)->pluck('draft_id');
            DB::table('draft_picks')->whereIn('draft_id', $draftIds)->where('team_name_raw', $name)
                ->update(['franchise_id' => $canonical]);

            $resolved = DB::table('team_seasons')->where('season_id', $season)
                ->where('original_name', $name)->where('franchise_id', $canonical)->exists();
            if (!$resolved) throw new RuntimeException('Lone Tsar 2026-27 identity repair incomplete.');
        });
    }

    public function down(): void
    {
        // Do not recreate a duplicate franchise on rollback.
    }
};
