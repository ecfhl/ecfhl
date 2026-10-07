<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    private array $indexes=[
        'active_fantasy_rosters'=>['rosters_day_player'=>['game_date','player_id','id']],
        'season_player_stats'=>['season_players_points'=>['season_fpts','player_name','player_id'],'season_players_position_points'=>['position','season_fpts','player_name','player_id']],
        'player_projections'=>['projections_as_of'=>['as_of_date']],
        'job_run_history'=>['job_runs_completed'=>['completed_at'],'job_runs_job_completed'=>['job_name','completed_at']],
        'push_notifications'=>['push_category_latest'=>['category','id']],
    ];
    public function up(): void
    {
        foreach($this->indexes as $table=>$indexes)foreach($indexes as $name=>$columns)Schema::table($table,fn(Blueprint $t)=>$t->index($columns,$name));
    }
    public function down(): void
    {
        foreach($this->indexes as $table=>$indexes)foreach($indexes as $name=>$columns)Schema::table($table,fn(Blueprint $t)=>$t->dropIndex($name));
    }
};
