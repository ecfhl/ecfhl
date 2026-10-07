<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlayerProfile
{
    public function data(string $id): array
    {
        $player=DB::table('season_player_stats')->where('player_id',$id)->first();
        $projection=DB::table('player_projections')->where('player_id',$id)->first();
        $baseline=DB::table('player_projection_baselines')->where('player_id',$id)->first();
        $roster=DB::table('active_fantasy_rosters')->where('player_id',$id)
            ->where('game_date',fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))->orderByDesc('id')->first();
        $today=app(FantasyDay::class)->today();
        if(!$player && ($roster || $baseline)){
            // Rostered prospects still open a profile without persisting unused zero-stat rows.
            $metadata=$roster??$baseline;
            $position=strtoupper((string)($metadata->position??''));
            $player=(object)['player_id'=>$id,'season_id'=>FantraxProjectionSource::SEASON_ID,
                'player_name'=>$metadata->player_name,'nhl_team'=>$metadata->nhl_team??null,
                'position'=>preg_match('/\bG\b/',$position)?'G':(preg_match('/\bD\b/',$position)?'D':'F'),
                'rookie'=>null,'season_fpts'=>$projection->season_fpts??0,'season_gp'=>$projection->season_gp??0,
                'season_fpts_per_game'=>$projection->season_fpts_per_game??0,'stats_json'=>'{}',
                'stats_through'=>$projection->window_end_date??$today->toDateString()];
        }
        abort_unless($player,404);
        $age=app(PlayerBirthdates::class)->age($player->player_name,$today);
        $games=app(PlayerGames::class);$team=PlayerGames::team($player->nhl_team);
        $todayGame=$games->forDate($today->toDateString())[$team]??null;
        $tomorrowGame=$games->forDate($today->addDay()->toDateString())[$team]??null;
        $statRows=[['label'=>'Current Season','fpts'=>$player->season_fpts,'gp'=>$player->season_gp,'rate'=>$player->season_fpts_per_game]];
        foreach([7,14,21] as $days) $statRows[]=['label'=>'Last '.$days.' days','fpts'=>$projection->{'fpts_'.$days.'d'}??null,'gp'=>$projection->{'gp_'.$days.'d'}??null,'rate'=>$projection->{'fpts_per_game_'.$days.'d'}??null];
        $statRows[]=['label'=>'Fantrax Proj','fpts'=>$baseline->fantrax_season_fpts??null,'gp'=>null,'rate'=>$baseline->fantrax_fpts_per_game??null];
        $categoryLabels=$player->position==='G'
            ? ['FPTS'=>'Fantasy points','FPTS/GP'=>'Fantasy points per game','GP'=>'Games played','W'=>'Wins','L'=>'Losses','OTL'=>'Overtime losses','SO'=>'Shutouts']
            : ['FPTS'=>'Fantasy points','FPTS/GP'=>'Fantasy points per game','GP'=>'Games played','G'=>'Goals','A'=>'Assists','PPG'=>'Power-play goals','SHG'=>'Short-handed goals','GWG'=>'Game-winning goals'];
        $values=function ($stats,$fpts,$rate,$gp) {
            return ['FPTS'=>$fpts,'FPTS/GP'=>$rate,'GP'=>$gp,'OTL'=>$stats['OTL']??$stats['OL']??null,'SO'=>$stats['SO']??$stats['SHO']??null]+$stats;
        };
        $seasonStats=$values(json_decode($player->stats_json,true)?:[],$player->season_fpts,$player->season_fpts_per_game,$player->season_gp);
        $seasonRows=[['season'=>$player->season_id,'stats'=>$seasonStats]];
        $previous=DB::table('historical_player_stats')->where('season_id','2025-26')->where('player_id',$id)->first();
        if ($previous) $seasonRows[]=['season'=>$previous->season_id,'stats'=>$values(json_decode($previous->stats_json,true)?:[],$previous->fpts,$previous->fpts_per_game,$previous->gp)];
        $teamSlug=$roster?Str::slug($roster->fantasy_team_name):null;
        return compact('player','age','projection','baseline','roster','teamSlug','todayGame','tomorrowGame','statRows','seasonStats','categoryLabels','seasonRows');
    }
}
