<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['active_daily_players', 'active_available_goalies'] as $name) {
            Schema::table($name, fn(Blueprint $table)=>$table->string('player_id')->nullable()->index());
        }
        Schema::create('player_projection_baselines', function (Blueprint $table) {
            $table->string('player_id')->primary();
            $table->string('season_id');
            $table->string('player_name');
            $table->string('nhl_team')->nullable();
            $table->string('position');
            $table->unsignedInteger('source_rank');
            $table->decimal('fantrax_fpts_per_game', 14, 6);
            $table->decimal('fantrax_season_fpts', 14, 6);
            $table->date('season_start');
            $table->timestamp('captured_at');
        });
        Schema::create('player_projections', function (Blueprint $table) {
            $table->string('player_id')->primary();
            $table->date('as_of_date');
            $table->date('window_end_date');
            foreach ([7, 14, 21] as $days) {
                $table->decimal('fpts_'.$days.'d', 14, 6);
                $table->unsignedInteger('gp_'.$days.'d');
                $table->decimal('fpts_per_game_'.$days.'d', 14, 6);
            }
            $table->decimal('projected_fpts_per_game', 14, 6);
            $table->timestamp('refreshed_at');
        });
    }

    public function down(): void
    {
        foreach (['active_daily_players', 'active_available_goalies'] as $name) {
            Schema::table($name, function(Blueprint $table) { $table->dropIndex(['player_id']); $table->dropColumn('player_id'); });
        }
        Schema::dropIfExists('player_projections');
        Schema::dropIfExists('player_projection_baselines');
    }
};
