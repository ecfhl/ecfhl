<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class PlayerProfile
{
    public function data(string $id): array
    {
        $player=DB::table('season_player_stats')->where('player_id',$id)->first();
        abort_unless($player,404);
        $projection=DB::table('player_projections')->where('player_id',$id)->first();
        $baseline=DB::table('player_projection_baselines')->where('player_id',$id)->first();
        $roster=DB::table('active_fantasy_rosters')->where('player_id',$id)
            ->where('game_date',fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))->orderByDesc('id')->first();
        $today=app(FantasyDay::class)->today();
        $games=app(PlayerGames::class);$team=PlayerGames::team($player->nhl_team);
        $todayGame=$games->forDate($today->toDateString())[$team]??null;
        $tomorrowGame=$games->forDate($today->addDay()->toDateString())[$team]??null;
        $statRows=[['label'=>'Current Season','fpts'=>$player->season_fpts,'gp'=>$player->season_gp,'rate'=>$player->season_fpts_per_game]];
        foreach([7,14,21] as $days) $statRows[]=['label'=>'Last '.$days.' days','fpts'=>$projection->{'fpts_'.$days.'d'}??null,'gp'=>$projection->{'gp_'.$days.'d'}??null,'rate'=>$projection->{'fpts_per_game_'.$days.'d'}??null];
        $statRows[]=['label'=>'Fantrax Proj','fpts'=>$baseline->fantrax_season_fpts??null,'gp'=>null,'rate'=>$baseline->fantrax_fpts_per_game??null];
        $seasonStats=json_decode($player->stats_json,true)?:[];
        $seasonStats['Pts']=$seasonStats['Pt']??$seasonStats['Pts']??null;
        $seasonStats['GP']=$player->season_gp ?? $seasonStats['GP'] ?? null;
        $categoryLabels=$player->position==='G'?['GP'=>'Games played','W'=>'Wins','L'=>'Losses','OL'=>'Overtime losses','GAA'=>'Goals against average','SV%'=>'Save percentage','SHO'=>'Shutouts','GA'=>'Goals against','SV'=>'Saves']:['GP'=>'Games played','G'=>'Goals','A'=>'Assists','Pts'=>'Points','PPG'=>'Power-play goals','SHG'=>'Short-handed goals','GWG'=>'Game-winning goals','SOG'=>'Shots on goal'];
        $categoryLabels=array_filter($categoryLabels,fn($label,$key)=>isset($seasonStats[$key])&&$seasonStats[$key]!=='',ARRAY_FILTER_USE_BOTH);
        $teamSlug=$roster?Str::slug($roster->fantasy_team_name):null;
        return compact('player','projection','baseline','roster','teamSlug','todayGame','tomorrowGame','statRows','seasonStats','categoryLabels');
    }
}
