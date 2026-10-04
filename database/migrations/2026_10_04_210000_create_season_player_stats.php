<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('season_player_stats', function (Blueprint $table) {
            $table->string('player_id')->primary();
            $table->string('season_id');
            $table->string('player_name');
            $table->string('nhl_team', 8)->nullable();
            $table->string('position', 8);
            $table->boolean('rookie')->nullable();
            $table->decimal('season_fpts', 14, 6);
            $table->unsignedInteger('season_gp');
            $table->decimal('season_fpts_per_game', 14, 6);
            $table->json('stats_json');
            $table->date('stats_through');
            $table->timestamp('refreshed_at');
            $table->index(['position', 'rookie', 'season_fpts'], 'season_players_filter');
        });
        Schema::create('season_player_stat_columns', function (Blueprint $table) {
            $table->string('group')->primary();
            $table->json('columns_json');
        });
        // Retain the existing season rates until the first complete-stat refresh succeeds.
        if (!Schema::hasColumn('player_projections', 'season_fpts')) return;
        $rows = DB::table('player_projections as p')->join('player_projection_baselines as b', 'b.player_id', '=', 'p.player_id')
            ->select('p.player_id','b.season_id','b.player_name','b.nhl_team','b.position','p.season_fpts','p.season_gp','p.season_fpts_per_game','p.window_end_date as stats_through','p.refreshed_at')->get();
        foreach ($rows->chunk(100) as $batch) DB::table('season_player_stats')->insert($batch->map(fn($r)=>(array)$r + ['stats_json'=>'{}'])->all());
    }
    public function down(): void
    {
        Schema::dropIfExists('season_player_stats');
        Schema::dropIfExists('season_player_stat_columns');
    }
};
