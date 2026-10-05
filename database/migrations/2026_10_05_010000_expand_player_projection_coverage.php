<?php

use App\Support\ProjectionMath;
use App\Support\ProjectionSettings;
use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private function columns(Blueprint $table, bool $nullable): void
    {
        foreach ([7, 14, 21] as $days) {
            $table->decimal('fpts_'.$days.'d', 14, 6)->nullable($nullable)->change();
            $table->unsignedInteger('gp_'.$days.'d')->nullable($nullable)->change();
            $table->decimal('fpts_per_game_'.$days.'d', 14, 6)->nullable($nullable)->change();
        }
        $table->decimal('season_fpts', 10, 2)->nullable($nullable)->default(null)->change();
        $table->unsignedInteger('season_gp')->nullable($nullable)->default(null)->change();
        $table->decimal('season_fpts_per_game', 10, 4)->nullable($nullable)->default(null)->change();
        $table->decimal('projected_fpts_per_game', 14, 6)->nullable($nullable)->change();
    }

    public function up(): void
    {
        Schema::table('player_projections', fn(Blueprint $table)=>$this->columns($table, true));
        $weights = ProjectionSettings::weights();
        $seasonStart = DB::table('player_projection_baselines')->min('season_start');
        // Reuse known season inputs immediately; unknown rolling inputs remain null.
        // A window covering the whole season can safely reuse the same actuals.
        DB::table('season_player_stats as s')->leftJoin('player_projections as p', 'p.player_id', '=', 's.player_id')
            ->whereNull('p.player_id')->select('s.*')->orderBy('s.player_id')->chunkById(100, function ($players) use ($weights, $seasonStart) {
                $rows = [];
                foreach ($players as $player) {
                    $row = ['player_id'=>$player->player_id, 'as_of_date'=>$player->stats_through, 'window_end_date'=>$player->stats_through,
                        'season_fpts'=>$player->season_fpts, 'season_gp'=>$player->season_gp, 'season_fpts_per_game'=>$player->season_fpts_per_game,
                        'refreshed_at'=>$player->refreshed_at];
                    $rates = ['season'=>$player->season_fpts_per_game];
                    foreach ([7, 14, 21] as $days) {
                        $wholeSeason = $seasonStart && CarbonImmutable::parse($player->stats_through)->subDays($days - 1)->toDateString() <= $seasonStart;
                        $row['fpts_'.$days.'d'] = $wholeSeason ? $player->season_fpts : null;
                        $row['gp_'.$days.'d'] = $wholeSeason ? $player->season_gp : null;
                        $rates[$days.'d'] = $row['fpts_per_game_'.$days.'d'] = $wholeSeason ? $player->season_fpts_per_game : null;
                    }
                    $row['projected_fpts_per_game'] = ProjectionMath::weighted($rates, $weights);
                    $rows[] = $row;
                }
                DB::table('player_projections')->insert($rows);
            }, 's.player_id', 'player_id');
        \App\Support\PublicData::forget('player-projections');
    }

    public function down(): void
    {
        DB::table('player_projections')->whereNotIn('player_id', DB::table('player_projection_baselines')->select('player_id'))->delete();
        foreach (['season_fpts', 'season_gp', 'season_fpts_per_game', 'projected_fpts_per_game', 'fpts_7d', 'gp_7d', 'fpts_per_game_7d', 'fpts_14d', 'gp_14d', 'fpts_per_game_14d', 'fpts_21d', 'gp_21d', 'fpts_per_game_21d'] as $column) {
            DB::table('player_projections')->whereNull($column)->update([$column=>0]);
        }
        Schema::table('player_projections', fn(Blueprint $table)=>$this->columns($table, false));
    }
};
