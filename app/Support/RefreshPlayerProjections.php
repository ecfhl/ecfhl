<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class RefreshPlayerProjections
{
    public function __construct(private FantraxProjectionSource $source) {}

    public function refresh(CarbonImmutable $today, bool $force = false, ?callable $log = null): int
    {
        $date = $today->setTimezone('America/Halifax')->toDateString();
        if (!$force && DB::table('player_projections')->where('as_of_date', $date)->count() === 1000) {
            if ($log) $log('1,000 player projections already current for '.$date.'.');
            return 1000;
        }
        $baseline = DB::table('player_projection_baselines')->orderBy('source_rank')->get()->map(fn($r)=>(array)$r)->all();
        $capture = !$baseline;
        if ($capture) $baseline = $this->source->baseline();
        if (count($baseline) !== 1000 || collect($baseline)->pluck('season_id')->unique()->all() !== [FantraxProjectionSource::SEASON_ID]) throw new RuntimeException('The frozen projection baseline is incomplete or belongs to a different season.');
        $end = CarbonImmutable::parse($date, 'America/Halifax')->subDay()->toDateString();
        $seasonStart = $baseline[0]['season_start'];
        $windows = [];
        $cache = [];
        foreach ([7, 14, 21] as $days) {
            $start = max(CarbonImmutable::parse($end)->subDays($days - 1)->toDateString(), $seasonStart);
            if ($start > $end) $windows[$days] = [];
            else {
                if (!isset($cache[$start])) $cache[$start] = $this->source->actual($start, $end);
                $windows[$days] = $cache[$start];
                foreach ($baseline as $player) if (!isset($windows[$days][$player['player_id']])) throw new RuntimeException('Missing actual stats for baseline player '.$player['player_id'].'.');
            }
            if ($log) $log($days.'-day actual FPts/GP collected through '.$end.'.');
        }
        $now = now();
        $rows = [];
        foreach ($baseline as $player) {
            $row = ['player_id'=>$player['player_id'], 'as_of_date'=>$date, 'window_end_date'=>$end, 'refreshed_at'=>$now];
            $rates = [];
            foreach ([7, 14, 21] as $days) {
                $stat = $windows[$days][$player['player_id']] ?? ['fpts'=>0, 'gp'=>0];
                $rate = ProjectionMath::rate((float)$stat['fpts'], (int)$stat['gp']);
                $row['fpts_'.$days.'d'] = $stat['fpts'];
                $row['gp_'.$days.'d'] = $stat['gp'];
                $row['fpts_per_game_'.$days.'d'] = $rate;
                $rates[] = $rate;
            }
            $row['projected_fpts_per_game'] = ProjectionMath::average((float)$player['fantrax_fpts_per_game'], ...$rates);
            $rows[] = $row;
        }
        DB::transaction(function () use ($capture, $baseline, $rows, $now) {
            if ($capture) foreach (array_chunk($baseline, 100) as $batch) DB::table('player_projection_baselines')->insert(array_map(fn($r)=>$r + ['captured_at'=>$now], $batch));
            DB::table('player_projections')->delete();
            foreach (array_chunk($rows, 100) as $batch) DB::table('player_projections')->insert($batch);
        });
        if ($log) $log('1000 player projections regenerated for '.$date.'. Fantrax baseline '.($capture ? 'captured' : 'unchanged').'.');
        return count($rows);
    }
}
