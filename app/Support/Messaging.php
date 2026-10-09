<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Messaging
{
 public static function gary(): User {
  DB::table('users')->insertOrIgnore(['messaging_persona'=>'gary-betman','name'=>'Gary Bettman','email'=>'gary-betman@personas.ecfhl.invalid','password'=>null,'google_id'=>null,'is_admin'=>false,'created_at'=>now(),'updated_at'=>now()]);
  return User::where('messaging_persona','gary-betman')->lockForUpdate()->firstOrFail();
 }
 public static function welcomeTeam(string $teamName): void {
  $gary=self::gary();
  $body='Welcome to the ECFHL, '.$teamName.'! Your team is now claimed. Good luck this season! — Gary Bettman';
  $id=DB::table('chat_messages')->insertGetId(['sender_id'=>$gary->id,'recipient_id'=>null,'client_id'=>\Illuminate\Support\Str::uuid()->toString(),'body'=>$body,'created_at'=>now(),'updated_at'=>now()]);
  // Publish notifications only once the account, claim and message have committed.
  DB::afterCommit(function()use($gary,$body,$id){
   try{app(WebPush::class)->notify('league-message','Gary Bettman · League chat',$body,'/messages',null,['sender_id'=>$gary->id,'recipient_id'=>null]);}
   catch(\Throwable $error){\Illuminate\Support\Facades\Log::warning('Team welcome saved but push failed',['message_id'=>$id,'error'=>$error->getMessage()]);}
  });
 }
 public static function participants() {
  return User::with('claim')->where(fn($q)=>$q->whereHas('claim',fn($q)=>$q->whereNotNull('fantasy_team_id')->where('fantasy_team_id','!=',''))->orWhereNotNull('messaging_persona'));
 }
 public static function conversation(?int $other): string {return $other ? 'user:'.$other : 'league';}
 public static function visible(int $userId,?int $other) {
  $q=DB::table('chat_messages as m');
  if(!$other)return $q->whereNull('m.recipient_id');
  return $q->where(function($q)use($userId,$other){
   $q->where(fn($q)=>$q->where('m.sender_id',$userId)->where('m.recipient_id',$other))
     ->orWhere(fn($q)=>$q->where('m.sender_id',$other)->where('m.recipient_id',$userId));
  });
 }
 public static function incoming(int $userId) {
  return DB::table('chat_messages as m')->where('m.sender_id','!=',$userId)
   ->where(fn($q)=>$q->where('m.recipient_id',$userId)->orWhereNull('m.recipient_id'));
 }
 public static function unread(int $userId): array {
  $rows=self::incoming($userId)->whereNotExists(function($q)use($userId){
   $q->selectRaw('1')->from('chat_reads as r')->where('r.user_id',$userId)->whereColumn('r.last_message_id','>=','m.id')
     ->where(function($q){$q->where(fn($q)=>$q->whereNull('m.recipient_id')->where('r.conversation','league'))
       ->orWhere(fn($q)=>$q->whereNotNull('m.recipient_id')->whereRaw("r.conversation = ".(DB::getDriverName()==='sqlite'?"'user:' || m.sender_id":"CONCAT('user:', m.sender_id)")));});
  });
  $league=(clone $rows)->whereNull('m.recipient_id')->count();
  $private=(clone $rows)->whereNotNull('m.recipient_id')->count();
  $people=(clone $rows)->whereNotNull('m.recipient_id')->select('m.sender_id')->selectRaw('COUNT(*) as unread_count')->groupBy('m.sender_id')->pluck('unread_count','sender_id')->map(fn($count)=>(int)$count)->all();
  return ['total'=>$league+$private,'league'=>$league,'private'=>$private,'people'=>(object)$people];
 }
 public static function rows($query): array {
  $rows=$query->join('users as u','u.id','=','m.sender_id')->leftJoin('owner_team_claims as c','c.user_id','=','u.id')
   ->get(['m.id','m.sender_id','m.recipient_id','m.body','m.created_at','c.team_name','u.messaging_persona','u.name as persona_name'])->map(function($m){$row=(array)$m;if($m->messaging_persona)$row['team_name']=$m->persona_name;unset($row['messaging_persona'],$row['persona_name']);$row['read_only_sender']=$m->messaging_persona!==null;$row['sender_name']=$row['team_name'] ?: 'League member';$row['team_logo']=$m->messaging_persona==='gary-betman' ? TeamImages::url('gary-bettman',64) : ($m->team_name ? TeamImages::url(\Illuminate\Support\Str::slug($m->team_name),64) : TeamImages::url('league-logo',64));$row['created_at']=\Carbon\CarbonImmutable::parse($m->created_at,config('app.timezone'))->toIso8601String();return $row;})->all();
  return self::receipts($rows);
 }
 public static function receipts(array $messages): array {
  if(!$messages)return [];
  $ids=array_column($messages,'id');
  $likes=DB::table('chat_reactions')->whereIn('message_id',$ids)->get(['message_id','user_id'])->groupBy('message_id');
  $attachments=DB::table('chat_attachments')->whereIn('message_id',$ids)->pluck('message_id')->all();
  $views=DB::table('chat_message_views as v')->join('users as u','u.id','=','v.user_id')->leftJoin('owner_team_claims as c','c.user_id','=','v.user_id')
   ->whereIn('v.message_id',$ids)->get(['v.message_id','v.user_id','v.read_at','c.team_name'])->groupBy('message_id');
  $senders=array_unique(array_column($messages,'sender_id'));
  $keys=array_merge(['league'],array_map(fn($id)=>self::conversation((int)$id),$senders));
  $cursors=DB::table('chat_reads as r')->join('users as u','u.id','=','r.user_id')->leftJoin('owner_team_claims as c','c.user_id','=','r.user_id')
   ->whereIn('r.conversation',$keys)->get(['r.user_id','r.conversation','r.last_message_id','c.team_name']);
  foreach($messages as &$message){
   $reactions=$likes[$message['id']]??collect();$message['likes']=$reactions->count();$message['liked']=$reactions->contains(fn($r)=>(int)$r->user_id===(int)auth()->id());$message['attachment_url']=in_array($message['id'],$attachments)?'/api/messages/'.$message['id'].'/attachment':null;
   $people=[];
   foreach($cursors as $reader){
    if((int)$reader->user_id===(int)$message['sender_id'] || (int)$reader->last_message_id<(int)$message['id'])continue;
    $private=$message['recipient_id']!==null;
    if($reader->conversation!==($private?self::conversation((int)$message['sender_id']):'league'))continue;
    if($private&&(int)$reader->user_id!==(int)$message['recipient_id'])continue;
    $people[$reader->user_id]=['team_name'=>$reader->team_name?:'League member','read_at'=>null];
   }
   foreach($views[$message['id']]??[] as $reader){
    if((int)$reader->user_id===(int)$message['sender_id'])continue;
    if($message['recipient_id']!==null&&(int)$reader->user_id!==(int)$message['recipient_id'])continue;
    $people[$reader->user_id]=['team_name'=>$reader->team_name?:'League member','read_at'=>\Carbon\CarbonImmutable::parse($reader->read_at,config('app.timezone'))->toIso8601String()];
   }
   $message['viewers']=array_values($people);$message['read']=count($people)>0;
   $message['read_at']=$message['recipient_id']!==null?($people[$message['recipient_id']]['read_at']??null):null;
  }
  unset($message);return $messages;
 }
 public static function preferences(User $user): array {return array_replace(OwnerNotificationPolicy::DEFAULTS,$user->notification_preferences??[]);}
}
