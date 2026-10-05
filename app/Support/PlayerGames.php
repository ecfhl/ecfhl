<?php
namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class PlayerGames
{
    public static function team(?string $value): string
    {
        $team=strtoupper(trim((string)$value));
        return ['LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL'][$team] ?? $team;
    }

    public function forDate(string $date): array
    {
        return PublicData::remember('player-games:'.$date,30,function() use($date){
            $games=[];
            foreach (['todays_odds'=>'team','active_starting_goalies'=>'team','active_daily_players'=>'team','active_fantasy_rosters'=>'nhl_team'] as $table=>$field) {
                $hasTime=Schema::hasColumn($table,'game_time');
                $rows=DB::table($table)->where('game_date',$date)->whereNotNull('opponent')->orderBy('id')
                    ->get([$field.' as team','opponent','home_away',...($hasTime?['game_time']:[])]);
                foreach($rows as $row){
                    $team=self::team($row->team);$opponent=self::team(ltrim(trim($row->opponent),'@'));
                    if(!isset(DailyFaceoffPowerPlay::TEAMS[$team],DailyFaceoffPowerPlay::TEAMS[$opponent])||$team===$opponent)continue;
                    $away=strtoupper($row->home_away??'')==='AWAY'||str_starts_with(trim($row->opponent),'@');
                    $time=trim((string)($row->game_time??''));
                    $old=$games[$team]??null;
                    $games[$team]=['opponent'=>$opponent,'away'=>$away,'time'=>$time ?: ($old['time']??null)];
                    $reverse=$games[$opponent]??null;
                    $games[$opponent]=['opponent'=>$team,'away'=>!$away,'time'=>$time ?: ($reverse['time']??null)];
                }
            }
            $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date);
            foreach($snapshot['players']??[] as $player){
                $team=self::team($player['nhl_team']??null);$opp=self::team($player['opponent']??null);
                if(!isset(DailyFaceoffPowerPlay::TEAMS[$team],DailyFaceoffPowerPlay::TEAMS[$opp])||$team===$opp)continue;
                $old=$games[$team]??null;
                $time=!empty($player['starts_at'])?CarbonImmutable::parse($player['starts_at'])->setTimezone('America/Halifax')->format('g:i a T'):($old['time']??null);
                $away=($player['home_away']??'')==='AWAY';
                $games[$team]=['opponent'=>$opp,'away'=>$away,'time'=>$time];
                $games[$opp]=['opponent'=>$team,'away'=>!$away,'time'=>$time];
            }
            return $games;
        });
    }
}
