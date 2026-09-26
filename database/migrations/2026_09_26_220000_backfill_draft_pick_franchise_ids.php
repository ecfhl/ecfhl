<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $picks = DB::table('draft_picks as dp')
            ->join('drafts as d', 'd.draft_id', '=', 'dp.draft_id')
            ->whereNull('dp.franchise_id')
            ->whereNotNull('dp.team_name_raw')
            ->select('dp.draft_pick_id', 'dp.team_name_raw', 'd.season_id')
            ->get();

        foreach ($picks as $pick) {
            $name = trim((string) $pick->team_name_raw);
            if ($name === '') continue;

            // Best source: the team name used by that franchise in the same season.
            $seasonMatches = DB::table('team_seasons')
                ->where('season_id', $pick->season_id)
                ->whereRaw('LOWER(TRIM(original_name)) = ?', [mb_strtolower($name)])
                ->pluck('franchise_id')->filter()->unique()->values();

            $franchiseId = $seasonMatches->count() === 1 ? $seasonMatches->first() : null;

            // Historical aliases are the next most reliable source.
            if (!$franchiseId) {
                $aliasMatches = DB::table('franchise_aliases')
                    ->whereRaw('LOWER(TRIM(alias_name)) = ?', [mb_strtolower($name)])
                    ->pluck('franchise_id')->filter()->unique()->values();
                if ($aliasMatches->count() === 1) $franchiseId = $aliasMatches->first();
            }

            // Finally allow an exact canonical franchise-name match.
            if (!$franchiseId) {
                $franchiseMatches = DB::table('franchises')
                    ->whereRaw('LOWER(TRIM(franchise_name)) = ?', [mb_strtolower($name)])
                    ->pluck('franchise_id')->filter()->unique()->values();
                if ($franchiseMatches->count() === 1) $franchiseId = $franchiseMatches->first();
            }

            if ($franchiseId) {
                DB::table('draft_picks')
                    ->where('draft_pick_id', $pick->draft_pick_id)
                    ->update(['franchise_id' => $franchiseId]);
            }
        }
    }

    public function down(): void
    {
        // Data repair: do not intentionally erase valid franchise IDs on rollback.
    }
};
