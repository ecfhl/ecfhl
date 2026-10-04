<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // Season award standings should reflect Fantrax's current season totals through today.
        // Recent-rate windows still use the same Fantrax actual-stat source.
        $end = CarbonImmutable::parse($date, 'America/Halifax')->toDateString();
        $seasonStart = $baseline[0]['season_start'];
        $windows = [];
        $cache = [];
        // Pull the current Fantrax season totals as part of this daily job.
        $seasonActual = $seasonStart <= $end ? $this->source->seasonActual($seasonStart, $end) : [];
        if ($seasonStart <= $end) $cache[$seasonStart] = $seasonActual;
        if ($seasonStart <= $end) foreach ($baseline as $player) if (!isset($seasonActual[$player['player_id']])) throw new RuntimeException('Missing season actual stats for baseline player '.$player['player_id'].'.');
        if ($log) $log('Season actual FPts/GP collected through '.$end.'.');
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
        $weights = ProjectionSettings::weights();
        $rows = [];
        foreach ($baseline as $player) {
            $row = ['player_id'=>$player['player_id'], 'as_of_date'=>$date, 'window_end_date'=>$end, 'refreshed_at'=>$now];
            $seasonStat = $seasonActual[$player['player_id']] ?? ['fpts'=>0, 'gp'=>0];
            $row['season_fpts'] = $seasonStat['fpts'];
            $row['season_gp'] = $seasonStat['gp'];
            $row['season_fpts_per_game'] = ProjectionMath::rate((float)$seasonStat['fpts'], (int)$seasonStat['gp']);
            $rates = [];
            foreach ([7, 14, 21] as $days) {
                $stat = $windows[$days][$player['player_id']] ?? ['fpts'=>0, 'gp'=>0];
                $rate = ProjectionMath::rate((float)$stat['fpts'], (int)$stat['gp']);
                $row['fpts_'.$days.'d'] = $stat['fpts'];
                $row['gp_'.$days.'d'] = $stat['gp'];
                $row['fpts_per_game_'.$days.'d'] = $rate;
                $rates[] = $rate;
            }
            $row['projected_fpts_per_game'] = ProjectionMath::weighted([
                'fantrax'=>(float)$player['fantrax_fpts_per_game'], 'season'=>$row['season_fpts_per_game'],
                '7d'=>$rates[0], '14d'=>$rates[1], '21d'=>$rates[2],
            ], $weights);
            $rows[] = $row;
        }
        $seasonRows = [];
        $columns = [];
        $baselineById = array_column($baseline, null, 'player_id');
        foreach ($seasonActual as $id => $stat) {
            $metadata = $baselineById[$id] ?? [];
            $name = $stat['player_name'] ?? $metadata['player_name'] ?? '';
            if ($name === '') continue;
            $position = strtoupper($stat['position'] ?? $metadata['position'] ?? '');
            $position = preg_match('/\bG\b/', $position) ? 'G' : (preg_match('/\bD\b/', $position) ? 'D' : 'F');
            $group = $position === 'G' ? 'goalie' : 'skater';
            $columns[$group] = array_merge($columns[$group] ?? [], $stat['stat_columns'] ?? []);
            $seasonRows[] = ['player_id'=>(string)$id, 'season_id'=>FantraxProjectionSource::SEASON_ID, 'player_name'=>$name,
                'nhl_team'=>$stat['nhl_team'] ?? $metadata['nhl_team'] ?? null, 'position'=>$position, 'rookie'=>$stat['rookie'] ?? null,
                'season_fpts'=>$stat['fpts'], 'season_gp'=>$stat['gp'], 'season_fpts_per_game'=>ProjectionMath::rate((float)$stat['fpts'], (int)$stat['gp']),
                'stats_json'=>json_encode($stat['stats'] ?? [], JSON_THROW_ON_ERROR), 'stats_through'=>$end, 'refreshed_at'=>$now];
        }
        $storeSeason = Schema::hasTable('season_player_stats') && $seasonRows;
        DB::transaction(function () use ($capture, $baseline, $rows, $now, $storeSeason, $seasonRows, $columns) {
            if ($capture) foreach (array_chunk($baseline, 100) as $batch) DB::table('player_projection_baselines')->insert(array_map(fn($r)=>$r + ['captured_at'=>$now], $batch));
            DB::table('player_projections')->delete();
            foreach (array_chunk($rows, 100) as $batch) DB::table('player_projections')->insert($batch);
            if ($storeSeason) {
                DB::table('season_player_stats')->delete();
                foreach (array_chunk($seasonRows, 100) as $batch) DB::table('season_player_stats')->insert($batch);
                DB::table('season_player_stat_columns')->delete();
                foreach ($columns as $group => $labels) DB::table('season_player_stat_columns')->insert(['group'=>$group, 'columns_json'=>json_encode($labels, JSON_THROW_ON_ERROR)]);
            }
        });
        PublicData::forget('player-projections');
        PublicData::forget('season-player-columns');
        if ($log && $storeSeason) $log(count($seasonRows).' complete season player stat lines stored, including Fantrax rookie flags.');
        if ($log) $log('1000 player projections regenerated for '.$date.'. Fantrax baseline '.($capture ? 'captured' : 'unchanged').'.');
        return count($rows);
    }
}
