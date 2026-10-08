<?php
// In-memory database and fake HTTP: no real users are messaged and no external push is sent.
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE=:memory:');putenv('SESSION_DRIVER=database');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
use App\Models\User;
use App\Models\TeamClaim;
use App\Support\Messaging;
use App\Support\OwnerNotificationPolicy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
function checkChat($ok,$message){if(!$ok)throw new RuntimeException($message);}
config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'session.secure'=>false]);
foreach(glob(__DIR__.'/../database/migrations/*create*.php') as $f)(require $f)->up();
(require __DIR__.'/../database/migrations/2026_10_01_235500_add_fantasy_team_to_push_notifications.php')->up();
Http::preventStrayRequests();Http::fake(['https://fcm.googleapis.com/*'=>Http::response('',201)]);
$users=[];$cookies=[];
foreach(['Alice','Bob','Carol'] as $name){$user=User::create(['name'=>$name,'email'=>strtolower($name).'@example.org','password'=>'example-password-123']);$users[$name]=$user;$cookies[$name]=[];TeamClaim::create(['fantasy_team_id'=>strtolower($name),'user_id'=>$user->id,'team_name'=>$name.' Team']);}
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function chatRequest($method,$url,$body=[],&$cookies=[],$html=false){
 global $app,$kernel;$app->forgetScopedInstances();Auth::forgetGuards();$app->make('session')->forgetDrivers();$app->forgetInstance('session.store');$app->forgetInstance('redirect');
 $r=Illuminate\Http\Request::create($url,$method,$body,$cookies,[],['HTTP_ACCEPT'=>$html?'text/html':'application/json']);
 $response=$kernel->handle($r);foreach($response->headers->getCookies() as $cookie)$cookies[$cookie->getName()]=$cookie->getValue();$kernel->terminate($r,$response);return $response;
}
function payload($response){return json_decode($response->getContent(),true);}
foreach($users as $name=>$u)checkChat(chatRequest('POST','/login',['email'=>$u->email,'password'=>'example-password-123'],$cookies[$name])->getStatusCode()===302,'Sign in failed');
$guest=[];checkChat(chatRequest('GET','/api/messages/state',[],$guest)->getStatusCode()===401,'Guest sees message state');
checkChat(chatRequest('POST','/api/messages/send',['body'=>'Hello','client_id'=>Str::uuid()->toString()],$guest)->getStatusCode()===401,'Guest can send');
$alice=$users['Alice'];$bob=$users['Bob'];$carol=$users['Carol'];
$push=app(App\Support\WebPush::class);foreach($users as $name=>$u)$push->subscribe('https://fcm.googleapis.com/fcm/send/'.strtolower($name),$u->id,'token-'.$name);
$send=['user_id'=>$bob->id,'body'=>'Private hello <script>alert(1)</script>','client_id'=>Str::uuid()->toString()];
$r=chatRequest('POST','/api/messages/send',$send,$cookies['Alice']);checkChat($r->getStatusCode()===201,'Private send failed: '.$r->getContent());$privateId=payload($r)['message']['id'];checkChat((bool)preg_match('/T.*[+-]\d{2}:\d{2}$/',payload($r)['message']['created_at']),'Message time has no timezone offset');
checkChat(chatRequest('POST','/api/messages/send',$send,$cookies['Alice'])->getStatusCode()===200&&DB::table('chat_messages')->count()===1,'Retry duplicates messages');
checkChat(chatRequest('POST','/api/messages/send',array_replace($send,['body'=>'Changed']),$cookies['Alice'])->getStatusCode()===409,'Idempotency key changes body');
$rows=payload(chatRequest('GET','/api/messages/conversation?user_id='.$alice->id,[],$cookies['Bob']))['messages'];checkChat(count($rows)===1&&str_contains($rows[0]['body'],'<script>'),'Message content altered or missing');
$r=chatRequest('GET','/api/messages/conversation?user_id='.$alice->id,[],$cookies['Carol']);checkChat(payload($r)['messages']===[],'Private messages leaked to another owner');
$state=payload(chatRequest('GET','/api/messages/state?after=0',[],$cookies['Carol']));checkChat($state['messages']===[]&&$state['unread']['total']===0,'Private popup or counter leaked');
$state=payload(chatRequest('GET','/api/messages/state?after=0',[],$cookies['Bob']));checkChat($state['unread']['private']===1&&count($state['messages'])===1,'Recipient unread or popup missing');
checkChat(!str_contains(json_encode($state),'alice@example.org'),'Private email exposed');
$unlinked=User::create(['name'=>'Unlinked account','email'=>'unlinked@example.org','password'=>'example-password-123']);
$directory=payload(chatRequest('GET','/api/messages/state',[],$cookies['Bob']))['teams'];
checkChat(count($directory)===2&&collect($directory)->pluck('user_id')->sort()->values()->all()===collect([$alice->id,$carol->id])->sort()->values()->all(),'Chat team directory includes self or unlinked accounts');
checkChat(!str_contains(json_encode($directory),'example.org')&&!str_contains(json_encode($directory),'"name":"'),'Chat directory exposes personal identity');
checkChat(chatRequest('POST','/api/messages/read',['user_id'=>$alice->id,'last_id'=>$privateId],$cookies['Carol'])->getStatusCode()===403,'Stranger marks private message read');
$r=chatRequest('POST','/api/messages/read',['user_id'=>$alice->id,'last_id'=>$privateId],$cookies['Bob']);checkChat(payload($r)['unread']['total']===0,'Reading private message does not clear count');
$r=chatRequest('POST','/api/messages/send',['body'=>'League hello','client_id'=>Str::uuid()->toString()],$cookies['Alice']);$leagueId=payload($r)['message']['id'];
checkChat(Messaging::unread($bob->id)['league']===1&&Messaging::unread($carol->id)['league']===1&&Messaging::unread($alice->id)['total']===0,'League unread or sender exclusion failed');
checkChat(count(payload(chatRequest('GET','/api/messages/conversation',[],$cookies['Carol']))['messages'])===1,'League conversation missing');
$r=chatRequest('POST','/api/messages/read',['last_id'=>$leagueId],$cookies['Bob']);checkChat(payload($r)['unread']['total']===0,'League read count failed');
checkChat(chatRequest('POST','/api/messages/read',['last_id'=>$privateId],$cookies['Bob'])->getStatusCode()===403,'Read cursor can cross conversations');
checkChat(chatRequest('POST','/api/messages/send',['user_id'=>$alice->id,'body'=>'Self','client_id'=>Str::uuid()->toString()],$cookies['Alice'])->getStatusCode()===422,'Can message self');
checkChat(chatRequest('POST','/api/messages/send',['body'=>'   ','client_id'=>Str::uuid()->toString()],$cookies['Alice'])->getStatusCode()===422,'Blank message accepted');
checkChat(chatRequest('POST','/api/messages/send',['body'=>str_repeat('x',4001),'client_id'=>Str::uuid()->toString()],$cookies['Alice'])->getStatusCode()===422,'Oversize message accepted');
$deliveries=fn($uid,$category)=>DB::table('push_deliveries as d')->join('push_subscriptions as s','s.id','=','d.subscription_id')->join('push_notifications as n','n.id','=','d.notification_id')->where('s.user_id',$uid)->where('n.category',$category)->count();
checkChat($deliveries($bob->id,'private-message')===1&&$deliveries($carol->id,'private-message')===0&&$deliveries($alice->id,'private-message')===0,'Private push recipients wrong');
checkChat($deliveries($bob->id,'league-message')===1&&$deliveries($carol->id,'league-message')===1&&$deliveries($alice->id,'league-message')===0,'League push recipients wrong');
$r=chatRequest('POST','/api/communication/preferences',['league_message_push'=>false,'private_message_popups'=>false],$cookies['Bob']);checkChat(payload($r)['preferences']['private_message_push']===true,'Mute alters unrelated setting');
chatRequest('POST','/api/messages/send',['body'=>'League muted','client_id'=>Str::uuid()->toString()],$cookies['Alice']);checkChat($deliveries($bob->id,'league-message')===0,'Muted league still receives pushes');
chatRequest('POST','/api/communication/preferences',['notifications_enabled'=>false],$cookies['Bob']);
chatRequest('POST','/api/messages/send',['user_id'=>$bob->id,'body'=>'Muted push','client_id'=>Str::uuid()->toString()],$cookies['Alice']);checkChat($deliveries($bob->id,'private-message')===0,'Master Off does not mute push');
$r=chatRequest('GET','/push/notifications',[],$guest);checkChat(payload($r)['notifications']===[],'Anonymous push feed exposes messages');
// A bell event is stored even without browser permission; messages have their own counter.
$push->notify('live-score','Alice goal','Player scored','/teams/current','alice');
$state=payload(chatRequest('GET','/api/messages/state',[],$cookies['Alice']));checkChat($state['notification_count']===1,'Bell unread missing');$notificationId=$state['notifications'][0]['id'];
chatRequest('POST','/api/notifications/read',['ids'=>[$notificationId]],$cookies['Bob']);checkChat(DB::table('owner_notification_inbox')->where('user_id',$alice->id)->whereNull('read_at')->count()===1,'Other user marked bell event read');
chatRequest('POST','/api/notifications/read',['ids'=>[$notificationId]],$cookies['Alice']);checkChat(payload(chatRequest('GET','/api/messages/state',[],$cookies['Alice']))['notification_count']===0,'Bell read count not cleared');
$r=chatRequest('GET','/messages?user_id='.$bob->id,[],$cookies['Alice'],true);if(in_array('--browser-page',$argv,true))file_put_contents(__DIR__.'/../storage/app/communication-test.html',$r->getContent());checkChat($r->getStatusCode()===200&&str_contains($r->getContent(),'Private conversation with Bob Team'),'Private page render failed');checkChat(str_contains($r->headers->get('Cache-Control'),'no-store'),'Private messages cacheable');
$r=chatRequest('GET','/account',[],$cookies['Alice'],true);$html=$r->getContent();checkChat($r->getStatusCode()===200&&str_contains($html,'id="live-score-updates"')&&str_contains($html,'/live-score-updates.js')&&str_contains($html,'/app-communication.js'),'Shared scoring panel or scripts missing');
checkChat(!str_contains($html,'id="live-score-updates-enabled"'),'Removed scoring checkbox returned');
checkChat(str_contains($html,'class="scoring-settings-icon" href="/notifications#alerts"'),'Scoring settings icon missing');
checkChat(!str_contains($html,'class="scoring-settings-link"'),'Old scoring settings link remains');
$r=chatRequest('GET','/notifications',[],$cookies['Alice'],true);$settings=$r->getContent();
checkChat($r->getStatusCode()===200&&str_contains($settings,'id="settings-notifications"')&&str_contains($settings,'id="settings-alerts"'),'Notification and alert settings tabs missing');
checkChat(str_contains($settings,'id="scoring-settings-controls"')&&str_contains($settings,'/notification-settings.js'),'Scoring settings controls missing');
$settingsDom=new DOMDocument();@$settingsDom->loadHTML($settings);$settingsXpath=new DOMXPath($settingsDom);
foreach(['notifications_enabled','private_message_push','league_message_push','team_scores','opponent_scores','own_goalies','all_goalies','available_today','available_tomorrow','goalies[]'] as $key){
 checkChat($settingsXpath->query('//*[@id="settings-alerts"]//input[@name="'.$key.'"]')->length===0,'Notification setting in Alerts: '.$key);
}
foreach(['private_message_popups','league_message_popups'] as $key){
 checkChat($settingsXpath->query('//*[@id="settings-alerts"]//input[@type="checkbox" and @name="'.$key.'"]')->length===1,'Popup setting missing in Alerts: '.$key);
 checkChat($settingsXpath->query('//*[@id="settings-notifications"]//input[@name="'.$key.'"]')->length===0,'Popup setting in Notifications: '.$key);
}
checkChat(str_contains($html,'id="chat-panel"')&&str_contains($html,'/chat-panel.js'),'Shared chat panel missing');
checkChat(!str_contains($html,'id="live-score-updates-status"'),'On/Off text still in header');
$r=chatRequest('GET','/api/scoring-updates',[],$guest);checkChat($r->getStatusCode()===200&&isset(payload($r)['matchups']),'Scoring endpoint unavailable across app');
// Pagination and monotonic read positions.
for($i=0;$i<55;$i++)DB::table('chat_messages')->insert(['sender_id'=>$alice->id,'recipient_id'=>$bob->id,'client_id'=>Str::uuid()->toString(),'body'=>'Page '.$i,'created_at'=>now(),'updated_at'=>now()]);
$r=payload(chatRequest('GET','/api/messages/conversation?user_id='.$alice->id,[],$cookies['Bob']));checkChat(count($r['messages'])===50&&$r['has_more'],'Pagination failed');
$first=$r['messages'][0]['id'];$latest=end($r['messages'])['id'];
$r=payload(chatRequest('GET','/api/messages/conversation?user_id='.$alice->id.'&before='.$first,[],$cookies['Bob']));checkChat(count($r['messages'])===7,'Earlier messages inaccessible');
chatRequest('POST','/api/messages/read',['user_id'=>$alice->id,'last_id'=>$latest],$cookies['Bob']);chatRequest('POST','/api/messages/read',['user_id'=>$alice->id,'last_id'=>$privateId],$cookies['Bob']);
checkChat(Messaging::unread($bob->id)['private']===0,'Stale tab moved read cursor backward');
echo "Messaging checks passed: private isolation, league chat, unread/read cursors, idempotent sends, pagination, defaults/mutes, push recipients, bell inbox, shared header and scoring endpoint.\n";
