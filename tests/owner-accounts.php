<?php
// Independent SQLite database; all outbound push/Google calls are mocked.
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE=:memory:');putenv('SESSION_DRIVER=database');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
use App\Models\User;
use App\Models\TeamClaim;
use App\Support\OwnerTeams;
use App\Support\OwnerNotificationPolicy;
use App\Support\WebPush;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;
use Carbon\CarbonImmutable;
function verifyOwner($ok,$message){if(!$ok)throw new RuntimeException($message);}
config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true],'session.secure'=>false]);
foreach(glob(__DIR__.'/../database/migrations/*create*.php') as $f)(require $f)->up();
(require __DIR__.'/../database/migrations/2026_10_01_235500_add_fantasy_team_to_push_notifications.php')->up();
(require __DIR__.'/../database/migrations/2026_10_02_010000_add_fantasy_team_to_push_subscriptions.php')->up();
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03T00:30:00-03:00'));
$day='2026-10-02';
DB::table('seasons')->insert(['season_id'=>'2026-27','season_name'=>'2026-27']);
foreach(['a'=>'Alpha','b'=>'Beta','lone'=>'Ꮮ૦ท૯⚡️𐌕รคг'] as $id=>$name){
 DB::table('active_fantasy_rosters')->insert(['game_date'=>$day,'fantasy_team_id'=>$id,'fantasy_team_name'=>$name,'player_id'=>'p'.$id,'player_name'=>'Player','position'=>'F']);
 DB::table('team_seasons')->insert(['team_season_id'=>'season-'.$id,'season_id'=>'2026-27','franchise_id'=>$id==='lone'?'F012':$id,'original_name'=>$name]);
}
DB::table('active_available_goalies')->insert(['game_date'=>$day,'team'=>'MTL','player_name'=>'Sam Goalie','opponent'=>'TOR','availability'=>'FA']);
DB::table('active_available_goalies')->insert(['game_date'=>'2026-10-03','team'=>'TOR','player_name'=>'Tomorrow Goalie','opponent'=>'MTL','availability'=>'W','waiver_day'=>'Sun']);
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function ownerRequest($method,$url,$body=[],&$cookies=[],$headers=[]){
 global $app,$kernel;
 $app->forgetScopedInstances();Auth::forgetGuards();$app->make('session')->forgetDrivers();$app->forgetInstance('session.store');
 $r=Illuminate\Http\Request::create($url,$method,$body,$cookies,[],array_merge(['HTTP_ACCEPT'=>'application/json'],$headers));
 $resp=$kernel->handle($r);foreach($resp->headers->getCookies() as $cookie)$cookies[$cookie->getName()]=$cookie->getValue();
 $kernel->terminate($r,$resp);return $resp;
}
$guest=[];$response=ownerRequest('GET','/register',[],$guest,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($response->getStatusCode()===200,'Register render failed: '.substr(strip_tags($response->getContent()),0,1000));
verifyOwner(str_contains($response->getContent(),'/team-icons/alpha'),'Team logo absent');
verifyOwner(!str_contains($response->getContent(),'value="lone"'),'Reserved team offered publicly');
verifyOwner(!str_contains($response->getContent(),'href="/admin"'),'Admin menu exposed to guest');
verifyOwner(ownerRequest('GET','/admin',[],$guest)->getStatusCode()===401,'Guest admin access allowed');
verifyOwner(ownerRequest('POST','/job-status/run/all',[],$guest)->getStatusCode()===401,'Guest collector trigger allowed');
verifyOwner(ownerRequest('POST','/team-icons/alpha',[],$guest)->getStatusCode()===401,'Guest image mutation allowed');
$alpha=[];$response=ownerRequest('POST','/register',['name'=>'Owner A','email'=>'a@example.org','password'=>'strong-example-a','password_confirmation'=>'strong-example-a','team_id'=>'a'],$alpha);
verifyOwner($response->getStatusCode()===302,'Signup failed: '.$response->getContent());
$a=User::where('email','a@example.org')->first();verifyOwner($a && $a->claim->fantasy_team_id==='a' && !$a->is_admin,'Signup/team/admin state incorrect');
verifyOwner($a->password!=='strong-example-a','Password was not hashed');
$response=ownerRequest('GET','/account',[],$alpha,['HTTP_ACCEPT'=>'text/html']);verifyOwner($response->getStatusCode()===200,'Account render failed: '.$response->getContent());
verifyOwner(ownerRequest('GET','/admin',[],$alpha)->getStatusCode()===403,'Regular owner admin access allowed');
verifyOwner(ownerRequest('POST','/lineup-advisors/mike/profile',['first_name'=>'Oops'],$alpha)->getStatusCode()===403,'Regular owner admin mutation allowed');
$second=[];$response=ownerRequest('POST','/register',['name'=>'Duplicate','email'=>'duplicate@example.org','password'=>'strong-example-b','password_confirmation'=>'strong-example-b','team_id'=>'a'],$second);
verifyOwner($response->getStatusCode()===422 && !User::where('email','duplicate@example.org')->exists(),'Duplicate claim left an orphan account: '.$response->getStatusCode().' '.$response->getContent());
$response=ownerRequest('POST','/register',['name'=>'Imposter','email'=>'imposter@example.org','password'=>'strong-example-b','password_confirmation'=>'strong-example-b','team_id'=>'lone'],$second);
verifyOwner($response->getStatusCode()===422 && !User::where('is_admin',true)->exists(),'Reserved-team admin escalation');
$configInvite=str_repeat('i',64);config(['owners.admin_invite_token'=>$configInvite,'owners.admin_invite_expires'=>'2099-01-01T00:00:00Z']);
$admin=[];verifyOwner(ownerRequest('GET','/account/admin-invite?token='.$configInvite,[],$admin)->getStatusCode()===302,'Admin invitation rejected');
$response=ownerRequest('POST','/register',['name'=>'Dan','email'=>'dan@example.org','password'=>'strong-example-c','password_confirmation'=>'strong-example-c','team_id'=>'lone'],$admin);
verifyOwner($response->getStatusCode()===302 && User::where('email','dan@example.org')->value('is_admin'),'Admin invitation did not assign administrator');
verifyOwner(ownerRequest('GET','/account/admin-invite?token='.$configInvite,[],$guest)->getStatusCode()===410,'Admin invitation reusable after activation');
$response=ownerRequest('GET','/account',[],$admin,['HTTP_ACCEPT'=>'text/html']);verifyOwner(str_contains($response->getContent(),'href="/admin"'),'Administrator menu absent');
// Authorization policy covers distinct scoring/goalie combinations and Pacific fantasy-day boundaries.
$b=User::create(['name'=>'B','email'=>'b@example.org','password'=>'strong-example-b']);(new OwnerTeams)->claim($b,'b');
$p=new OwnerNotificationPolicy;
verifyOwner($p->accepts($a,'live-score','a',[]) && !$p->accepts($a,'live-score','b',['opponent_team_id'=>'a']),'Own-only scoring filters failed');
$a->notification_preferences=['team_scores'=>false,'opponent_scores'=>true,'available_today'=>true];$a->save();
verifyOwner(!$p->accepts($a,'live-score','a',[]) && $p->accepts($a,'live-score','b',['opponent_team_id'=>'a']),'Opponent scoring filters failed');
$context=['game_date'=>$day,'available'=>true,'goalie_key'=>'MTL|samgoalie'];
verifyOwner($p->accepts($a,'goalie-status',null,$context),'Available today alert rejected');
verifyOwner(!$p->accepts($a,'goalie-status',null,array_replace($context,['game_date'=>'2026-10-03'])),'Tomorrow alert accepted with today-only filter');
verifyOwner(!$p->accepts($a,'goalie-status',null,array_replace($context,['available'=>false])),'Unavailable goalie accepted');
$a->notification_preferences=['team_scores'=>true,'goalies'=>['MTL|samgoalie']];$a->save();
verifyOwner($p->accepts($a,'goalie-status',null,$context),'Watched goalie alert rejected');
verifyOwner(!$p->accepts($a,'goalie-status',null,array_replace($context,['goalie_key'=>'TOR|other'])),'Unwatched goalie alert accepted');
verifyOwner(!$p->accepts($a,'goalie-status',null,array_replace($context,['game_date'=>'2026-10-04'])),'Future/past goalie alert accepted');
$a->notification_preferences=['all_goalies'=>true];$a->save();verifyOwner($p->accepts($a,'goalie-status',null,array_replace($context,['available'=>false])),'All-goalie filter failed');
verifyOwner(app(App\Support\OwnerGoalies::class)->options()->count()===1,'Waivers not filtered against the game date');
$response=ownerRequest('GET','/notifications',[],$alpha,['HTTP_ACCEPT'=>'text/html']);verifyOwner($response->getStatusCode()===200 && str_contains($response->getContent(),'Available goalies'),'Notifications page render failed');
Http::preventStrayRequests();Http::fake(['https://fcm.googleapis.com/*'=>Http::response('',201)]);
$a->notification_preferences=['team_scores'=>true];$a->save();
$push=app(WebPush::class);$push->subscribe('https://fcm.googleapis.com/fcm/send/alpha',$a->id,'token-alpha');$push->subscribe('https://fcm.googleapis.com/fcm/send/beta',$b->id,'token-beta');
$push->notify('live-score','Score','Player scored','/teams/current','a',['opponent_team_id'=>'b']);
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer token-alpha']);verifyOwner(count(json_decode($feed->getContent(),true)['notifications'])===1,'Selected owner delivery missing');
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer token-beta']);verifyOwner(count(json_decode($feed->getContent(),true)['notifications'])===0,'Other owner received scoring alert');
$feed=ownerRequest('GET','/push/notifications',[],$guest);verifyOwner(json_decode($feed->getContent(),true)['notifications']===[],'Anonymous shared feed exposed');
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer wrong']);verifyOwner(json_decode($feed->getContent(),true)['notifications']===[],'Invalid feed token exposed events');
verifyOwner(ownerRequest('POST','/push/subscribe',['endpoint'=>'https://127.0.0.1/internal'], $alpha)->getStatusCode()===422,'Push endpoint allowed SSRF');
// Google-linking is explicit; a matching email never silently takes over an existing password account.
config(['owners.google_client_id'=>'test-client','owners.google_client_secret'=>'test-secret']);
Http::fake(['https://oauth2.googleapis.com/token'=>Http::response(['access_token'=>'google-test']),'https://openidconnect.googleapis.com/v1/userinfo'=>Http::response(['sub'=>'google-a','email'=>'a@example.org','email_verified'=>true,'name'=>'Owner A'])]);
$response=ownerRequest('GET','/auth/google',[],$alpha);parse_str(parse_url($response->headers->get('Location'),PHP_URL_QUERY),$q);
$response=ownerRequest('GET','/auth/google/callback?state='.$q['state'].'&code=code',[],$alpha);verifyOwner($response->getStatusCode()===302 && $a->fresh()->google_id==='google-a','Explicit Google link failed: '.$response->getContent());
verifyOwner(ownerRequest('GET','/auth/google/callback?state=wrong&code=code',[],$guest)->getStatusCode()===419,'OAuth state validation missing');
// Correct password signs in; wrong password fails without an authenticated session.
$login=[];verifyOwner(ownerRequest('POST','/login',['email'=>'b@example.org','password'=>'wrong'],$login)->getStatusCode()===422,'Invalid password accepted');
verifyOwner(ownerRequest('POST','/login',['email'=>'b@example.org','password'=>'strong-example-b'],$login)->getStatusCode()===302,'Password sign-in failed');
// Reverse-proxy redirects preserve HTTPS for secure session cookies.
$response=ownerRequest('GET','/admin',[],$guest,['HTTP_ACCEPT'=>'text/html','HTTP_X_FORWARDED_PROTO'=>'https','REMOTE_ADDR'=>'10.0.0.1']);
verifyOwner(str_starts_with((string)$response->headers->get('Location'),'https://'),'Proxy HTTPS was lost on a redirect');
CarbonImmutable::setTestNow();
echo "Owner account checks passed: pages, optional browsing, exclusive claims, reserved admin invitation, admin routes/actions, password hashing/login, Google state/linking, own/opponent scoring, goalie filters, waiver dates, isolated push delivery, and SSRF rejection.\n";
