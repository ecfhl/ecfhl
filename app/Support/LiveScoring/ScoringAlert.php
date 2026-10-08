<?php
namespace App\Support\LiveScoring;

final class ScoringAlert
{
    public static function state(array $snapshot): array
    {
        $players=[];
        foreach ($snapshot['players']??[] as $player) {
            if (($player['scoring_status']??'')!=='ACTIVE') continue;
            $position=$player['position']??'';
            if (is_array($position)) $position=implode(',',$position);
            $players[]=[
                'teamId'=>(string)$player['fantasy_team_id'],
                'key'=>$player['fantasy_team_id'].'|'.$player['player_id'],
                'team'=>$snapshot['teams'][$player['fantasy_team_id']]['name'],
                'name'=>\App\Support\PlayerName::display($player['player_name']),
                'nhl'=>$player['nhl_team']??'', 'goalie'=>(bool)preg_match('/(^|[,\/ ])G($|[,\/ ])/i',(string)$position),
                'change'=>$player['fpts_change']??'same',
                'points'=>(float)($player['daily_fpts']??0), 'stats'=>self::totals($player),
            ];
        }
        return ['date'=>$snapshot['fantasy_date'],'players'=>$players];
    }

    private static function totals(array $player): array
    {
        $stats=$player['stats']??[];
        $values=[];
        foreach(['G'=>['G'],'A'=>['A'],'PPG'=>['PPG'],'SHG'=>['SHG'],'GWG'=>['GWG'],
            'W'=>['W'],'L'=>['L'],'OTL'=>['OTL','OL+ShL','OL'],'SO'=>['SO','SHO']] as $key=>$aliases){
            $values[$key]=0;
            foreach($aliases as $alias){
                if(!array_key_exists($alias,$stats))continue;
                $stat=$stats[$alias];
                $values[$key]=(int)(is_array($stat)?($stat['value']??0):$stat);
                break;
            }
        }
        return $values;
    }

    public static function payload(array $snapshot,array $player,?array $previous=null): array
    {
        $totals=self::totals($player);
        $old=self::totals($previous??[]);
        $gain=static fn($key)=>max(0,$totals[$key]-$old[$key]);
        $position=$player['position']??$player['pos']??'';
        if(is_array($position))$position=implode(',',$position);
        $isGoalie=(bool)preg_match('/(^|[,\/ ])G($|[,\/ ])/i',(string)$position);
        $events=[];
        if($isGoalie){
            if($gain('W'))$events[]=$gain('W')===1?'records a win':'records '.$gain('W').' wins';
            if($gain('L'))$events[]=$gain('L')===1?'takes a loss':'takes '.$gain('L').' losses';
            if($gain('OTL'))$events[]=$gain('OTL')===1?'takes an overtime loss':'takes '.$gain('OTL').' overtime losses';
            if($gain('SO'))$events[]=$gain('SO')===1?'records a shutout':'records '.$gain('SO').' shutouts';
        }else{
            if($gain('G')>1){
                $event='scores '.$gain('G').' goals';
                $kinds=[];
                if($gain('GWG'))$kinds[]='the game winner';
                if($gain('PPG'))$kinds[]=$gain('PPG')===1?'a power-play goal':$gain('PPG').' power-play goals';
                if($gain('SHG'))$kinds[]=$gain('SHG')===1?'a short-handed goal':$gain('SHG').' short-handed goals';
                if($kinds)$event.=', including '.implode(' and ',$kinds);
                $events[]=$event;
            }elseif($gain('GWG')){
                $events[]='scores the game winner'.($gain('PPG')?' on the power play':($gain('SHG')?' while short-handed':''));
            }elseif($gain('PPG')){
                $events[]=$gain('PPG')===1?'scores a power-play goal':'scores '.$gain('PPG').' power-play goals';
            }elseif($gain('SHG')){
                $events[]=$gain('SHG')===1?'scores a short-handed goal':'scores '.$gain('SHG').' short-handed goals';
            }elseif($gain('G')){
                $events[]='scores a goal';
            }
            if($gain('A'))$events[]=$gain('A')===1?'gets an assist':'gets '.$gain('A').' assists';
        }
        $name=\App\Support\PlayerName::display($player['player_name']);
        $nhl=trim((string)($player['nhl_team']??''));
        $body=$name.($nhl!==''?' ('.$nhl.')':'').' '.($events?implode(' and ',$events):'has a score update').'.';
        $summary=[];
        foreach($isGoalie?['W','L','OTL','SO']:['G','A','PPG','SHG','GWG'] as $key){
            if($totals[$key]!==0)$summary[]=$key.': '.$totals[$key];
        }
        if($summary)$body.="\n".implode(' · ',$summary);
        $formatted=rtrim(rtrim(number_format((float)$player['daily_fpts'],2,'.',''),'0'),'.');
        return ['title'=>$snapshot['teams'][$player['fantasy_team_id']]['name'].' - '.$formatted.'pts',
            'body'=>$body,'url'=>'/teams/current?date='.$snapshot['fantasy_date'],
            'fantasy_team_id'=>$player['fantasy_team_id']];
    }
}
