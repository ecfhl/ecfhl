<?php
namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class Messaging
{
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
  return $query->join('users as u','u.id','=','m.sender_id')->leftJoin('owner_team_claims as c','c.user_id','=','u.id')
   ->get(['m.id','m.sender_id','m.recipient_id','m.body','m.created_at','c.team_name'])->map(function($m){$row=(array)$m;$row['sender_name']=$m->team_name ?: 'League member';$row['team_logo']=$m->team_name ? TeamImages::url(\Illuminate\Support\Str::slug($m->team_name),64) : TeamImages::url('league-logo',64);$row['created_at']=\Carbon\CarbonImmutable::parse($m->created_at,config('app.timezone'))->toIso8601String();return $row;})->all();
 }
 public static function preferences(User $user): array {return array_replace(OwnerNotificationPolicy::DEFAULTS,$user->notification_preferences??[]);}
}
