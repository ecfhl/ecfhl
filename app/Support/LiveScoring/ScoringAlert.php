<?php
namespace App\Support\LiveScoring;
final class ScoringAlert {
 public static function payload(array $snapshot,array $player): array {
  $statLine=[];
  foreach(['G','A','PPG','SHG','GWG'] as $stat)$statLine[]=$stat.': '.(int)($player['stats'][$stat]['value']??0);
  return ['title'=>'ECFHL · '.$snapshot['teams'][$player['fantasy_team_id']]['name'],
   'body'=>$player['player_name'].' · '.$player['daily_fpts']." FPts\n".implode(' · ',$statLine),
   'url'=>'/teams/current?date='.$snapshot['fantasy_date'],'fantasy_team_id'=>$player['fantasy_team_id']];
 }
}
