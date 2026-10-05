<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class ProjectionSettings
{
    public const LABELS = ['fantrax'=>'Fantrax projection', 'season'=>'Season to date', '7d'=>'Last 7 days', '14d'=>'Last 14 days', '21d'=>'Last 21 days'];

    public static function weights(): array
    {
        $weights = ProjectionMath::DEFAULT_WEIGHTS;
        if (!Schema::hasTable('projection_settings')) return $weights;
        $row = DB::table('projection_settings')->where('id', 1)->first();
        if ($row) foreach ($weights as $key => $_) $weights[$key] = (float)$row->{'weight_'.$key};
        return $weights;
    }

    public static function description(): string
    {
        $parts = [];
        foreach (self::weights() as $key => $weight) if ($weight > 0) $parts[] = rtrim(rtrim(number_format($weight, 2, '.', ''), '0'), '.').'% '.self::LABELS[$key];
        return implode(' + ', $parts);
    }

    public static function validate(array $weights): array
    {
        // Validate again at the calculation boundary, including precision used by the database.
        $total = 0;
        foreach (ProjectionMath::DEFAULT_WEIGHTS as $key => $_) {
            $value = $weights[$key] ?? null;
            if (!is_numeric($value) || !is_finite((float)$value) || $value < 0 || $value > 100 || abs((float)$value * 100 - round((float)$value * 100)) > 0.00001)
                throw ValidationException::withMessages(['weights'=>'Each percentage must be between 0 and 100, with at most two decimal places.']);
            $weights[$key] = (float)$value;
            $total += (int)round($value * 100);
        }
        if ($total !== 10000) throw ValidationException::withMessages(['weights'=>'The five percentages must total 100%.']);
        return $weights;
    }

    public static function preview(array $weights): array
    {
        $weights = self::validate($weights);
        $rows = DB::table('player_projections as p')->leftJoin('player_projection_baselines as b', 'b.player_id', '=', 'p.player_id')
            ->leftJoin('season_player_stats as s', 's.player_id', '=', 'p.player_id')
            ->selectRaw('COALESCE(b.player_name, s.player_name) as player_name, COALESCE(b.nhl_team, s.nhl_team) as nhl_team, COALESCE(b.position, s.position) as position')
            ->addSelect('p.player_id', 'b.source_rank', 'b.fantrax_fpts_per_game',
                'p.season_fpts_per_game', 'p.season_gp', 'p.fpts_per_game_7d', 'p.fpts_per_game_14d', 'p.fpts_per_game_21d', 'p.window_end_date')->get();
        $players = $rows->map(function ($row) use ($weights) {
            return ['player_id'=>$row->player_id, 'name'=>$row->player_name, 'team'=>$row->nhl_team, 'position'=>$row->position,
                'source_rank'=>$row->source_rank === null ? PHP_INT_MAX : (int)$row->source_rank, 'season_gp'=>(int)$row->season_gp, 'season_fpts_per_game'=>$row->season_fpts_per_game === null ? null : (float)$row->season_fpts_per_game,
                'myproj'=>ProjectionMath::weighted(['fantrax'=>$row->fantrax_fpts_per_game, 'season'=>$row->season_fpts_per_game,
                    '7d'=>$row->fpts_per_game_7d, '14d'=>$row->fpts_per_game_14d, '21d'=>$row->fpts_per_game_21d], $weights)];
        })->filter(fn($player)=>$player['myproj'] !== null && $player['name'] !== null)->sort(fn($a, $b)=>($b['myproj'] <=> $a['myproj']) ?: ($a['source_rank'] <=> $b['source_rank']))->take(10)->values()->all();
        return ['players'=>$players, 'count'=>$rows->count(), 'stats_through'=>$rows->max('window_end_date'), 'weights'=>$weights];
    }

    public static function save(array $weights): int
    {
        $weights = self::validate($weights);

        // Share the collector lock so an in-flight daily refresh cannot overwrite the new calculation.
        $path = storage_path('app/player-projections.lock');
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
        $lock = fopen($path, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock) fclose($lock);
            throw ValidationException::withMessages(['weights'=>'Projections are being refreshed. Try saving again when the refresh finishes.']);
        }
        try {
            $count = DB::transaction(function () use ($weights) {
                $values = ['updated_at'=>now()];
                foreach (ProjectionMath::DEFAULT_WEIGHTS as $key => $_) $values['weight_'.$key] = $weights[$key];
                DB::table('projection_settings')->where('id', 1)->update($values);
                $columns = ['fantrax'=>'(SELECT fantrax_fpts_per_game FROM player_projection_baselines WHERE player_projection_baselines.player_id = player_projections.player_id)',
                    'season'=>'season_fpts_per_game', '7d'=>'fpts_per_game_7d', '14d'=>'fpts_per_game_14d', '21d'=>'fpts_per_game_21d'];
                DB::table('player_projections')->update(['projected_fpts_per_game'=>DB::raw(ProjectionMath::sql($columns, $weights))]);
                return DB::table('player_projections')->count();
            });
            PublicData::forget('player-projections');
            return $count;
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
