<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Messaging;
use App\Support\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class MessagingController
{
 private function other(Request $r): ?int {
  $v=$r->validate(['user_id'=>'nullable|integer|min:1|exists:users,id']);
  $id=isset($v['user_id'])?(int)$v['user_id']:null;
  abort_if($id===$r->user()->id,422,'Choose another team.');
  if($id)abort_unless(User::whereKey($id)->whereHas('claim',fn($q)=>$q->whereNotNull('fantasy_team_id')->where('fantasy_team_id','!=',''))->exists(),422,'This account is not linked to a team.');return $id;
 }
 public function index(Request $r) {
  $other=$this->other($r);
  return response()->view('communication.messages',['other'=>$other,'people'=>User::with('claim')->whereHas('claim',fn($q)=>$q->whereNotNull('fantasy_team_id')->where('fantasy_team_id','!=',''))->where('id','!=',$r->user()->id)->orderBy('name')->get()])->header('Cache-Control','private, no-store');
 }
 public function conversation(Request $r) {
  $other=$this->other($r);$r->validate(['before'=>'nullable|integer|min:1','after'=>'nullable|integer|min:0']);
  $q=Messaging::visible($r->user()->id,$other);
  if($r->filled('before'))$q->where('m.id','<',(int)$r->input('before'));
  if($r->filled('after'))$q->where('m.id','>',(int)$r->input('after'));
  // Initial/older pages are returned chronologically; incremental pages take the earliest new entries.
  $ascending=$r->filled('after');$q->orderBy('m.id',$ascending?'asc':'desc')->limit(50);
  $rows=Messaging::rows($q);if(!$ascending)$rows=array_reverse($rows);
  return response()->json(['messages'=>$rows,'has_more'=>count($rows)===50])->header('Cache-Control','private, no-store');
 }
 public function send(Request $r,WebPush $push) {
  $other=$this->other($r);$v=$r->validate(['body'=>'required|string|max:4000','client_id'=>'required|uuid']);
  $body=trim($v['body']);abort_if($body==='',422,'Write a message first.');
  $new=false;
  $id=DB::transaction(function()use($r,$other,$v,$body,&$new){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $existing=DB::table('chat_messages')->where('sender_id',$r->user()->id)->where('client_id',$v['client_id'])->first();
   if($existing){abort_if(($existing->recipient_id===null?null:(int)$existing->recipient_id)!==$other || $existing->body!==$body,409,'This send was already used for a different message.');return $existing->id;}
   $new=true;return DB::table('chat_messages')->insertGetId(['sender_id'=>$r->user()->id,'recipient_id'=>$other,'client_id'=>$v['client_id'],'body'=>$body,'created_at'=>now(),'updated_at'=>now()]);
  });
  if($new){
   try{$push->notify($other?'private-message':'league-message',($r->user()->claim?->team_name??'League member').($other?' sent you a message':' · League chat'),mb_substr($body,0,240),$other?'/messages?user_id='.$r->user()->id:'/messages',null,['sender_id'=>$r->user()->id,'recipient_id'=>$other]);}
   catch(\Throwable $e){Log::warning('Message saved but push failed',['message_id'=>$id,'error'=>$e->getMessage()]);}
  }
  return response()->json(['message'=>Messaging::rows(DB::table('chat_messages as m')->where('m.id',$id))[0]],$new?201:200)->header('Cache-Control','private, no-store');
 }
 public function read(Request $r) {
  $other=$this->other($r);$v=$r->validate(['last_id'=>'required|integer|min:1']);
  abort_unless(Messaging::visible($r->user()->id,$other)->where('m.id',$v['last_id'])->exists(),403);
  DB::transaction(function()use($r,$other,$v){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();$key=Messaging::conversation($other);
   $row=DB::table('chat_reads')->where('user_id',$r->user()->id)->where('conversation',$key)->first();
   DB::table('chat_reads')->updateOrInsert(['user_id'=>$r->user()->id,'conversation'=>$key],['last_message_id'=>max((int)($row->last_message_id??0),(int)$v['last_id']),'created_at'=>$row->created_at??now(),'updated_at'=>now()]);
  });
  return response()->json(['unread'=>Messaging::unread($r->user()->id)])->header('Cache-Control','private, no-store');
 }
 public function state(Request $r) {
  $r->validate(['after'=>'nullable|integer|min:0']);$uid=$r->user()->id;
  $incoming=Messaging::incoming($uid);$latest=(int)(clone $incoming)->max('m.id');
  $messages=$r->filled('after')?Messaging::rows($incoming->where('m.id','>',(int)$r->input('after'))->orderBy('m.id')->limit(100)):[];
  $inbox=DB::table('owner_notification_inbox as i')->join('push_notifications as n','n.id','=','i.notification_id')->where('i.user_id',$uid);
  $notifications=(clone $inbox)->orderByRaw('CASE WHEN i.read_at IS NULL THEN 0 ELSE 1 END')->orderByDesc('n.id')->limit(50)->get(['n.id','n.title','n.body','n.url','i.read_at']);
  $owners=DB::table('owner_team_claims')->whereNotNull('fantasy_team_id')->where('fantasy_team_id','!=','')->where('user_id','!=',$uid)->get(['user_id','team_name'])->mapWithKeys(fn($c)=>[\Illuminate\Support\Str::slug($c->team_name)=>$c->user_id]);
  $teams=User::with('claim')->whereHas('claim',fn($q)=>$q->whereNotNull('fantasy_team_id')->where('fantasy_team_id','!=',''))->where('id','!=',$uid)->get()->map(fn($u)=>['user_id'=>$u->id,'team_name'=>$u->claim->team_name,'team_logo'=>\App\Support\TeamImages::url(\Illuminate\Support\Str::slug($u->claim->team_name),64)])->sortBy('team_name')->values();
  return response()->json(['teams'=>$teams,'unread'=>Messaging::unread($uid),'messages'=>$messages,'latest_id'=>$latest,'notification_count'=>(clone $inbox)->whereNull('i.read_at')->count(),'notifications'=>$notifications,'preferences'=>Messaging::preferences($r->user()),'owners'=>$owners])->header('Cache-Control','private, no-store');
 }
 public function readNotifications(Request $r) {
  $v=$r->validate(['ids'=>'required|array|max:50','ids.*'=>'integer|min:1']);
  DB::table('owner_notification_inbox')->where('user_id',$r->user()->id)->whereIn('notification_id',$v['ids'])->whereNull('read_at')->update(['read_at'=>now(),'updated_at'=>now()]);
  return response()->json(['ok'=>true]);
 }
 public function preferences(Request $r) {
  $keys=['notifications_enabled','private_message_popups','league_message_popups','private_message_push','league_message_push'];
  $rules=array_fill_keys($keys,'sometimes|required|boolean');$v=$r->validate($rules);
  $p=DB::transaction(function()use($r,$v){$user=User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();$p=array_replace(Messaging::preferences($user),$v);$user->notification_preferences=$p;$user->save();
   $queued=DB::table('push_deliveries')->whereIn('subscription_id',DB::table('push_subscriptions')->where('user_id',$user->id)->select('id'));
   if(!$p['notifications_enabled'])$queued->delete();
   else {
    $muted=[];if(!$p['private_message_push'])$muted[]='private-message';if(!$p['league_message_push'])$muted[]='league-message';
    if($muted)$queued->whereIn('notification_id',DB::table('push_notifications')->whereIn('category',$muted)->select('id'))->delete();
   }
   return $p;});
  return response()->json(['preferences'=>$p])->header('Cache-Control','private, no-store');
 }
}
