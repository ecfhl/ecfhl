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
 public function save(Request $r,OwnerGoalies $goalies){
  $allowed=$goalies->options()->pluck('key')->merge($r->user()->notification_preferences['goalies']??[])->unique()->all();
  $v=$r->validate(['goalies'=>'nullable|array|max:100','goalies.*'=>['string',Rule::in($allowed)]]);
  $p=[];foreach(array_keys(OwnerNotificationPolicy::DEFAULTS) as $key)if($key!=='goalies')$p[$key]=$r->boolean($key);
  $p['goalies']=array_values(array_unique($v['goalies']??[]));$r->user()->notification_preferences=$p;$r->user()->save();
  // Clear queued events when the owner changes their filters.
  \Illuminate\Support\Facades\DB::table('push_deliveries')->whereIn('subscription_id',\Illuminate\Support\Facades\DB::table('push_subscriptions')->where('user_id',$r->user()->id)->select('id'))->delete();
  return back()->with('notice','Notification preferences saved.');
 }
}
