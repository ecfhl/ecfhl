<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MessagingController as Chat;
Route::middleware('auth')->group(function(){
 Route::get('/admin/gary',[\App\Http\Controllers\GaryMessagingController::class,'index']);
 Route::post('/admin/gary/messages',[\App\Http\Controllers\GaryMessagingController::class,'send'])->middleware('throttle:30,1,gary-send');
 Route::get('/messages',[Chat::class,'index']);
 Route::get('/api/messages/state',[Chat::class,'state'])->middleware('throttle:120,1,chat-state');
 Route::get('/api/messages/conversation',[Chat::class,'conversation'])->middleware('throttle:120,1,chat-read');
 Route::post('/api/messages/send',[Chat::class,'send'])->middleware('throttle:30,1,chat-send');
 Route::post('/api/messages/read',[Chat::class,'read']);
 Route::post('/api/notifications/read',[Chat::class,'readNotifications']);
 Route::post('/api/communication/preferences',[Chat::class,'preferences']);
});
Route::get('/api/scoring-updates',function(\App\Support\FantasyDay $days,\App\Support\LiveScoring\SnapshotRepository $repository){
 $date=$days->today()->toDateString();$snapshot=$repository->get($date)??['fantasy_date'=>$date];
 $state=\App\Support\LiveScoring\ScoringAlert::state($snapshot);
 $state['matchups']=$snapshot['matchups']??[];
 $state['teams']=collect($snapshot['teams']??[])->map(fn($team,$id)=>['id'=>(string)$id,'name'=>$team['name']])->values();
 if(request('scope')==='nhl')$state['nhlEvents']=\App\Support\LiveScoring\NhlUpdates::events($date, $snapshot ?? []);
 return response()->json($state)->header('Cache-Control','no-store');
})->middleware('throttle:120,1,scoring-popups');
