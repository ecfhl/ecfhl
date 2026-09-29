<?php

use App\Support\FantraxAvailablePlayers;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:refresh-available-goalies', function (FantraxAvailablePlayers $fantrax) {
    $base = CarbonImmutable::now('America/Halifax')->startOfDay();
    $failed = false;

    foreach ([$base, $base->addDay()] as $date) {
        $day = $date->format('Y-m-d');
        try {
            $result = $fantrax->fetch($date, 'G');
            $dfo = DB::table('active_starting_goalies')->whereDate('game_date', $day)->get();
            $normalize = static fn ($v) => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim((string) $v))) ?? '';
            $dfoByName = [];
            foreach ($dfo as $g) {
                $key = strtoupper(trim((string) $g->team)).'|'.$normalize($g->player_name);
                $dfoByName[$key] = $g;
            }

            $now = now();
            $rows = [];
            foreach ($result['rows'] as $p) {
                // Fantrax can return mixed positions even for a goalie-filtered request.
                if (strtoupper(trim((string) ($p['position'] ?? ''))) !== 'G') continue;

                $team = strtoupper(trim((string) ($p['team'] ?? '')));
                $name = $normalize($p['player_name'] ?? '');
                if ($team === '' || $name === '') continue;

                $oppRaw = trim((string) ($p['opponent'] ?? ''));
                $away = str_starts_with($oppRaw, '@');
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
                    'player_name' => $p['player_name'],
                    'team' => $team,
                    'opponent' => $opponent,
                    'home_away' => $homeAway,
                    'availability' => $p['availability'],
                    'waiver_day' => $p['waiver_day'],
                    'injury_status' => $p['injury_status'],
                    'projected_fpts' => $p['projected_fpts'],
                    'source_rank' => $p['source_rank'],
                    'fantrax_url' => $p['fantrax_url'],
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

            $this->info($day.': '.count($rows).' available goalies rebuilt');
            Log::info('Available goalies rebuilt', ['date' => $day, 'rows' => count($rows)]);
        } catch (\Throwable $e) {
            $failed = true;
            Log::error('Available goalie rebuild failed', ['date' => $day, 'error' => $e->getMessage()]);
            $this->error($day.': '.$e->getMessage());
        }
    }

    return $failed ? 1 : 0;
});

Schedule::command('ecfhl:refresh-available-goalies')->hourlyAt(35)->withoutOverlapping(50);
