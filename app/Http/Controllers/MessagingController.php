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
  if($id)abort_unless(Messaging::participants()->whereKey($id)->exists(),422,'This account is not linked to a team.');return $id;
 }
 public function index(Request $r) {
  $other=$this->other($r);
  return response()->view('communication.messages',['other'=>$other,'people'=>Messaging::participants()->whereNull('messaging_persona')->where('id','!=',$r->user()->id)->orderBy('name')->get()])->header('Cache-Control','private, no-store');
 }
 public function conversation(Request $r) {
  $other=$this->other($r);$r->validate(['before'=>'nullable|integer|min:1','after'=>'nullable|integer|min:0','receipts'=>'nullable|array|max:250','receipts.*'=>'integer|min:1']);
  $q=Messaging::visible($r->user()->id,$other);
  if($r->filled('before'))$q->where('m.id','<',(int)$r->input('before'));
  if($r->filled('after'))$q->where('m.id','>',(int)$r->input('after'));
  // Initial/older pages are returned chronologically; incremental pages take the earliest new entries.
  $ascending=$r->filled('after');$q->orderBy('m.id',$ascending?'asc':'desc')->limit(50);
  $rows=Messaging::rows($q);if(!$ascending)$rows=array_reverse($rows);
   $receipts=$r->input('receipts',[])?Messaging::rows(Messaging::visible($r->user()->id,$other)->whereIn('m.id',$r->input('receipts'))):[];
  $receipts=array_map(fn($message)=>array_intersect_key($message,array_flip(['id','viewers','read','read_at','likes','liked','attachment_url'])),$receipts);
  return response()->json(['messages'=>$rows,'receipts'=>$receipts,'has_more'=>count($rows)===50])->header('Cache-Control','private, no-store');
 }
 public function send(Request $r,WebPush $push) {
  $other=$this->other($r);if($other)abort_unless(Messaging::participants()->whereNull('messaging_persona')->whereKey($other)->exists(),422,'Choose a league owner.');$v=$r->validate(['body'=>'nullable|string|max:4000','client_id'=>'required|uuid','attachment'=>'nullable|string|max:2800000']);
  $body=trim($v['body']??'');$attachment=null;
  if(!empty($v['attachment'])){
   abort_unless(preg_match('~^data:(image/(?:png|jpeg|gif|webp));base64,(.+)$~s',$v['attachment'],$match),422,'Choose a PNG, JPEG, GIF or WebP image.');
   $bytes=base64_decode($match[2],true);abort_unless($bytes!==false&&strlen($bytes)<=2097152,422,'Images must be under 2 MB.');
   $info=@getimagesizefromstring($bytes);abort_unless($info&&$info['mime']===$match[1]&&$info[0]<=6000&&$info[1]<=6000,422,'Choose a valid image up to 6000 pixels.');
   $attachment=['mime'=>$info['mime'],'sha256'=>hash('sha256',$bytes),'data'=>base64_encode($bytes)];
  }
  abort_if($body===''&&!$attachment,422,'Write a message or choose an image.');
  $new=false;
  $id=DB::transaction(function()use($r,$other,$v,$body,$attachment,&$new){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   $existing=DB::table('chat_messages')->where('sender_id',$r->user()->id)->where('client_id',$v['client_id'])->first();
   if($existing){$hash=DB::table('chat_attachments')->where('message_id',$existing->id)->value('sha256');abort_if($hash!==($attachment['sha256']??null),409,'This send was already used for a different image.');abort_if(($existing->recipient_id===null?null:(int)$existing->recipient_id)!==$other || $existing->body!==$body,409,'This send was already used for a different message.');return $existing->id;}
   $new=true;$id=DB::table('chat_messages')->insertGetId(['sender_id'=>$r->user()->id,'recipient_id'=>$other,'client_id'=>$v['client_id'],'body'=>$body,'created_at'=>now(),'updated_at'=>now()]);
   if($attachment)DB::table('chat_attachments')->insert(array_merge($attachment,['message_id'=>$id]));return $id;
  });
  if($new){
   try{$push->notify($other?'private-message':'league-message',($r->user()->claim?->team_name??'League member').($other?' sent you a message':' · League chat'),mb_substr($body?:'Shared an image',0,240),$other?'/messages?user_id='.$r->user()->id:'/messages',null,['sender_id'=>$r->user()->id,'recipient_id'=>$other]);}
   catch(\Throwable $e){Log::warning('Message saved but push failed',['message_id'=>$id,'error'=>$e->getMessage()]);}
  }
  return response()->json(['message'=>Messaging::rows(DB::table('chat_messages as m')->where('m.id',$id))[0]],$new?201:200)->header('Cache-Control','private, no-store');
 }
 private function accessible(Request $r,int $id) {
  $message=DB::table('chat_messages')->where('id',$id)->first();abort_unless($message,404);
  abort_unless($message->recipient_id===null||(int)$message->sender_id===$r->user()->id||(int)$message->recipient_id===$r->user()->id,403);return $message;
 }
 public function react(Request $r,int $id) {
  $this->accessible($r,$id);$v=$r->validate(['active'=>'required|boolean']);
  if($v['active'])DB::table('chat_reactions')->insertOrIgnore(['message_id'=>$id,'user_id'=>$r->user()->id,'created_at'=>now(),'updated_at'=>now()]);
  else DB::table('chat_reactions')->where('message_id',$id)->where('user_id',$r->user()->id)->delete();
  return response()->json(['message'=>Messaging::rows(DB::table('chat_messages as m')->where('m.id',$id))[0]])->header('Cache-Control','private, no-store');
 }
 public function attachment(Request $r,int $id) {
  $this->accessible($r,$id);$image=DB::table('chat_attachments')->where('message_id',$id)->first();abort_unless($image,404);
  return response(base64_decode($image->data),200,['Content-Type'=>$image->mime,'Cache-Control'=>'private, max-age=3600','X-Content-Type-Options'=>'nosniff','Content-Disposition'=>'inline']);
 }
 public function read(Request $r) {
  $other=$this->other($r);$v=$r->validate(['last_id'=>'required|integer|min:1']);
  abort_unless(Messaging::visible($r->user()->id,$other)->where('m.id',$v['last_id'])->exists(),403);
  DB::transaction(function()use($r,$other,$v){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();$key=Messaging::conversation($other);
   $row=DB::table('chat_reads')->where('user_id',$r->user()->id)->where('conversation',$key)->first();
   // Only newly read incoming messages get a first-view timestamp. Historical cursors retain their known read status without inventing an old time.
   $messages=Messaging::visible($r->user()->id,$other)->where('m.sender_id','!=',$r->user()->id)->where('m.id','>',(int)($row->last_message_id??0))->where('m.id','<=',(int)$v['last_id'])->pluck('m.id');
   foreach($messages->chunk(500) as $chunk)DB::table('chat_message_views')->insertOrIgnore($chunk->map(fn($id)=>['message_id'=>$id,'user_id'=>$r->user()->id,'read_at'=>now()])->all());
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
  $teams=Messaging::participants()->whereNull('messaging_persona')->where('id','!=',$uid)->get()->map(fn($u)=>['user_id'=>$u->id,'team_name'=>$u->messaging_persona?$u->name:$u->claim->team_name,'team_logo'=>\App\Support\TeamImages::url($u->messaging_persona?($u->messaging_persona==='gary-betman'?'gary-bettman':'league-logo'):\Illuminate\Support\Str::slug($u->claim->team_name),64)])->sortBy('team_name')->values();
  $received=User::whereNotNull('messaging_persona')->whereIn('id',DB::table('chat_messages')->where('recipient_id',$uid)->select('sender_id'))->get()->map(fn($u)=>['user_id'=>$u->id,'team_name'=>$u->name,'read_only'=>true]);
  return response()->json(['received_conversations'=>$received,'teams'=>$teams,'unread'=>Messaging::unread($uid),'messages'=>$messages,'latest_id'=>$latest,'notification_count'=>(clone $inbox)->whereNull('i.read_at')->count(),'notifications'=>$notifications,'preferences'=>Messaging::preferences($r->user()),'owners'=>$owners])->header('Cache-Control','private, no-store');
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

