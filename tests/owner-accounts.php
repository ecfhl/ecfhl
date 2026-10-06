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
(require __DIR__.'/../database/migrations/2026_10_03_210000_add_season_actuals_to_player_projections.php')->up();
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
 // Rebuild Redirector too: it retains the prior session store in this multi-request harness.
 $app->forgetInstance('redirect');
 $r=Illuminate\Http\Request::create($url,$method,$body,$cookies,[],array_merge(['HTTP_ACCEPT'=>'application/json'],$headers));
 $resp=$kernel->handle($r);foreach($resp->headers->getCookies() as $cookie)$cookies[$cookie->getName()]=$cookie->getValue();
 $kernel->terminate($r,$resp);return $resp;
}
$guest=[];$response=ownerRequest('GET','/register',[],$guest,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($response->getStatusCode()===200,'Register render failed: '.substr(strip_tags($response->getContent()),0,1000));
verifyOwner(str_contains($response->headers->get('Cache-Control'),'no-store'),'Signup page can cache a stale session token');
verifyOwner(str_contains($response->getContent(),'/submit-guard.js'),'Shared submit protection missing');
verifyOwner(!str_contains($response->getContent(),'id="guest-signup-dialog"'),'Signup page must not show a signup invitation');
verifyOwner(str_contains($response->getContent(),'class="nav-create-account"'),'Guest menu needs a prominent signup action');
verifyOwner(str_contains($response->getContent(),'/team-icons/alpha'),'Team logo absent');
verifyOwner(str_contains($response->getContent(),'data-team-icon-viewer data-team-slug="alpha" data-team-name="Alpha"'),'Signup team logos must open the shared team viewer.');
verifyOwner(!str_contains($response->getContent(),'value="lone"'),'Reserved team offered publicly');
verifyOwner(!str_contains($response->getContent(),'href="/admin"'),'Admin menu exposed to guest');
verifyOwner(ownerRequest('GET','/admin',[],$guest)->getStatusCode()===401,'Guest admin access allowed');
verifyOwner(ownerRequest('GET','/admin/projections',[],$guest)->getStatusCode()===401,'Guest projection settings access allowed');
verifyOwner(ownerRequest('POST','/admin/projections',['weights'=>App\Support\ProjectionMath::DEFAULT_WEIGHTS],$guest)->getStatusCode()===401,'Guest projection mutation allowed');
verifyOwner(ownerRequest('POST','/admin/projections/preview',['units'=>['fantrax'=>6,'season'=>2,'7d'=>2,'14d'=>0,'21d'=>0]],$guest)->getStatusCode()===401,'Guest projection preview allowed');
verifyOwner(ownerRequest('POST','/job-status/run/all',[],$guest)->getStatusCode()===401,'Guest collector trigger allowed');
verifyOwner(ownerRequest('POST','/team-icons/alpha',[],$guest)->getStatusCode()===401,'Guest image mutation allowed');
$alpha=[];$response=ownerRequest('POST','/register',['name'=>'Owner A','email'=>'a@example.org','password'=>'strong-example-a','password_confirmation'=>'strong-example-a','team_id'=>'a'],$alpha);
verifyOwner($response->getStatusCode()===302,'Signup failed: '.$response->getContent());
$a=User::where('email','a@example.org')->first();verifyOwner($a && $a->claim->fantasy_team_id==='a' && !$a->is_admin,'Signup/team/admin state incorrect');
verifyOwner($a->password!=='strong-example-a','Password was not hashed');
$response=ownerRequest('GET','/account',[],$alpha,['HTTP_ACCEPT'=>'text/html']);verifyOwner($response->getStatusCode()===200,'Account render failed: '.$response->getContent());
verifyOwner(str_contains($response->getContent(),'data-team-icon-viewer data-team-slug="alpha" data-team-name="Alpha"')&&str_contains($response->getContent(),'data-full-src="/team-icons/alpha"'),'Account team logo must open its full-size viewer.');
verifyOwner(str_contains($response->headers->get('Cache-Control'),'no-store'),'Private account page can be cached');
verifyOwner(!str_contains($response->getContent(),'id="guest-signup-dialog"')&&!str_contains($response->getContent(),'class="nav-create-account"'),'Signed-in owner received signup prompts');
// Enable real CSRF middleware for expired forms and repeats after successful sign-in.
$app['env']='local';
foreach(['/login','/register'] as $authUrl){
 $response=ownerRequest('POST',$authUrl,['_token'=>'expired','name'=>'Retained Name','email'=>'retained@example.org','password'=>'must-not-be-flashed','password_confirmation'=>'must-not-be-flashed','team_id'=>'b'],$guest,['HTTP_ACCEPT'=>'text/html']);
 verifyOwner($response->getStatusCode()===302&&parse_url($response->headers->get('Location'),PHP_URL_PATH)===$authUrl,'Expired auth form showed a raw error');
 $response=ownerRequest('GET',$authUrl,[],$guest,['HTTP_ACCEPT'=>'text/html']);
 verifyOwner(str_contains($response->getContent(),'This form expired.'),'Expired form message missing for '.$authUrl);
 verifyOwner(str_contains($response->getContent(),'retained@example.org'),'Expired form lost safe input for '.$authUrl);
 verifyOwner(!str_contains($response->getContent(),'must-not-be-flashed'),'Expired form retained a password');
 $response=ownerRequest('POST',$authUrl,['_token'=>'expired'],$alpha,['HTTP_ACCEPT'=>'text/html']);
 verifyOwner($response->getStatusCode()===302&&parse_url($response->headers->get('Location'),PHP_URL_PATH)==='/account','Repeat after successful auth did not recover to the account');
}
$response=ownerRequest('POST','/login',['_token'=>'expired'],$guest);
verifyOwner($response->getStatusCode()===419&&str_contains($response->getContent(),'Reload this page'),'AJAX expired-session message unclear');
$app['env']='testing';
verifyOwner(ownerRequest('GET','/admin',[],$alpha)->getStatusCode()===403,'Regular owner admin access allowed');
verifyOwner(ownerRequest('POST','/admin/projections',['weights'=>App\Support\ProjectionMath::DEFAULT_WEIGHTS],$alpha)->getStatusCode()===403,'Regular owner projection mutation allowed');
verifyOwner(ownerRequest('POST','/admin/projections/preview',['units'=>['fantrax'=>6,'season'=>2,'7d'=>2,'14d'=>0,'21d'=>0]],$alpha)->getStatusCode()===403,'Regular owner projection preview allowed');
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
verifyOwner(str_contains($response->getContent(),'href="/admin/projections"'),'Projection weights link missing from administrator menu');
$landing=ownerRequest('GET','/admin',[],$admin,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($landing->getStatusCode()===200 && substr_count($landing->getContent(),'class="card admin-menu-card"')===5, 'Admin must open a card menu instead of redirecting to Advisors');
$response=ownerRequest('GET','/admin/projections',[],$admin,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($response->getStatusCode()===200 && str_contains($response->getContent(),'Season to date') && str_contains($response->getContent(),'units[21d]'),'Projection settings page failed to render');
$response=ownerRequest('POST','/admin/projections',['weights'=>['fantrax'=>20,'season'=>30,'7d'=>25,'14d'=>15,'21d'=>10]],$admin);
verifyOwner($response->getStatusCode()===302 && App\Support\ProjectionSettings::weights()['season']===30.0,'Admin weights were not persisted');
$response=ownerRequest('POST','/admin/projections',['weights'=>['fantrax'=>30,'season'=>30,'7d'=>25,'14d'=>15,'21d'=>10]],$admin);
verifyOwner($response->getStatusCode()===422 && App\Support\ProjectionSettings::weights()['fantrax']===20.0,'Invalid total changed saved settings');
$response=ownerRequest('POST','/admin/projections',['units'=>['fantrax'=>6,'season'=>2,'7d'=>2,'14d'=>0,'21d'=>0]],$admin);
verifyOwner($response->getStatusCode()===302 && App\Support\ProjectionSettings::weights()===['fantrax'=>60.0,'season'=>20.0,'7d'=>20.0,'14d'=>0.0,'21d'=>0.0],'Slider units must convert 6/2/2 into 60%/20%/20%');
$response=ownerRequest('POST','/admin/projections',['units'=>['fantrax'=>7,'season'=>2,'7d'=>2,'14d'=>0,'21d'=>0]],$admin);
verifyOwner($response->getStatusCode()===422 && App\Support\ProjectionSettings::weights()['fantrax']===60.0,'A slider total over 10 units changed saved settings');
$response=ownerRequest('POST','/admin/projections/preview',['units'=>['fantrax'=>5,'season'=>3,'7d'=>2,'14d'=>0,'21d'=>0]],$admin);
verifyOwner($response->getStatusCode()===200 && json_decode($response->getContent(),true)['weights']['fantrax']===50 && App\Support\ProjectionSettings::weights()['fantrax']===60.0,'Admin preview must calculate proposed weights without saving');
$response=ownerRequest('POST','/admin/projections/preview',['units'=>['fantrax'=>6,'season'=>3,'7d'=>2,'14d'=>0,'21d'=>0]],$admin);
verifyOwner($response->getStatusCode()===422,'Preview must reject a total above 10 units');
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
// Own-goalie alerts include every roster slot and are independent of free-agent alerts.
$ownGoalieKey='LAK|ownedgoalie';$ownContext=['game_date'=>$day,'available'=>false,'goalie_key'=>$ownGoalieKey];
foreach([$day,'2026-10-03'] as $rosterDay)foreach(['ACTIVE','BENCH','MINORS','INJURED_RESERVE'] as $slot){
 DB::table('active_fantasy_rosters')->insert(['game_date'=>$rosterDay,'fantasy_team_id'=>'a','fantasy_team_name'=>'Alpha','player_id'=>'g-'.$slot,'player_name'=>$slot==='ACTIVE'?'Owned Goalie':'Owned '.$slot,'nhl_team'=>'LA','position'=>'G','roster_status'=>$slot]);
}
$a->notification_preferences=['own_goalies'=>true,'goalies'=>[]];$a->save();$ownPolicy=new OwnerNotificationPolicy;
verifyOwner($ownPolicy->accepts($a,'goalie-status',null,$ownContext),'Roster goalie rejected unless a free agent');
verifyOwner($ownPolicy->accepts($a,'goalie-status',null,array_replace($ownContext,['game_date'=>'2026-10-03'])),'Tomorrow roster goalie rejected');
foreach(['BENCH','MINORS','INJURED_RESERVE'] as $slot)verifyOwner($ownPolicy->accepts($a,'goalie-status',null,array_replace($ownContext,['goalie_key'=>OwnerNotificationPolicy::goalieKey('LAK','Owned '.$slot)])),'Roster slot ignored: '.$slot);
verifyOwner(!$ownPolicy->accepts($a,'goalie-status',null,array_replace($ownContext,['goalie_key'=>'TOR|otherowner'])),'Other owner goalie leaked');
verifyOwner(!$ownPolicy->accepts($a,'goalie-status',null,array_replace($ownContext,['game_date'=>'2026-10-04'])),'Own-goalie future event accepted');
$b->notification_preferences=['own_goalies'=>true];$b->save();verifyOwner(!$ownPolicy->accepts($b,'goalie-status',null,$ownContext),'Own goalie sent to another owner');
$unclaimed=User::create(['name'=>'No team','email'=>'unclaimed@example.org','password'=>'strong-example-d','notification_preferences'=>['own_goalies'=>true]]);
verifyOwner(!$ownPolicy->accepts($unclaimed,'goalie-status',null,$ownContext),'Unclaimed owner received own-goalie alert');
$a->notification_preferences=['own_goalies'=>false];$a->save();verifyOwner(!$ownPolicy->accepts($a,'goalie-status',null,$ownContext),'Own-goalie switch off ignored');
$response=ownerRequest('POST','/notifications',['own_goalies'=>'1'],$alpha);
verifyOwner($response->getStatusCode()===200&&json_decode($response->getContent(),true)['preferences']['own_goalies']===true&&$a->fresh()->notification_preferences['own_goalies']===true,'Own-goalie preference did not save / confirm');
verifyOwner(json_decode($response->getContent(),true)['redirect_url']==='/teams/current/alpha','Saved notification preferences must return to the owner team');
$response=ownerRequest('GET','/notifications',[],$alpha,['HTTP_ACCEPT'=>'text/html']);
verifyOwner(str_contains($response->getContent(),'name="own_goalies" value="1" checked'),'Own-goalie saved switch not checked');
$response=ownerRequest('POST','/notifications',['own_goalies'=>'1'],$alpha,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($response->getStatusCode()===302&&parse_url($response->headers->get('Location'),PHP_URL_PATH)==='/teams/current/alpha','Native preference form fallback did not redirect to the owner team');
$push->notify('goalie-status','Goalie Status','Owned goalie confirmed','/notifications',null,$ownContext);
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer token-alpha']);
verifyOwner(count(json_decode($feed->getContent(),true)['notifications'])===1,'Own-goalie push delivery missing');
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer token-beta']);
verifyOwner(count(json_decode($feed->getContent(),true)['notifications'])===0,'Own-goalie push leaked to another team');
// Goalie watch list uses dated statuses and game instants, independent of alert eligibility.
foreach(['2026_09_30_210000_add_game_time_to_active_daily_players_table.php','2026_10_01_001900_add_game_started_to_active_daily_players.php','2026_09_30_000009_add_game_time_to_active_fantasy_rosters.php'] as $migration)(require __DIR__.'/../database/migrations/'.$migration)->up();
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-02T16:00:00-03:00'));
DB::table('active_available_goalies')->delete();
foreach([
 ['MTL','Sam Goalie','Fri 6:00PM',null],['TOR','Z Early','Fri 5:00PM',null],
 ['BOS','Already Started','Fri 3:00PM',null],['ANA','Live Game','Fri 9:00PM',null],
 ['SEA','Live Opponent',null,null],['EDM','Finished Game',null,null],
 ['CAR','Confirmed Starter','Fri 8:00PM',null],['CAR','Backup Goalie','Fri 8:00PM',null],
 ['BUF','Not Starting','Fri 8:00PM',null],['DET','Pool Confirmed','Fri 8:00PM','Confirmed'],
 ['DAL','Unknown Time',null,null],['FLA','At Start Time','Fri 4:00PM',null],
 ['VAN','Late Night','Sat 12:30AM',null],['NSH','Pool Started','Fri 9:00PM',null],
] as [$team,$name,$time,$status]){
 DB::table('active_available_goalies')->insert(['game_date'=>$day,'team'=>$team,'player_name'=>$name,'opponent'=>'NYR','availability'=>'FA','starting_status'=>$status]);
 DB::table('active_daily_players')->insert(['game_date'=>$day,'team'=>$team,'player_name'=>$name,'opponent'=>'NYR','position'=>'G','availability'=>'FA','game_time'=>$time,'game_started'=>$team==='NSH']);
}
DB::table('active_available_goalies')->insert(['game_date'=>'2026-10-03','team'=>'MTL','player_name'=>'Sam Goalie','opponent'=>'TOR','availability'=>'FA']);
DB::table('active_daily_players')->insert(['game_date'=>'2026-10-03','team'=>'MTL','player_name'=>'Sam Goalie','game_time'=>'Sat 5:00PM']);
foreach([['CAR','Confirmed Starter','Confirmed'],['TOR','Z Early','Probable'],['BUF','Not Starting','Not starting']] as [$team,$name,$status])DB::table('active_starting_goalies')->insert(['game_date'=>$day,'team'=>$team,'player_name'=>$name,'starting_status'=>$status,'source_url'=>'https://example.org','checked_at'=>'2026-10-02 19:00:00']);
DB::table('live_scoring_snapshots')->insert(['league_id'=>App\Support\LiveScoring\FantraxClient::LEAGUE_ID,'fantasy_date'=>$day,'source_date'=>$day,'source'=>'fantrax','source_payload'=>'{}','player_count'=>3,'collected_at'=>'2026-10-02 19:00:00','payload'=>json_encode(['players'=>[
 ['nhl_team'=>'ANA','opponent'=>'SEA','game_status'=>'2','starts_at'=>null],
 ['nhl_team'=>'EDM','game_status'=>'3','starts_at'=>null],
 ['nhl_team'=>'MTL','game_status'=>'1','starts_at'=>'2026-10-02T23:00:00Z'],
]])]);
$snapshotRow=DB::table('live_scoring_snapshots')->where('fantasy_date',$day)->first();$snapshotPayload=json_decode($snapshotRow->payload,true);
$snapshotPayload['players'][]=['nhl_team'=>'LA','player_name'=>'New Snapshot Goalie','position'=>'G','fantasy_team_id'=>'a','roster_status'=>'BENCH'];
DB::table('live_scoring_snapshots')->where('id',$snapshotRow->id)->update(['payload'=>json_encode($snapshotPayload)]);
$snapshotPolicy=new OwnerNotificationPolicy;
verifyOwner($snapshotPolicy->accepts($a->fresh(),'goalie-status',null,array_replace($ownContext,['goalie_key'=>'LAK|newsnapshotgoalie'])),'Snapshot roster membership ignored');
verifyOwner(!$snapshotPolicy->accepts($a->fresh(),'goalie-status',null,$ownContext),'Stale roster used over current snapshot');
foreach([['sam','Sam Goalie','MTL',4.25],['late','Late Night','VAN',4.25],['early','Z Early','TOR',0]] as [$id,$name,$team,$projection]){
 DB::table('player_projection_baselines')->insert(['player_id'=>$id,'player_name'=>$name,'nhl_team'=>$team,'position'=>'G','season_id'=>'2026-27','source_rank'=>1,'fantrax_fpts_per_game'=>99,'fantrax_season_fpts'=>999,'season_start'=>'2026-10-01','captured_at'=>'2026-10-02 12:00:00']);
 $row=['player_id'=>$id,'as_of_date'=>$day,'window_end_date'=>'2026-10-01','projected_fpts_per_game'=>$projection,'refreshed_at'=>'2026-10-02 12:00:00'];
 foreach([7,14,21] as $window){$row['fpts_'.$window.'d']=0;$row['gp_'.$window.'d']=0;$row['fpts_per_game_'.$window.'d']=0;}
 DB::table('player_projections')->insert($row);
}
DB::table('active_available_goalies')->where('player_name','Unknown Time')->update(['projected_fpts'=>500]);
$options=app(App\Support\OwnerGoalies::class)->options();
verifyOwner($options->where('day','Today')->pluck('name')->values()->all()===['Sam Goalie','Late Night','Z Early','Unknown Time'],'Custom projection ordering / start-time ties / missing vs zero /  confirmed / backup / live / final / exact-start filtering failed: '.$options->toJson());
verifyOwner($options->firstWhere('name','Sam Goalie')['projected_points']===4.25 && $options->firstWhere('name','Unknown Time')['projected_points']===null,'Watch list used Fantrax estimates instead of my projections');
verifyOwner($options->where('day','Tomorrow')->pluck('name')->values()->all()===['Sam Goalie'],'Today/tomorrow grouping lost a repeated goalie');
verifyOwner($options->firstWhere('name','Sam Goalie')['start_time']==='8:00 pm ADT','Snapshot instant not used for Atlantic start display');
verifyOwner($options->firstWhere('name','Late Night')['start']==='2026-10-03T00:30:00-03:00','Atlantic midnight date rolled backward');
verifyOwner(app(App\Support\OwnerGoalies::class)->available($day)->contains('player_name','Confirmed Starter'),'Confirmation alert eligibility was incorrectly removed');
$a->notification_preferences=['team_scores'=>false,'opponent_scores'=>true,'goalies'=>['CAR|confirmedstarter']];$a->save();
$response=ownerRequest('GET','/notifications',[],$alpha,['HTTP_ACCEPT'=>'text/html']);$html=$response->getContent();
verifyOwner(str_contains($html,'<details class="owner-goalie-day"><summary>Today</summary>')&&str_contains($html,'<details class="owner-goalie-day"><summary>Tomorrow</summary>'),'Collapsed day groups missing');
verifyOwner(!str_contains($html,'<strong>Confirmed Starter</strong>')&&str_contains($html,'type="hidden" name="goalies[]" value="CAR|confirmedstarter"'),'Hidden saved watch leaked into display / lost');
verifyOwner(ownerRequest('POST','/notifications/goalie',['key'=>'TOR|zearly','enabled'=>true],$guest)->getStatusCode()===401,'Guest goalie watch accepted');
foreach([true,true,false,true] as $enabled){
 $response=ownerRequest('POST','/notifications/goalie',['key'=>'TOR|zearly','enabled'=>$enabled],$alpha);
 verifyOwner($response->getStatusCode()===200&&json_decode($response->getContent(),true)['enabled']===$enabled,'Bell watch toggle failed: '.$response->getContent());
}
$preferences=$a->fresh()->notification_preferences;
verifyOwner($preferences['team_scores']===false&&$preferences['opponent_scores']===true&&$preferences['goalies']===['CAR|confirmedstarter','TOR|zearly'],'Bell overwrote other preferences / duplicated watch');
verifyOwner(ownerRequest('POST','/notifications/goalie',['key'=>'CAR|confirmedstarter','enabled'=>true],$alpha)->getStatusCode()===422,'Confirmed goalie new watch accepted');
verifyOwner(ownerRequest('POST','/notifications/goalie',['key'=>'BOS|alreadystarted','enabled'=>true],$alpha)->getStatusCode()===422,'Started game watch accepted');
verifyOwner(ownerRequest('POST','/notifications/goalie',['key'=>'CAR|confirmedstarter','enabled'=>false],$alpha)->getStatusCode()===200,'Hidden watch cannot be removed');
$response=ownerRequest('GET','/daily-targets?date='.$day,[],$alpha,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($response->getStatusCode()===301&&str_contains($response->headers->get('Location'),'/players?')&&str_contains($response->headers->get('Location'),'dfo_sort=1'),'Retired Daily Targets must redirect to Players with priority sorting.');
ownerRequest('GET','/notifications',[],$guest,['HTTP_ACCEPT'=>'text/html']);
verifyOwner(str_contains(view('layouts.app')->render(),'id="guest-signup-dialog"'),'Signed-out browsing needs a signup invitation');
verifyOwner(str_contains(view('layouts.app')->render(),'Already have an account?'),'Signup invitation must include sign-in');
$guestBell=view('account.goalie-bell',['goalie'=>['name'=>'Z Early','team'=>'TOR','starting_status'=>'Probable'],'date'=>$day])->render();
verifyOwner(str_contains($guestBell,'href="/login"'),'Guest bell lacks sign-in link');
verifyOwner(str_contains(view('account.goalie-bell',['goalie'=>['name'=>'Sam Goalie','team'=>'MTL'],'date'=>'2026-10-03'])->render(),'goalie-watch-bell'),'Tomorrow unknown status bell missing');
// Collector test replays the latest real scoring alert only to the requesting admin device.
Http::fake(['https://fcm.googleapis.com/*'=>Http::response('',201)]);
$testHeaders=['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'];
$testEndpoint='https://fcm.googleapis.com/fcm/send/admin-current';
$response=ownerRequest('POST','/push/subscribe',['endpoint'=>$testEndpoint],$admin);
verifyOwner($response->getStatusCode()===200,'Admin device setup failed');
$testToken=json_decode($response->getContent(),true)['feedToken'];
$adminOwner=User::where('email','dan@example.org')->first();
$push->subscribe('https://fcm.googleapis.com/fcm/send/admin-other',$adminOwner->id,'token-admin-other');
DB::table('push_notifications')->insert(['category'=>'live-score','title'=>'Earlier','body'=>'Earlier Player','fantasy_team_id'=>'a','url'=>'/teams/current?date='.$day]);
DB::table('push_notifications')->insert(['category'=>'live-score','title'=>'ECFHL Live Scoring','body'=>'Latest Skater now has 7 FPts.','fantasy_team_id'=>'b','url'=>'/teams/current?date='.$day]);
DB::table('push_notifications')->insert(['category'=>'goalie-status','title'=>'Newer goalie','body'=>'Not a scoring event']);
$testSnapshot=json_decode(DB::table('live_scoring_snapshots')->where('fantasy_date',$day)->value('payload'),true);
$testSnapshot['fantasy_date']=$day;$testSnapshot['teams']['b']=['name'=>'Beta'];
$testSnapshot['players'][]=['fantasy_team_id'=>'b','player_name'=>'Latest Skater','daily_fpts'=>7,'stats'=>['G'=>['value'=>2],'A'=>['value'=>3],'PPG'=>['value'=>1],'SHG'=>['value'=>1],'GWG'=>['value'=>1]]];
DB::table('live_scoring_snapshots')->where('fantasy_date',$day)->update(['payload'=>json_encode($testSnapshot)]);
verifyOwner(ownerRequest('POST','/job-status/test-scoring-notification',[],$guest,$testHeaders)->getStatusCode()===401,'Guest can send a test');
verifyOwner(ownerRequest('POST','/job-status/test-scoring-notification',[],$alpha,$testHeaders)->getStatusCode()===403,'Regular owner can send admin test');
verifyOwner(ownerRequest('POST','/job-status/test-scoring-notification',[],$admin)->getStatusCode()===403,'Non-AJAX test bypassed restriction');
foreach(['/job-status/test-scoring-notification','/job-status/test-goalie-notification'] as $testUrl){
 $response=ownerRequest('POST',$testUrl,[],$admin,$testHeaders);
 verifyOwner($response->getStatusCode()===200&&json_decode($response->getContent(),true)['ok'],'Scoring test failed: '.$response->getContent());
 $testAlert=DB::table('push_notifications')->orderByDesc('id')->first();
 verifyOwner($testAlert->category==='test-score'&&$testAlert->title==='ECFHL · Beta · TEST','Test selected goalie / prior test / older score');
 verifyOwner($testAlert->body==="Skater, Latest · 7 FPts\nG: 2 · A: 3 · PPG: 1 · SHG: 1 · GWG: 1",'Test omitted current stat totals / legacy team name');
 $delivery=DB::table('push_deliveries')->where('notification_id',$testAlert->id)->get();
 $deviceId=DB::table('push_subscriptions')->where('endpoint_hash',hash('sha256',$testEndpoint))->value('id');
 verifyOwner($delivery->count()===1&&(int)$delivery[0]->subscription_id===(int)$deviceId,'Test broadcast to other owners/devices');
}
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer '.$testToken]);
verifyOwner(count(json_decode($feed->getContent(),true)['notifications'])===2,'Device feed did not contain the scoring tests');
$testsBefore=DB::table('push_notifications')->where('category','test-score')->count();
Http::swap(new Illuminate\Http\Client\Factory);Http::preventStrayRequests();
Http::fake(['https://fcm.googleapis.com/*'=>Http::response('',410)]);
$response=ownerRequest('POST','/job-status/test-scoring-notification',[],$admin,$testHeaders);
verifyOwner($response->getStatusCode()===422&&DB::table('push_notifications')->where('category','test-score')->count()===$testsBefore,'Failed browser delivery reported success / left queued test');
verifyOwner(!DB::table('push_subscriptions')->where('endpoint_hash',hash('sha256',$testEndpoint))->exists(),'Expired test subscription retained');
DB::table('push_notifications')->where('category','live-score')->delete();
try{$push->testLatestScore($adminOwner->id,hash('sha256','https://fcm.googleapis.com/fcm/send/admin-other'));throw new RuntimeException('No scorer invented a test');}catch(Illuminate\Validation\ValidationException $e){verifyOwner(str_contains($e->getMessage(),'No scoring alert'),'Empty scoring history gave an unclear error');}
CarbonImmutable::setTestNow();

// Team administration exposes account details only to admins and unlinks atomically.
$adminTeams=ownerRequest('GET','/admin/teams',[],$admin,['HTTP_ACCEPT'=>'text/html']);
verifyOwner($adminTeams->getStatusCode()===200&&str_contains($adminTeams->getContent(),'Account linked')&&str_contains($adminTeams->getContent(),'a@example.org')&&str_contains($adminTeams->getContent(),'Change Image'),'Admin Teams must show linked accounts and image controls.');
verifyOwner(ownerRequest('GET','/admin/team-images',[],$admin)->headers->get('Location')==='http://localhost/admin/teams','Old Team Images URL must redirect to Teams.');
verifyOwner(ownerRequest('GET','/admin/teams',[],$alpha)->getStatusCode()===403,'An owner cannot inspect other linked accounts.');
verifyOwner(ownerRequest('POST','/admin/teams/a/unlink',['user_id'=>$a->id],$guest)->getStatusCode()===401,'Guests cannot unlink accounts.');
verifyOwner(ownerRequest('POST','/admin/teams/a/unlink',['user_id'=>$a->id],$alpha)->getStatusCode()===403,'Owners cannot unlink teams through administration.');
verifyOwner(ownerRequest('POST','/admin/teams/a/unlink',['user_id'=>$b->id],$admin)->getStatusCode()===409&&$a->fresh()->claim,'A stale admin page cannot unlink a different account.');
$betaPending=DB::table('push_deliveries')->whereIn('subscription_id',DB::table('push_subscriptions')->where('user_id',$b->id)->select('id'))->count();
$unlinked=ownerRequest('POST','/admin/teams/a/unlink',['user_id'=>$a->id],$admin);
verifyOwner($unlinked->getStatusCode()===302&&!$a->fresh()->claim&&User::whereKey($a->id)->exists(),'Unlinking must keep the account and remove only the team association.');
verifyOwner(DB::table('push_deliveries')->whereIn('subscription_id',DB::table('push_subscriptions')->where('user_id',$a->id)->select('id'))->count()===0,'Queued former-team notifications must be cleared.');
verifyOwner(DB::table('push_deliveries')->whereIn('subscription_id',DB::table('push_subscriptions')->where('user_id',$b->id)->select('id'))->count()===$betaPending,'Unlinking one account must not affect other owners.');
verifyOwner(ownerRequest('POST','/account/claim-team',['team_id'=>'a'],$alpha)->getStatusCode()===302&&$a->fresh()->claim,'A released team can be claimed again.');
// Renewing a device subscription must not expose the previous owner feed.
$device=DB::table('push_subscriptions')->where('endpoint_hash',hash('sha256','https://fcm.googleapis.com/fcm/send/alpha'))->first();
DB::table('push_deliveries')->insert(['subscription_id'=>$device->id,'notification_id'=>DB::table('push_notifications')->max('id')]);
$push->subscribe($device->endpoint,$b->id,'replacement-token');
verifyOwner(DB::table('push_deliveries')->where('subscription_id',$device->id)->count()===0,'Device renewal retained previous-account events.');
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer replacement-token']);
verifyOwner(json_decode($feed->getContent(),true)['notifications']===[],'A new owner can read old device events.');
$feed=ownerRequest('GET','/push/notifications',[],$guest,['HTTP_AUTHORIZATION'=>'Bearer token-alpha']);
verifyOwner(json_decode($feed->getContent(),true)['notifications']===[],'Old device token survived reassignment.');
echo "Owner account checks passed: pages, optional browsing, exclusive claims, reserved admin invitation, admin routes/actions, password hashing/login, Google state/linking, own/opponent scoring, goalie filters, date groups/custom projection ordering/start-time ties, confirmed/nonstarter/started-game exclusions, bell toggle/auth/preference preservation, waiver dates, own-goalie roster slots/current snapshot/toggle/delivery, isolated push delivery, latest-score test replay/legacy formatting/device isolation/failed delivery, and SSRF rejection.\n";
