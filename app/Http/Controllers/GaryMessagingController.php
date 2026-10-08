<?php
namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Messaging;
use App\Support\WebPush;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class GaryMessagingController {
 public function index(Request $r) {
  abort_unless($r->user()->is_admin,403);
  return response()->view('admin.gary',['people'=>Messaging::participants()->get(),'owner'=>$r->user()])->header('Cache-Control','private, no-store');
 }
 public function send(Request $r,WebPush $push) {
  abort_unless($r->user()->is_admin,403);
  $v=$r->validate(['user_id'=>'nullable|integer|min:1|exists:users,id','body'=>'required|string|max:4000','client_id'=>'required|uuid']);
  // Missing recipient defaults to the requesting administrator; explicit null means League chat.
  $recipient=$r->input('user_id',$r->user()->id);$recipient=$recipient?(int)$recipient:null;
  if($recipient)abort_unless(Messaging::participants()->whereKey($recipient)->whereNull('messaging_persona')->exists() || $recipient===$r->user()->id,422,'Choose a league owner.');
  $body=trim($v['body']);abort_if($body==='',422,'Write a message first.');$new=false;
  [$id,$gary]=DB::transaction(function()use($r,$recipient,$body,$v,&$new){
   User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
   // This persona has no credentials, team claim or administrator privileges.
   DB::table('users')->insertOrIgnore(['messaging_persona'=>'gary-betman','name'=>'Gary Betman','email'=>'gary-betman@personas.ecfhl.invalid','password'=>null,'google_id'=>null,'is_admin'=>false,'created_at'=>now(),'updated_at'=>now()]);
   $gary=User::where('messaging_persona','gary-betman')->lockForUpdate()->firstOrFail();
   $existing=DB::table('chat_messages')->where('sender_id',$gary->id)->where('client_id',$v['client_id'])->first();
   if($existing){abort_if(($existing->recipient_id===null?null:(int)$existing->recipient_id)!==$recipient || $existing->body!==$body,409,'This send was already used for a different message.');return [$existing->id,$gary];}
   $new=true;$id=DB::table('chat_messages')->insertGetId(['sender_id'=>$gary->id,'recipient_id'=>$recipient,'client_id'=>$v['client_id'],'body'=>$body,'created_at'=>now(),'updated_at'=>now()]);return [$id,$gary];
  });
  if($new)try{$push->notify($recipient?'private-message':'league-message','Gary Betman'.($recipient?' sent you a message':' · League chat'),mb_substr($body,0,240),$recipient?'/messages?user_id='.$gary->id:'/messages',null,['sender_id'=>$gary->id,'recipient_id'=>$recipient]);}
  catch(\Throwable $error){Log::warning('Gary message saved but push failed',['message_id'=>$id,'error'=>$error->getMessage()]);}
  if($r->expectsJson())return response()->json(['message'=>Messaging::rows(DB::table('chat_messages as m')->where('m.id',$id))[0]],$new?201:200);
  return redirect('/admin/gary')->with('notice','Message sent from Gary Betman.');
 }
}
