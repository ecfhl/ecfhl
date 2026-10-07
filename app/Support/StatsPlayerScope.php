<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Zero-game exceptions come only from Daily Faceoff, not fantasy roster ownership. */
final class StatsPlayerScope
{
    private array $names=[];

    public function __construct()
    {
        foreach(['active_line_combinations','active_pp_lines','active_starting_goalies'] as $table){
            if(!Schema::hasTable($table))continue;
            $query=DB::table($table)->select('team','player_name');
            if($table==='active_starting_goalies')$query->where('game_date','>=',app(FantasyDay::class)->today()->subDay()->toDateString());
            foreach($query->get() as $row)$this->names[self::key($row->team,$row->player_name)]=true;
        }
    }

    private static function key(?string $team,string $name): string
    {
        return PlayerGames::team($team).'|'.PlayerProjections::name($name);
    }

    public function listed(array $player): bool
    {
        return isset($this->names[self::key($player['nhl_team']??$player['team']??null,(string)($player['player_name']??''))]);
    }

    public function keeps(array $stat,array $metadata=[]): bool
    {
        return (int)($stat['gp']??0)>0 || $this->listed($stat+$metadata);
    }
}
