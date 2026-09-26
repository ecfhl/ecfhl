<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $payload = DB::table('source_cache')->where('source_key', 'drafts')->value('payload');
        if (!$payload) return;

        $drafts = json_decode($payload, true);
        if (!is_array($drafts)) return;

        $seasonIds = DB::table('seasons')->pluck('season_id', 'season_name')->all();
        $teamSeasons = DB::table('team_seasons')->get()->groupBy('season_id');
        $aliases = DB::table('franchise_aliases')->get()->groupBy(fn($r) => mb_strtolower(trim($r->alias_name)));
        $franchises = DB::table('franchises')->get()->groupBy(fn($r) => mb_strtolower(trim($r->franchise_name)));

        $resolve = function (string $season, string $team) use ($seasonIds, $teamSeasons, $aliases, $franchises) {
            $key = mb_strtolower(trim($team));
            $sid = $seasonIds[$season] ?? null;

            if ($sid) {
                $matches = ($teamSeasons[$sid] ?? collect())->filter(
                    fn($r) => mb_strtolower(trim($r->original_name)) === $key
                )->pluck('franchise_id')->unique()->values();
                if ($matches->count() === 1) return $matches->first();
            }

            $matches = collect($aliases[$key] ?? [])->pluck('franchise_id')->unique()->values();
            if ($matches->count() === 1) return $matches->first();

            $matches = collect($franchises[$key] ?? [])->pluck('franchise_id')->unique()->values();
            return $matches->count() === 1 ? $matches->first() : null;
        };

        foreach ($drafts as $season => &$teams) {
            if (!is_array($teams)) continue;
            foreach ($teams as $team => &$picks) {
                if (!is_array($picks)) continue;
                $franchiseId = $resolve((string)$season, (string)$team);
                if (!$franchiseId) continue;
                foreach ($picks as &$pick) {
                    if (is_array($pick)) $pick['franchise_id'] = $franchiseId;
                }
                unset($pick);
            }
            unset($picks);
        }
        unset($teams);

        DB::table('source_cache')->where('source_key', 'drafts')->update([
            'payload' => json_encode($drafts, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);
    }

    public function down(): void
    {
        // Source-cache enrichment is intentionally retained on rollback.
    }
};
