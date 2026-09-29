<?php

use App\Support\DailyFaceoffPowerPlay;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:sync', function () {
    $sources = [
        'history' => 'https://ecfhl.win/data.json',
        'drafts' => 'https://ecfhl.win/draft-picks.json',
    ];

    foreach ($sources as $key => $url) {
        $response = Http::timeout(30)->retry(3, 1000)->get($url);
        $response->throw();
        $decoded = $response->json();

        if ($key === 'history') {
            $replaceTeamName = function (&$value) use (&$replaceTeamName) {
                if (is_array($value)) {
                    foreach ($value as &$item) $replaceTeamName($item);
                } elseif ($value === 'Janick') {
                    $value = 'JDPower';
                }
            };
            $replaceTeamName($decoded);
        }

        $payload = $key === 'history'
            ? json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            : $response->body();

        DB::table('source_cache')->updateOrInsert(
            ['source_key' => $key],
            ['source_url' => $url, 'payload' => $payload, 'retrieved_at' => now(), 'updated_at' => now(), 'created_at' => now()]
        );

        if ($key === 'history') {
            Log::info('ECFHL history shape', [
                'league' => $decoded['league'] ?? null,
                'coverage' => $decoded['coverage'] ?? null,
                'seasons_count' => count($decoded['seasons'] ?? []),
                'seasons_sample' => array_slice($decoded['seasons'] ?? [], 0, 2),
                'team_seasons_count' => count($decoded['team_seasons'] ?? []),
                'team_seasons_sample' => array_slice($decoded['team_seasons'] ?? [], 0, 2),
                'team_summary_type' => gettype($decoded['team_summary'] ?? null),
                'team_summary_sample' => array_slice($decoded['team_summary'] ?? [], 0, 2, true),
                'trade_seasons' => array_keys($decoded['trades_by_season'] ?? []),
                'trade_sample' => array_slice($decoded['trades_by_season'] ?? [], 0, 1, true),
            ]);
        } else {
            Log::info('ECFHL drafts shape', [
                'seasons' => array_keys($decoded ?? []),
                'sample' => array_slice($decoded ?? [], 0, 1, true),
            ]);
        }
        $this->info("Synced {$key}");
    }

    DB::table('franchises')->where('franchise_id','F009')->update(['franchise_name'=>'JDPower']);
})->purpose('Import the current ECFHL history data into MySQL');

Artisan::command('ecfhl:validate-drafts', function () {
    $plan = (new \Database\Seeders\DraftsOnlySeeder)->plan();
    $this->info('READ ONLY: '.count($plan['draft_picks']).' picks validated; season counts: '.json_encode($plan['counts']));
});

Artisan::command('ecfhl:refresh-pp-lines {--team=}', function (DailyFaceoffPowerPlay $scraper) {
    $only = strtoupper((string) $this->option('team'));
    $teams = DailyFaceoffPowerPlay::TEAMS;
    if ($only !== '') {
        if (!isset($teams[$only])) {
            $this->error("Unknown NHL team: {$only}");
            return 1;
        }
        $teams = [$only => $teams[$only]];
    }

    foreach ($teams as $team => $slug) {
        try {
            $data = $scraper->fetch($team, $slug);
            $storedUpdate = DB::table('active_pp_lines')->where('team', $team)->max('last_update');

            if ($storedUpdate && $data['lastUpdate']->lessThanOrEqualTo(\Carbon\CarbonImmutable::parse($storedUpdate))) {
                $this->line("{$team}: unchanged");
                continue;
            }

            DB::transaction(function () use ($data, $team) {
                DB::table('active_pp_lines')->where('team', $team)->delete();
                $now = now();
                $rows = array_map(fn ($player) => [
                    'team' => $team,
                    'player_name' => $player['player_name'],
                    'pp_unit' => $player['pp_unit'],
                    'unit_position' => $player['unit_position'],
                    'source_url' => $data['url'],
                    'last_update' => $data['lastUpdate'],
                    'checked_at' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $data['players']);
                DB::table('active_pp_lines')->insert($rows);
            });
            $this->info("{$team}: refreshed PP1/PP2");
        } catch (\Throwable $e) {
            Log::error('Daily Faceoff PP refresh failed', ['team'=>$team, 'error'=>$e->getMessage()]);
            $this->error("{$team}: {$e->getMessage()}");
        }
    }

    return 0;
})->purpose('Refresh active PP1/PP2 lines from Daily Faceoff when the source page is newer');

Schedule::command('ecfhl:refresh-pp-lines')
    ->cron('17 */4 * * *')
    ->withoutOverlapping(240)
    ->runInBackground();
