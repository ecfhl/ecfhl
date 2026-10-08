<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** NHL windows use UTC instants; fantasy dates always roll over in Pacific time. */
class CollectorSchedule
{
    private ?array $window = null;

    public function window(): array
    {
        if ($this->window !== null) return $this->window;
        $now = CarbonImmutable::now('UTC');
        $today = (new FantasyDay)->today();
        $live = $pregame = $pending = false;
        $todayFinished = false;
        $finals = [];
        try {
            foreach ([$today->subDay(), $today, $today->addDay()] as $date) {
                $day = $date->toDateString();
                $cacheKey = 'collectors:nhl:'.$day;
                $data = Cache::get($cacheKey);
                if ($data === null) {
                    $data = Http::connectTimeout(3)->timeout(8)->get('https://api-web.nhle.com/v1/score/'.$day)->throw()->json();
                    if (!is_array($data) || ($data['currentDate'] ?? null) !== $day || !is_array($data['games'] ?? null)) {
                        throw new \RuntimeException('Invalid dated NHL schedule');
                    }
                    $active = collect($data['games'])->contains(fn($game)=>in_array($game['gameState'] ?? '', ['LIVE', 'CRIT', 'PRE'], true)
                        || (!in_array($game['gameState'] ?? '', ['FINAL','OFF'], true) && CarbonImmutable::parse($game['startTimeUTC'])->lte($now)));
                    // Today's live/final transitions need minute checks; future
                    // schedules and completed historical days can wait longer.
                    $ttl = $active ? 60 : ($date->lt($today) ? 3600 : ($date->gt($today) ? 900 : 300));
                    Cache::put($cacheKey, $data, $ttl);
                }
                $finished = count($data['games']) > 0;
                foreach ($data['games'] as $game) {
                    $state = $game['gameState'] ?? '';
                    if (in_array($state, ['FINAL', 'OFF'], true)) continue;
                    // Postponed games must not keep live/pregame windows open.
                    if (($game['gameScheduleState'] ?? '') === 'PPD') { $finished = false; continue; }
                    $finished = false;
                    $start = CarbonImmutable::parse($game['startTimeUTC']);
                    $live = $live || in_array($state, ['LIVE', 'CRIT'], true)
                        || ($now->betweenIncluded($start, $start->addHours(5)) && in_array($state, ['FUT', 'PRE'], true));
                    $pregame = $pregame || $now->betweenIncluded($start->subHours(6), $start);
                    if ($day === $today->toDateString() && $start->gt($now)) $pending = true;
                }
                if ($finished && $date->lte($today)) {
                    if ($day === $today->toDateString()) $todayFinished = true;
                    $key = 'collectors:final:'.$day;
                    Cache::add($key, $now->toIso8601String(), 3 * 86400);
                    $finals[] = Cache::get($key);
                }
            }
            return $this->window = compact('live', 'pregame', 'pending', 'finals', 'todayFinished') + ['known'=>true];
        } catch (\Throwable $e) {
            Log::warning('Collector NHL window unavailable', ['error'=>$e->getMessage()]);
            // Never let an NHL outage silence minute-by-minute fantasy scoring.
            return $this->window = ['live'=>true, 'pregame'=>true, 'pending'=>false, 'finals'=>[], 'known'=>false, 'todayFinished'=>true];
        }
    }

    public function reconciliationDue(string $key): bool
    {
        $row = DB::table('collector_job_statuses')->where('job_key', $key)->first();
        foreach ($this->window()['finals'] as $observed) {
            if (!$row || $row->status !== 'success' || !$row->ran_at
                || CarbonImmutable::parse($row->ran_at, config('app.timezone'))->lt(CarbonImmutable::parse($observed))) return true;
        }
        return false;
    }

    public function due(string $job): bool
    {
        $now = CarbonImmutable::now(FantasyDay::TIMEZONE);
        $minute = (int)$now->minute;
        $window = $this->window();
        return match ($job) {
            'scores' => $window['live'] || $minute === 0 || ($minute % 5 === 0 && $this->reconciliationDue('scores')),
            'standings' => $minute % 5 === 0 && ($window['live'] || $this->reconciliationDue('standings')),
            'players', 'teams' => ($window['live'] || $window['pregame'] || ($now->hour >= 8 && $now->hour < 22))
                ? $minute % 15 === 0 : ($minute === 0 && $now->hour % 3 === 0),
            'goalies' => $window['pregame'] ? $minute % 5 === 0 : ($minute === 0 && $now->hour % 3 === 0),
            'advisor' => ($minute === 0 && $now->hour % 3 === 0) || ($window['pregame'] && $minute % 30 === 0),
            'odds' => $window['known'] && $window['pending'],
            default => false,
        };
    }
}
