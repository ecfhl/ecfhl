<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Explicit repair only: never called by DatabaseSeeder or migrations. */
class DraftsOnlySeeder extends Seeder
{
    public static function source(?string $path = null): array
    {
        $text = file_get_contents($path ?? database_path('data/ecfhl_database.txt'));
        preg_match_all('/TAB NAME:\s*([^>]+)>\R(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/su', $text, $matches, PREG_SET_ORDER);
        $tables = [];
        foreach ($matches as $match) {
            $name = trim($match[1]);
            if (!in_array($name, ['drafts', 'draft_picks', 'team_seasons'], true)) continue;
            if (isset($tables[$name])) throw new RuntimeException("Duplicate section: $name");
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $match[2]); rewind($stream);
            $headers = fgetcsv($stream);
            $indexed = ($headers[0] ?? '') === 'index';
            if ($indexed) array_shift($headers);
            $tables[$name] = [];
            while (($row = fgetcsv($stream)) !== false) {
                if ($row === [null]) continue;
                if ($indexed) array_shift($row);
                if (count($row) !== count($headers)) throw new RuntimeException("Malformed $name row");
                $tables[$name][] = array_combine($headers, array_map(fn($v) => $v === '' ? null : $v, $row));
            }
            fclose($stream);
        }
        if (count($tables['draft_picks'] ?? []) < 2103 || count($tables['drafts'] ?? []) < 16) {
            throw new RuntimeException('Import refused: source requires at least 2,103 picks and 16 drafts.');
        }
        foreach (['drafts'=>'draft_id', 'draft_picks'=>'draft_pick_id'] as $table=>$key) {
            $ids = array_column($tables[$table], $key);
            if (count(array_filter($ids)) !== count($ids) || count(array_unique($ids)) !== count($ids)) throw new RuntimeException("Invalid/duplicate $key");
        }
        return $tables;
    }

    public function plan(): array
    {
        $source = self::source();
        $key = fn($s) => mb_strtolower(trim(str_replace(["’", "‘"], "'", (string)$s)));
        $live = [];
        foreach (DB::table('team_seasons')->get() as $team) {
            $live[$team->season_id][$key($team->original_name)][$team->franchise_id] = true;
        }
        $historical = [];
        foreach ($source['team_seasons'] as $team) $historical[$team['season_id']][$team['franchise_id']][] = $team['original_name'];
        $seasons = DB::table('seasons')->pluck('season_id')->all();
        $players = array_fill_keys(DB::table('players')->pluck('player_id')->all(), true);
        $franchises = array_fill_keys(DB::table('franchises')->pluck('franchise_id')->all(), true);
        $drafts = array_column($source['drafts'], 'season_id', 'draft_id');
        foreach ($drafts as $sid) if (!in_array($sid, $seasons, true)) throw new RuntimeException("Missing season $sid");
        $seen = []; $counts = [];
        foreach ($source['draft_picks'] as &$pick) {
            $sid = $drafts[$pick['draft_id']] ?? null;
            if (!$sid || !isset($players[$pick['player_id'] ?? ''])) throw new RuntimeException('Missing draft/player: '.$pick['draft_pick_id']);
            foreach (['round','pick_in_round','overall_pick'] as $field) {
                if (!ctype_digit((string)$pick[$field]) || (int)$pick[$field] < 1) throw new RuntimeException("Invalid $field");
            }
            $unique = $sid.'|'.$pick['overall_pick'];
            if (isset($seen[$unique])) throw new RuntimeException("Duplicate overall pick $unique");
            $seen[$unique] = true;
            // Prefer this season's actual name. Canonical export names are translated
            // through the source's same-season team row, then matched to LIVE team_seasons.
            // Never copy an exported franchise ID directly into production.
            $candidates = $live[$sid][$key($pick['team_name_raw'])] ?? [];
            if (!$candidates) foreach ($historical[$sid][$pick['franchise_id']] ?? [] as $name) {
                $candidates += $live[$sid][$key($name)] ?? [];
            }
            if (count($candidates) !== 1) throw new RuntimeException("Unresolved/ambiguous franchise: $sid / ".$pick['team_name_raw']);
            $pick['franchise_id'] = array_key_first($candidates);
            if (!isset($franchises[$pick['franchise_id']])) throw new RuntimeException('Missing live franchise');
            $counts[$sid] = ($counts[$sid] ?? 0) + 1;
        }
        unset($pick);
        if (count($counts) !== count($drafts)) throw new RuntimeException('A source draft has no picks');
        ksort($counts);
        return ['drafts'=>$source['drafts'], 'draft_picks'=>$source['draft_picks'], 'counts'=>$counts];
    }

    private function protectedState(): array
    {
        $state = [];
        foreach (['seasons','franchises','franchise_aliases','season_members','team_seasons','players','trades','trade_assets','award_types','awards','playoff_rounds','playoff_games','playoff_winners','prize_awards','season_prizes','rules','source_cache'] as $table) {
            $rows = DB::table($table)->get()->map(fn($r) => json_encode($r))->all();
            sort($rows);
            $state[$table] = hash('sha256', implode("\n", $rows));
        }
        return $state;
    }

    public function run(): void
    {
        // All parsing, reference and identity validation finishes before DELETE.
        $plan = $this->plan();
        $this->command?->info('Validated '.count($plan['draft_picks']).' picks: '.json_encode($plan['counts']));
        DB::transaction(function () use ($plan) {
            $before = $this->protectedState();
            DB::table('draft_picks')->delete();
            DB::table('drafts')->delete();
            DB::table('drafts')->insert($plan['drafts']);
            foreach (array_chunk($plan['draft_picks'], 200) as $batch) DB::table('draft_picks')->insert($batch);
            foreach (['drafts','draft_picks'] as $table) {
                if (DB::table($table)->count() !== count($plan[$table])) throw new RuntimeException("Post-import count mismatch: $table");
            }
            if ($before !== $this->protectedState()) throw new RuntimeException('A protected table changed; rolling back');
        });
        $this->command?->info('Protected table fingerprints unchanged.');
        $this->command?->info('Rebuilt only drafts and draft_picks; all franchise IDs resolved through seasonal team_seasons.');
    }
}
