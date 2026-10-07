<?php
namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class DatabaseMaintenance
{
    public function run(?callable $log=null): array
    {
        $result=[];
        $scope=new StatsPlayerScope;
        $keep=[];
        foreach(DB::table('season_player_stats')->where('season_gp',0)->get(['player_id','player_name','nhl_team']) as $player){
            if($scope->listed((array)$player))$keep[]=$player->player_id;
        }
        $result['zero_game_stats']=DB::table('season_player_stats')->where('season_gp',0)->whereNotIn('player_id',$keep)->delete();
        $result['unused_projections']=DB::table('player_projections')->where('season_gp',0)
            ->whereNotIn('player_id',DB::table('player_projection_baselines')->select('player_id'))->whereNotIn('player_id',$keep)->delete();
        // Raw responses duplicate the canonical snapshot and are never used for page rendering or replay.
        // Compact in SQL so a large historic response never travels into the PHP worker.
        $mysql=DB::connection()->getDriverName()==='mysql';
        $extract=$mysql?'JSON_EXTRACT':'json_extract';
        $object=$mysql?'JSON_OBJECT':'json_object';
        $sourceBytesBefore=(int)DB::table('live_scoring_snapshots')->selectRaw('COALESCE(SUM(LENGTH(source_payload)),0) AS bytes')->value('bytes');
        $result['compacted_sources']=DB::table('live_scoring_snapshots')
            ->whereRaw($extract."(source_payload, '$.compact_version') IS NULL")
            ->update(['source_payload'=>DB::raw($object."('compact_version',1,'source_date',source_date,'player_count',player_count)")]);
        $sourceBytesAfter=(int)DB::table('live_scoring_snapshots')->selectRaw('COALESCE(SUM(LENGTH(source_payload)),0) AS bytes')->value('bytes');
        $result['source_bytes_saved']=max(0,$sourceBytesBefore-$sourceBytesAfter);
        $cutoff=app(FantasyDay::class)->today()->subDays(7)->toDateString();
        foreach(['active_daily_players'=>'game_date','active_available_goalies'=>'game_date','active_starting_goalies'=>'game_date',
            'active_fantasy_rosters'=>'game_date','active_daily_scores'=>'game_date','active_matchup_scores'=>'game_date',
            'todays_odds'=>'game_date','team_daily_moves'=>'move_date','lineup_advice'=>'advice_date'] as $table=>$column){
            if(!Schema::hasTable($table))continue;
            $latest=DB::table($table)->max($column);
            if(!$latest)continue;
            // Always retain the last three collected days, even after a prolonged upstream outage.
            $before=min($cutoff,CarbonImmutable::parse($latest)->subDays(2)->toDateString());
            $result[$table]=DB::table($table)->where($column,'<',$before)->delete();
        }
        $result['expired_sessions']=Schema::hasTable('sessions')?DB::table('sessions')->where('last_activity','<',now()->subMinutes((int)config('session.lifetime',10080))->timestamp)->delete():0;
        $latestRuns=DB::table('job_run_history')->selectRaw('MAX(id) AS id')->groupBy('job_name')->pluck('id')->all();
        $result['old_job_runs']=DB::table('job_run_history')->where('completed_at','<',now()->subDays(30))->whereNotIn('id',$latestRuns)->delete();
        PublicData::forget('player-projections');
        PublicData::forget('standings-awards');
        foreach($result as $key=>$count)if($log)$log($key.': '.$count);
        return $result;
    }

    public function report(): array
    {
        if(DB::connection()->getDriverName()!=='mysql')return [];
        return array_map(fn($r)=>(array)$r,DB::select('SELECT table_name AS name, table_rows AS estimated_rows, data_length AS data_bytes, index_length AS index_bytes, data_free AS reusable_bytes FROM information_schema.tables WHERE table_schema = DATABASE() ORDER BY data_length + index_length DESC'));
    }
}
