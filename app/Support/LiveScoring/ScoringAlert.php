<?php
namespace App\Support\LiveScoring;
final class ScoringAlert {
 public static function payload(array $snapshot,array $player,?array $previous=null): array {
  $stats=$player['stats']??[];
  $value=static fn($key)=>(int)($stats[$key]['value']??0);
  $gain=static fn($key)=>max(0,$value($key)-(int)($previous['stats'][$key]['value']??0));
  $isGoalie=strtoupper((string)($player['position']??$player['pos']??''))==='G';
  $events=[];
  if($isGoalie){
   if($gain('W'))$events[]='Win';
   if($gain('SHO') || $gain('SO'))$events[]='Shutout';
  }
  if($gain('G')){
   $kind=[];
   foreach(['GWG','PPG','SHG'] as $key)if($gain($key))$kind[]=$key;
   $events[]=($kind?implode(' ',$kind).' ':'').($gain('G')>1?$gain('G').' Goals':'Goal');
  }else{
   foreach(['GWG'=>'GWG Goal','PPG'=>'Power-play Goal','SHG'=>'Short-handed Goal'] as $key=>$label)if($gain($key))$events[]=$label;
  }
  if($gain('A'))$events[]=$gain('A')>1?$gain('A').' Assists':'Assist';
  $name=\App\Support\PlayerName::display($player['player_name']);
  $nhl=trim((string)($player['nhl_team']??''));
  $points=(float)$player['daily_fpts'];
  $formatted=rtrim(rtrim(number_format($points,2,'.',''),'0'),'.');
  return ['title'=>$snapshot['teams'][$player['fantasy_team_id']]['name'].' - '.$formatted.' Fpts',
   'body'=>($events?implode(' and ',$events):'Score updated').' by '.$name.($nhl!==''?' ('.$nhl.')':''),
   'url'=>'/teams/current?date='.$snapshot['fantasy_date'],'fantasy_team_id'=>$player['fantasy_team_id']];
 }
}
