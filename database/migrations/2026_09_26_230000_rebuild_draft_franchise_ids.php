<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Rebuild every draft franchise_id from the authoritative relational identity
        // data. Do not trust an existing cached franchise_id: some historical picks
        // were previously assigned to the wrong franchise rather than simply being NULL.
        $seasonTeam = [];
        foreach (DB::table('team_seasons')->get(['season_id','original_name','franchise_id']) as $row) {
            $name = mb_strtolower(trim((string) $row->original_name));
            if ($name !== '') {
                $seasonTeam[$row->season_id][$name] = $row->franchise_id;
            }
        }

        $aliases = [];
        foreach (DB::table('franchise_aliases')->get(['alias_name','franchise_id']) as $row) {
            $name = mb_strtolower(trim((string) $row->alias_name));
            if ($name !== '') $aliases[$name] = $row->franchise_id;
        }
        foreach (DB::table('franchises')->get(['franchise_name','franchise_id']) as $row) {
            $name = mb_strtolower(trim((string) $row->franchise_name));
            if ($name !== '') $aliases[$name] = $row->franchise_id;
        }

        // Fix the relational draft table first, including rows that currently contain
        // an incorrect non-null franchise_id.
        $draftRows = DB::table('draft_picks as dp')
            ->join('drafts as d', 'd.draft_id', '=', 'dp.draft_id')
            ->get(['dp.draft_pick_id','dp.team_name_raw','d.season_id']);

        foreach ($draftRows as $row) {
            $name = mb_strtolower(trim((string) $row->team_name_raw));
            if ($name === '') continue;
            $fid = $seasonTeam[$row->season_id][$name] ?? $aliases[$name] ?? null;
            if ($fid) {
                DB::table('draft_picks')->where('draft_pick_id', $row->draft_pick_id)->update(['franchise_id' => $fid]);
            }
        }

        // draftSeason() currently reads source_cache, so rebuild those IDs too.
        $cache = DB::table('source_cache')->where('source_key', 'drafts')->first();
        if (!$cache) return;
        $drafts = json_decode($cache->payload, true);
        if (!is_array($drafts)) return;

        foreach ($drafts as $season => &$teams) {
            if (!is_array($teams)) continue;
            foreach ($teams as $teamName => &$picks) {
                $key = mb_strtolower(trim((string) $teamName));
                $fid = $seasonTeam[$season][$key] ?? $aliases[$key] ?? null;
                if (!$fid || !is_array($picks)) continue;
                foreach ($picks as &$pick) {
                    if (is_array($pick)) $pick['franchise_id'] = $fid;
                }
                unset($pick);
            }
            unset($picks);
        }
        unset($teams);

        DB::table('source_cache')->where('source_key', 'drafts')->update([
            'payload' => json_encode($drafts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Identity repair is intentionally not reversed.
    }
};
