<?php
namespace App\Support;
use App\Models\User;
class OwnerNotificationPolicy {
 public const DEFAULTS=['team_scores'=>true,'opponent_scores'=>false,'all_goalies'=>false,'available_today'=>false,'available_tomorrow'=>false,'goalies'=>[]];
 public static function goalieKey(string $team,string $name): string {
  return strtoupper(trim($team)).'|'.(preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'');
 }
 public function accepts(User $user,string $category,?string $teamId,array $context): bool {
  $p=array_replace(self::DEFAULTS,$user->notification_preferences??[]);
  if($category==='live-score'){
   $own=$user->claim?->fantasy_team_id;if(!$own)return false;
   return ($own===$teamId && $p['team_scores']) || ($own===($context['opponent_team_id']??null) && $p['opponent_scores']);
  }
  if($category!=='goalie-status')return false;
  $day=$context['game_date']??null;$days=(new FantasyDay)->dates();
  if(!in_array($day,[$days['today'],$days['tomorrow']],true))return false;
  if($p['all_goalies'])return true;
  if(empty($context['available']))return false;
  $enabled=($day===$days['today'] && $p['available_today']) || ($day===$days['tomorrow'] && $p['available_tomorrow']);
  $watched=in_array($context['goalie_key']??'', $p['goalies'],true);
  return (bool)($enabled||$watched);
 }
}
