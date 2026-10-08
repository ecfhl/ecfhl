<?php
namespace App\Http\Controllers;
use App\Support\OwnerGoalies;
use App\Support\OwnerNotificationPolicy;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class OwnerNotificationsController {
 public function index(Request $r,OwnerGoalies $goalies){
  return view('account.notifications',['owner'=>$r->user(),'preferences'=>array_replace(OwnerNotificationPolicy::DEFAULTS,$r->user()?->notification_preferences??[]),'goalies'=>$goalies->options()]);
 }
 public function watch(Request $r,OwnerGoalies $goalies){
  $v=$r->validate(['key'=>'required|string|max:255','enabled'=>'required|boolean','player_id'=>'nullable|string|max:100']);
  $key=$v['key'];$enabled=$r->boolean('enabled');
  $allowed=$goalies->options()->pluck('key')->all();
  // Players-page watches persist across games, including rostered goalies.
  if(!empty($v['player_id'])){
   $player=\Illuminate\Support\Facades\DB::table('season_player_stats')->where('player_id',$v['player_id'])->where('position','G')->first();
   abort_unless($player && OwnerNotificationPolicy::goalieKey((string)$player->nhl_team,$player->player_name)===$key,422,'This player is not a matching goalie.');
   $allowed[]=$key;
  }
  $saved=\Illuminate\Support\Facades\DB::transaction(function()use($r,$allowed,$key,$enabled){
   $owner=\App\Models\User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $p=array_replace(OwnerNotificationPolicy::DEFAULTS,$owner->notification_preferences??[]);
   abort_unless(in_array($key,$allowed,true)||(!$enabled && in_array($key,$p['goalies'],true)),422,'This goalie is no longer awaiting a starting decision for an upcoming game. Refresh the page.');
   $p['goalies']=array_values(array_diff($p['goalies'],[$key]));
   if($enabled){abort_if(count($p['goalies'])>=100,422,'You can watch up to 100 goalies.');$p['goalies'][]=$key;}
   $owner->notification_preferences=$p;$owner->save();return $p['goalies'];
  });
  return response()->json(['key'=>$key,'enabled'=>in_array($key,$saved,true)]);
 }
 public function save(Request $r,OwnerGoalies $goalies){
  $allowed=$goalies->options()->pluck('key')->merge($r->user()->notification_preferences['goalies']??[])->unique()->all();
  $v=$r->validate(['goalies'=>'nullable|array|max:100','goalies.*'=>['string',Rule::in($allowed)]]);
  $p=[];foreach(array_keys(OwnerNotificationPolicy::DEFAULTS) as $key)if($key!=='goalies')$p[$key]=in_array($key,['notifications_enabled','private_message_popups','league_message_popups','private_message_push','league_message_push'],true)&&!$r->has($key) ? (array_replace(OwnerNotificationPolicy::DEFAULTS,$r->user()->notification_preferences??[])[$key]) : $r->boolean($key);
  $p['goalies']=array_values(array_unique($v['goalies']??[]));
  \Illuminate\Support\Facades\DB::transaction(function()use($r,$p){
   $r->user()->notification_preferences=$p;$r->user()->save();
   // Clear queued events together with the preference update.
   \Illuminate\Support\Facades\DB::table('push_deliveries')->whereIn('subscription_id',\Illuminate\Support\Facades\DB::table('push_subscriptions')->where('user_id',$r->user()->id)->select('id'))->delete();
  });
  $claim=$r->user()->claim;
  $redirect=$claim?'/teams/current/'.\Illuminate\Support\Str::slug($claim->team_name):'/teams/current';
  if($r->expectsJson())return response()->json(['message'=>'Notification preferences saved.','preferences'=>$p,'redirect_url'=>$redirect]);
  return redirect($redirect)->with('notice','Notification preferences saved.');
 }
}
