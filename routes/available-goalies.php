<?php

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:refresh-available-goalies', function () {
    $base = CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
    $failed = false;

    foreach ([$base, $base->addDay()] as $date) {
        $day = $date->format('Y-m-d');
        try {
            // Use the already-refreshed Fantrax daily-player table as the source of
            // available goalies. This keeps goalie availability consistent with the
            // same playing-today/tomorrow data used by AI Tips and avoids a second,
            // position-filtered Fantrax request that can omit valid goalies.
            $daily = DB::table('active_daily_players')
                ->whereDate('game_date', $day)
                ->whereRaw('UPPER(TRIM(position)) = ?', ['G'])
                ->orderBy('source_rank')
                ->get();

            $dfo = DB::table('active_starting_goalies')->whereDate('game_date', $day)->get();
            $normalize = static fn ($v) => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim((string) $v))) ?? '';
            $dfoByName = [];
            foreach ($dfo as $g) {
                $key = strtoupper(trim((string) $g->team)).'|'.$normalize($g->player_name);
                $dfoByName[$key] = $g;
            }

            $now = now();
            $rows = [];
            foreach ($daily as $p) {
                $team = strtoupper(trim((string) ($p->team ?? '')));
                $name = $normalize($p->player_name ?? '');
                if ($team === '' || $name === '') continue;

                $availability = strtoupper(trim((string) ($p->availability ?? '')));
                if ($availability !== 'FA' && $availability !== 'W') continue;

                $oppRaw = trim((string) ($p->opponent ?? ''));
                $away = strtoupper(trim((string) ($p->home_away ?? ''))) === 'AWAY' || str_starts_with($oppRaw, '@');
                $key = $team.'|'.$name;
                $dfoRow = $dfoByName[$key] ?? null;
                $opponent = ltrim($oppRaw, '@');
                $homeAway = $oppRaw === '' ? null : ($away ? 'AWAY' : 'HOME');
                if ($dfoRow && !empty($dfoRow->opponent)) {
                    $opponent = $dfoRow->opponent;
                    $homeAway = $dfoRow->home_away;
                }

                $rows[] = [
                    'game_date' => $day,
                    'player_name' => $p->player_name,
                    'team' => $team,
                    'opponent' => $opponent,
                    'home_away' => $homeAway,
                    'availability' => $availability,
                    'waiver_day' => $p->waiver_day,
                    'injury_status' => $p->injury_status,
                    'projected_fpts' => $p->projected_fpts,
                    'source_rank' => $p->source_rank,
                    'fantrax_url' => $p->fantrax_url,
                    'starting_status' => $dfoRow?->starting_status,
                    'last_update' => $now,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(function () use ($day, $rows) {
                DB::table('active_available_goalies')->whereDate('game_date', $day)->delete();
                if ($rows) DB::table('active_available_goalies')->insert($rows);
            });

            $this->info($day.': '.count($rows).' available goalies rebuilt from active_daily_players');
            Log::info('Available goalies rebuilt from daily players', ['date' => $day, 'rows' => count($rows)]);
        } catch (\Throwable $e) {
            $failed = true;
            Log::error('Available goalie rebuild failed', ['date' => $day, 'error' => $e->getMessage()]);
            $this->error($day.': '.$e->getMessage());
        }
    }

    return $failed ? 1 : 0;
});

Schedule::command('ecfhl:refresh-available-goalies')->hourlyAt(35)->withoutOverlapping(50);
