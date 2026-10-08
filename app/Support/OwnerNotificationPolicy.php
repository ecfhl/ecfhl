<?php
namespace App\Support;
use App\Models\User;
class OwnerNotificationPolicy {
 public const DEFAULTS=['notifications_enabled'=>true,'private_message_popups'=>true,'league_message_popups'=>true,'private_message_push'=>true,'league_message_push'=>true,'team_scores'=>true,'opponent_scores'=>false,'all_goalies'=>false,'own_goalies'=>false,'available_today'=>false,'available_tomorrow'=>false,'goalies'=>[]];
 private array $rosterGoalies=[];
 public static function goalieKey(string $team,string $name): string {
  return strtoupper(trim($team)).'|'.(preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'');
 }
 public function accepts(User $user,string $category,?string $teamId,array $context): bool {
  $p=array_replace(self::DEFAULTS,$user->notification_preferences??[]);
  if(in_array($category,['private-message','league-message'],true)){
   if($user->id===(int)($context['sender_id']??0))return false;
   return $category==='private-message' ? $user->id===(int)($context['recipient_id']??0) && $p['private_message_push'] : (bool)$p['league_message_push'];
  }
  if($category==='live-score'){
   $own=$user->claim?->fantasy_team_id;if(!$own)return false;
   return ($own===$teamId && $p['team_scores']) || ($own===($context['opponent_team_id']??null) && $p['opponent_scores']);
  }
  if($category!=='goalie-status')return false;
  $day=$context['game_date']??null;$days=(new FantasyDay)->dates();
  if(!in_array($day,[$days['today'],$days['tomorrow']],true))return false;
  if($p['all_goalies'])return true;
  if($p['own_goalies'] && $this->ownsGoalie($user,$day,(string)($context['goalie_key']??'')))return true;
  if(in_array($context['goalie_key']??'', $p['goalies'],true))return true;
  if(empty($context['available']))return false;
  $enabled=($day===$days['today'] && $p['available_today']) || ($day===$days['tomorrow'] && $p['available_tomorrow']);
  $watched=in_array($context['goalie_key']??'', $p['goalies'],true);
  return (bool)($enabled||$watched);
 }
 private function ownsGoalie(User $user,string $date,string $key): bool {
  $teamId=$user->claim?->fantasy_team_id;if(!$teamId || $key==='')return false;
  if(!array_key_exists($date,$this->rosterGoalies)){
   $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date);
   $players=$snapshot!==null ? ($snapshot['players']??[]) : \Illuminate\Support\Facades\DB::table('active_fantasy_rosters')->where('game_date',$date)->get()->map(fn($p)=>(array)$p)->all();
   $this->rosterGoalies[$date]=[];
   foreach($players as $p){
    $position=$p['position']??'';if(is_array($position))$position=implode(',',$position);
    if(!preg_match('/(^|[,\/ ])G($|[,\/ ])/i',(string)$position))continue;
    $goalieKey=self::goalieKey((string)($p['nhl_team']??''),(string)($p['player_name']??''));
    $this->rosterGoalies[$date][(string)$p['fantasy_team_id']][$this->canonicalKey($goalieKey)]=true;
   }
  }
  return isset($this->rosterGoalies[$date][$teamId][$this->canonicalKey($key)]);
 }
 private function canonicalKey(string $key): string {
  [$team,$name]=array_pad(explode('|',$key,2),2,'');
  $team=match($team){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$team};
  return $team.'|'.$name;
 }

}
