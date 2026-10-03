<?php
namespace App\Support;
use Illuminate\Support\Facades\DB;
class OwnerGoalies {
 public function available(string $date): \Illuminate\Support\Collection {
  return DB::table('active_available_goalies')->whereDate('game_date',$date)->get()->filter(function($g)use($date){
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
  $days=(new FantasyDay)->dates();$rows=collect();
  foreach(['today','tomorrow'] as $label)foreach($this->available($days[$label]) as $g){
   $key=OwnerNotificationPolicy::goalieKey($g->team,$g->player_name);
   if(!$rows->has($key))$rows->put($key,['key'=>$key,'name'=>$g->player_name,'team'=>$g->team,'days'=>[]]);
   $item=$rows->get($key);$item['days'][]=ucfirst($label);$rows->put($key,$item);
  }
  return $rows->sortBy('name')->values();
 }
}
