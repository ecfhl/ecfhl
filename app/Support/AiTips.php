<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AiTips
{
    public static function groups(array $snapshot, string $date): array
    {
        $groups=['G'=>[],'F'=>[],'D'=>[]];
        $daily=DB::table('active_daily_players')->whereDate('game_date',$date)->orderBy('source_rank')->get();
        $goalieStatus=DB::table('active_starting_goalies')->whereDate('game_date',$date)->get()->keyBy(fn($r)=>strtoupper(trim($r->team)).'|'.mb_strtolower(trim($r->player_name)));
        $confirmedTeams=[];foreach($goalieStatus as $row)if(strtolower(trim((string)$row->starting_status))==='confirmed')$confirmedTeams[strtoupper(trim($row->team))]=mb_strtolower(trim($row->player_name));
        foreach($daily as $row){
            $position=strtoupper(trim((string)$row->position));if(!isset($groups[$position]))continue;
            $status=strtoupper(trim((string)$row->availability));if($status==='W')$status='W'.($row->waiver_day?' ('.$row->waiver_day.')':'');if(!preg_match('/^(FA|W(?:\s*\([^)]+\))?)$/',$status))continue;
            $opponent=trim((string)$row->opponent;if($opponent===''||!$row->team||!$row->player_name)continue;$opponent=strtoupper((string)$row->home_away)==='AWAY'?'@'.$opponent:$opponent;
            $player=['name'=>$row->player_name,'team'=>$row->team,'position'=>$position,'opponent'=>$opponent,'status'=>$status,'injury_status'=>$row->injury_status,'projected_points'=>$row->projected_fpts===null?null:(float)$row->projected_fpts,'source_rank'=>(int)$row->source_rank,'game_date'=>$date,'starting_status'=>null];
            if($position==='G'){$key=strtoupper(trim($row->team)).'|'.mb_strtolower(trim($row->player_name));if(isset($goalieStatus[$key]))$player['starting_status']=$goalieStatus[$key]->starting_status;$team=strtoupper(trim($row->team));if(isset($confirmedTeams[$team])&&$confirmedTeams[$team]!==mb_strtolower(trim($row->player_name)))continue;}
            $groups[$position][]=$player;
        }
        foreach(['F','D'] as $position)usort($groups[$position],fn($a,$b)=>(($b['projected_points']??0)<=>($a['projected_points']??0))?: (($a['source_rank']??PHP_INT_MAX)<=>($b['source_rank']??PHP_INT_MAX))?:strcasecmp($a['name'],$b['name']));
        $dailyFaceoff=array_values(array_filter($groups['G'],fn($p)=>!empty($p['starting_status'])));$fantrax=array_values(array_filter($groups['G'],fn($p)=>empty($p['starting_status'])));
        $priority=fn($p)=>match(strtolower(trim($p['starting_status']??''))){'confirmed'=>0,'probable'=>1,'unconfirmed'=>2,default=>3};
        usort($dailyFaceoff,fn($a,$b)=>($priority($a)<=>$priority($b))?:(($b['projected_points']??0)<=>($a['projected_points']??0))?:strcasecmp($a['name'],$b['name']));
        usort($fantrax,fn($a,$b)=>(($b['projected_points']??0)<=>($a['projected_points']??0))?:(($a['source_rank']??PHP_INT_MAX)<=>($b['source_rank']??PHP_INT_MAX))?:strcasecmp($a['name'],$b['name']));
        $goalies=[];$seen=[];foreach(array_merge($dailyFaceoff,$fantrax) as $p){$key=mb_strtolower(trim($p['name'])).'|'.strtolower(trim($p['team']));if(isset($seen[$key]))continue;$seen[$key]=true;$goalies[]=$p;}
        $groups['G']=$goalies;
        return $groups;
    }
}
