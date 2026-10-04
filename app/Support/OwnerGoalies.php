<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
use Carbon\CarbonImmutable;
use App\Support\LiveScoring\SnapshotRepository;
class OwnerGoalies {
 public function available(string $date): \Illuminate\Support\Collection {
  return DB::table('active_available_goalies')->where('game_date',$date)->get()->filter(function($g)use($date){
   $status=strtoupper(trim((string)$g->availability));
   if(!in_array($status,['FA','W','WAIVERS','FREE AGENT'],true))return false;
   if($status!=='FA' && !empty($g->waiver_day)){
    $weekdays=['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
    $waiver=strtolower(substr(trim((string)$g->waiver_day),0,3));
    if(isset($weekdays[$waiver])){
     $today=(new FantasyDay)->today();$clear=$today->addDays(($weekdays[$waiver]-(int)$today->format('w')+7)%7)->toDateString();
     if($date<$clear)return false;
    }
   }
   return trim((string)$g->opponent)!=='';
  });
 }
 public function options(): \Illuminate\Support\Collection {
  $days=(new FantasyDay)->dates();$rows=collect();$now=CarbonImmutable::now();$projections=new PlayerProjections;
  foreach(['today','tomorrow'] as $label){
   $date=$days[$label];$available=$this->available($date);$statuses=[];$confirmedTeams=[];
   foreach(DB::table('active_starting_goalies')->where('game_date',$date)->get() as $g){
    $team=strtoupper(trim($g->team));$key=OwnerNotificationPolicy::goalieKey($team,$g->player_name);
    $statuses[$key]=strtolower(trim((string)$g->starting_status));
    if($statuses[$key]==='confirmed')$confirmedTeams[$team]=true;
   }
   foreach($available as $g){
    if(strtolower(trim((string)$g->starting_status))==='confirmed')$confirmedTeams[strtoupper(trim($g->team))]=true;
   }
   $games=$this->games($date);
   $dayRows=collect();
   foreach($available as $g){
    $team=strtoupper(trim($g->team));$key=OwnerNotificationPolicy::goalieKey($team,$g->player_name);
    $status=$statuses[$key]??strtolower(trim((string)$g->starting_status));
    // A confirmed teammate makes every other goalie on that team a nonstarter.
    if(isset($confirmedTeams[$team]) || in_array($status,['confirmed','not starting'],true))continue;
    $game=$games[$team]??[];$start=$game['start']??null;
    if(($game['started']??false) || ($start && $start->lessThanOrEqualTo($now)))continue;
    $dayRows->push(['key'=>$key,'name'=>$g->player_name,'team'=>$team,'day'=>ucfirst($label),'projected_points'=>$projections->rate($g),
     'start'=>$start?->toIso8601String(),'start_time'=>$start?->setTimezone('America/Halifax')->format('g:i a T')??'Time TBD']);
   }
   $rows=$rows->concat($dayRows->sort(fn($a,$b)=>(($b['projected_points']??-INF)<=>($a['projected_points']??-INF))?: (($a['start']?strtotime($a['start']):PHP_INT_MAX)<=>($b['start']?strtotime($b['start']):PHP_INT_MAX))?:strcasecmp($a['name'],$b['name'])));
  }
  return $rows->values();
 }

 private function games(string $date): array {
  $games=[];
  // Dated player-pool times are already converted to Atlantic time by the collector.
  foreach(['active_daily_players'=>'team','active_fantasy_rosters'=>'nhl_team'] as $table=>$column){
   foreach(DB::table($table)->where('game_date',$date)->get() as $p){
    $team=strtoupper(trim((string)($p->$column??'')));if($team==='')continue;
    $games[$team]['started']=($games[$team]['started']??false)||(bool)($p->game_started??false);
    if(!isset($games[$team]['start']))$games[$team]['start']=$this->parseTime($date,(string)($p->game_time??''));
   }
  }
  // Snapshots provide absolute start instants and live/final states for both sides.
  foreach((app(SnapshotRepository::class)->get($date)['players']??[]) as $p){
   foreach([$p['nhl_team']??'',ltrim((string)($p['opponent']??''),'@')] as $team){
    $team=strtoupper(trim($team));if($team==='')continue;
    $games[$team]['started']=($games[$team]['started']??false)||in_array((string)($p['game_status']??''),['2','3'],true);
    if(!empty($p['starts_at']))$games[$team]['start']=CarbonImmutable::parse($p['starts_at']);
   }
  }
  return $games;
 }

 private function parseTime(string $date,string $time): ?CarbonImmutable {
  if(!preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM))/i',$time,$match))return null;
  try{
   $start=CarbonImmutable::createFromFormat('!Y-m-d g:iA',$date.' '.strtoupper(preg_replace('/\s+/','',$match[1])),'America/Halifax');
   // A late Pacific game may start after midnight Atlantic on the next day.
   if(preg_match('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\b/i',$time,$weekday) && strcasecmp($start->format('D'),$weekday[1])!==0 && strcasecmp($start->addDay()->format('D'),$weekday[1])===0)$start=$start->addDay();
   return $start;
  }catch(\Throwable){return null;}
 }
}
