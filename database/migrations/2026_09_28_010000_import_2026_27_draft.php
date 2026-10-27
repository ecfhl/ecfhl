<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $data = json_decode(file_get_contents(database_path('data/draft_2026_27.json')), true, 512, JSON_THROW_ON_ERROR);
        if ($data['season'] !== '2026-27' || $data['league_id'] !== '092zcn40molvao69' || count($data['picks']) !== 146) {
            throw new RuntimeException('Unexpected 2026-27 draft source.');
        }
        $normalize = fn($name) => mb_strtolower(trim(preg_replace('/\s+/u', ' ', str_replace(['’', '‘'], "'", $name))));

        DB::transaction(function () use ($data, $normalize) {
            $season = DB::table('seasons')->where('season_id', '2026-27')->first();
            if (!$season || $season->league_id !== $data['league_id']) {
                throw new RuntimeException('The draft league does not match the season.');
            }
            $teams = DB::table('team_seasons')->where('season_id', '2026-27')->get()
                ->groupBy(fn($row) => $normalize($row->original_name));
            $aliases = DB::table('franchise_aliases')->get()->groupBy(fn($row) => $normalize($row->alias_name));
            $players = DB::table('players')->get()->groupBy(fn($row) => $normalize($row->player_name));
            $seen = [];
            $seenPlayers = [];
            $rows = [];
            $newPlayers = [];
            $draftIds = DB::table('drafts')->where('season_id', '2026-27')->pluck('draft_id')->all();
            $draftId = $draftIds[0] ?? '2026-27-fantrax';
            foreach ($data['picks'] as $pick) {
                $overall = $pick['overall_pick'];
                $key = $normalize($pick['player']);
                if ($pick['round'] < 1 || $pick['pick_in_round'] < 1 || $pick['pick_in_round'] > 14
                    || $overall !== ($pick['round'] - 1) * 14 + $pick['pick_in_round']
                    || isset($seen[$overall]) || isset($seenPlayers[$key]) || $key === '') {
                    throw new RuntimeException('Invalid or duplicate draft selection.');
                }
                $seen[$overall] = true;
                $seenPlayers[$key] = true;
                $teamKey = $normalize($pick['team']);
                $matches = ($teams[$teamKey] ?? collect())->pluck('franchise_id')->unique();
                if ($matches->isEmpty()) {
                    $matches = ($aliases[$teamKey] ?? collect())->pluck('franchise_id')->unique()
                        ->filter(fn($id) => DB::table('season_members')->where('season_id', '2026-27')->where('franchise_id', $id)->exists());
                }
                // The original 2026-27 team import may have retained a variant of
                // Lone Tsar's stylized name. Resolve only this known identity.
                if ($teamKey === $normalize('Ꮮ૦ท૯⚡️𐌕รคг')
                    && DB::table('franchises')->where('franchise_id', 'F012')->exists()) {
                    $matches = collect(['F012']);
                }
                if ($matches->count() !== 1 || !DB::table('franchises')->where('franchise_id', $matches->first())->exists()) {
                    throw new RuntimeException('Unresolved drafting team: '.$pick['team']);
                }
                $existing = $players[$key] ?? collect();
                if ($existing->count() > 1) {
                    throw new RuntimeException('Ambiguous player: '.$pick['player']);
                }
                $playerId = $existing->first()?->player_id;
                if (!$playerId) {
                    $playerId = 'fantrax-name-'.sha1($key);
                    if (DB::table('players')->where('player_id', $playerId)->exists()) {
                        throw new RuntimeException('Player ID collision.');
                    }
                    $newPlayers[] = ['player_id' => $playerId, 'player_name' => $pick['player']];
                }
                $rows[] = [
                    'draft_pick_id' => '2026-27-fantrax-'.$overall,
                    'draft_id' => $draftId, 'franchise_id' => $matches->first(),
                    'team_name_raw' => $pick['team'], 'round' => $pick['round'],
                    'pick_in_round' => $pick['pick_in_round'], 'overall_pick' => $overall,
                    'player_id' => $playerId,
                ];
            }

            // Preserve all historical drafts; skipped Fantrax slots are not selections.
            $historicalCount = DB::table('draft_picks')->whereNotIn('draft_id', $draftIds)->count();
            if ($newPlayers) DB::table('players')->insert($newPlayers);
            DB::table('drafts')->updateOrInsert(['draft_id' => $draftId], [
                'season_id' => '2026-27', 'source_id' => 'fantrax-'.$data['league_id'],
            ]);
            DB::table('draft_picks')->whereIn('draft_id', $draftIds)->delete();
            DB::table('drafts')->where('season_id', '2026-27')->where('draft_id', '!=', $draftId)->delete();
            DB::table('draft_picks')->insert($rows);
            if (DB::table('draft_picks')->where('draft_id', $draftId)->count() !== 146
                || DB::table('draft_picks')->where('draft_id', '!=', $draftId)->count() !== $historicalCount) {
                throw new RuntimeException('Draft import count validation failed.');
            }
            DB::table('seasons')->where('season_id', '2026-27')
                ->where('note', 'Draft pending; results will be added after the 2026-27 ECFHL draft.')
                ->update(['note' => 'Draft results recorded from Fantrax: 146 selections.']);
        });
    }

    public function down(): void
    {
        // Retain verified draft history on application rollback.
    }
};
