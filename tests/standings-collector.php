<?php
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE=:memory:');putenv('SESSION_DRIVER=array');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use App\Support\FantraxStandings;
use App\Support\FantraxSchedule;
function checkCollector($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach(glob(__DIR__.'/../database/migrations/*create*.php') as $f)(require $f)->up();
$cacheDirectory=sys_get_temp_dir().'/ecfhl-standings-'.bin2hex(random_bytes(8));
config(['performance.public_data_cache'=>true,'cache.stores.file.path'=>$cacheDirectory]);
Illuminate\Support\Facades\Cache::forgetDriver('file');
DB::table('seasons')->insert(['season_id'=>'2026-27','season_name'=>'2026-27','sequence'=>20,'format'=>'Head-to-Head']);
DB::table('source_cache')->insert(['source_key'=>'history','source_url'=>'https://example.com','payload'=>json_encode(['seasons'=>[['season'=>'2026-27','sequence'=>20,'format'=>'Head-to-Head']]])]);
$fake=new class extends FantraxStandings {
 public array $rows=[];public bool $fail=false;
 public function fetch():array{if($this->fail)throw new RuntimeException('Upstream unavailable; existing standings preserved.');return ['rows'=>$this->rows,'completed_through'=>'2026-10-03'];}
};
for($i=0;$i<14;$i++){
 DB::table('franchises')->insert(['franchise_id'=>'f'.$i,'franchise_name'=>'Team '.$i]);
 DB::table('team_seasons')->insert(['team_season_id'=>'ts'.$i,'season_id'=>'2026-27','franchise_id'=>'f'.$i,'original_name'=>'Team '.$i,'rank'=>$i+1,'fantasy_points_for'=>1]);
 $fake->rows[]=['team_id'=>'t'.$i,'team_name'=>'Team '.$i,'rank'=>14-$i,'w'=>2,'l'=>1,'t'=>0,'standings_points'=>4,'fantasy_points_for'=>100+$i,'fantasy_points_against'=>90];
}
$app->instance(FantraxStandings::class,$fake);
$app->instance(FantraxSchedule::class,new class extends FantraxSchedule{public function periods(bool $refresh=false):array{return [['caption'=>'Scoring Period 1','start'=>'2026-10-01','end'=>'2026-10-07','matchups'=>[['away_name'=>'Team 0','home_name'=>'Team 1','away_team_id'=>'t0','home_team_id'=>'t1','away_score'=>55,'home_score'=>42]]]];}});
\App\Support\CurrentTeams::standings();
\App\Support\PublicData::remember('joined-team-seasons',30,fn()=>['stale']);
checkCollector(Artisan::call('ecfhl:refresh-current-standings')===0,'Standings collector failed');
checkCollector(collect(\App\Support\CurrentTeams::standings())->firstWhere('team','Team 0')['fantasy_points_for']==100,'Collector must invalidate the current team cache immediately.');
checkCollector(\App\Support\PublicData::remember('joined-team-seasons',30,fn()=>['fresh'])===['fresh'],'Collector must invalidate joined historical standings as well.');
checkCollector(DB::table('team_seasons')->where('team_season_id','ts0')->value('fantasy_points_for')==100,'Collector did not publish standings');
$status=DB::table('collector_job_statuses')->where('job_key','standings')->first();
checkCollector($status->status==='success'&&str_contains($status->message,'2026-10-03'),'Scheduled collector must publish success and completion date');
checkCollector(DB::table('job_run_history')->value('target_date')==='2026-10-03','Collector history must identify the finalized day');
$before=DB::table('team_seasons')->orderBy('team_season_id')->get()->toJson();
$fake->fail=true;checkCollector(Artisan::call('ecfhl:refresh-current-standings')===1,'Upstream failure must return failure');
checkCollector(DB::table('team_seasons')->orderBy('team_season_id')->get()->toJson()===$before&&DB::table('job_run_history')->count()===1,'Failed collection must preserve standings and last successful update');
checkCollector(DB::table('collector_job_statuses')->where('job_key','standings')->value('status')==='failed','Scheduled failure outcome missing');
$fake->fail=false;$saved=$fake->rows;array_pop($fake->rows);checkCollector(Artisan::call('ecfhl:refresh-current-standings')===1,'Incomplete team matching must fail');
checkCollector(DB::table('team_seasons')->orderBy('team_season_id')->get()->toJson()===$before,'Incomplete collection overwrote standings');
$fake->rows=$saved;checkCollector(Artisan::call('ecfhl:refresh-current-standings')===0,'Failure did not release the collector lock');
$lock=fopen(storage_path('app/standings.lock'),'c');flock($lock,LOCK_EX);
checkCollector(Artisan::call('ecfhl:refresh-current-standings')===1&&str_contains(Artisan::output(),'already running'),'Manual and scheduled collection must not overlap');
flock($lock,LOCK_UN);fclose($lock);
$events=$app->make(Illuminate\Console\Scheduling\Schedule::class)->events();
$event=collect($events)->first(fn($e)=>str_contains((string)$e->command,'ecfhl:refresh-current-standings'));
checkCollector($event&&$event->expression==='* * * * *'&&$event->runInBackground&&$event->withoutOverlapping,'Standings must use the live-scoring scheduler cadence with overlap protection');
foreach([['2026-10-04T17:00:00Z',true],['2026-10-04T17:15:00Z',false]] as [$instant,$expected]){
 \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse($instant));checkCollector($event->filtersPass($app)===$expected,'Standings scheduler did not share idle live-scoring cadence');
}
$app->make(\App\Support\LiveScoring\SnapshotRepository::class)->publish(['fantasy_date'=>'2026-10-04','source_date'=>'2026-10-04','players'=>[['game_status'=>'2']]],[],\Carbon\CarbonImmutable::now('UTC'));
foreach([['2026-10-04T17:16:00Z',true],['2026-10-04T17:17:00Z',true]] as [$instant,$expected]){
 \Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse($instant));checkCollector($event->filtersPass($app)===$expected,'Standings scheduler did not share live one-minute cadence');
}
\Carbon\CarbonImmutable::setTestNow();
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$request=Illuminate\Http\Request::create('/standings');$response=$kernel->handle($request);$kernel->terminate($request,$response);
checkCollector($response->getStatusCode()===200&&str_contains($response->getContent(),'/standings.js?v=1')&&str_contains($response->getContent(),'Data refreshes every minute during games and hourly when idle'),'Standings page must load automatic refresh: '.$response->getStatusCode().' '.($response->headers->get('Location')??substr(strip_tags($response->getContent()),0,160)));
checkCollector(str_contains($response->getContent(),'Scoring Period 1')&&str_contains($response->getContent(),'data-period-number="1"'),'Standings must read the matchup table written by the collector.');
foreach(['h2h','total','none'] as $type){
 $app->forgetScopedInstances();$request=Illuminate\Http\Request::create('/standings?type='.$type,'GET',[],[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);$partial=$kernel->handle($request);$kernel->terminate($request,$partial);
 checkCollector($partial->getStatusCode()===200&&str_contains($partial->getContent(),'class="shell standings-page"')&&str_contains($partial->getContent(),'Scoring Period 1')&&!str_contains($partial->getContent(),'<html'),'Standings refresh must be a lightweight fragment independent of historical filters.');
}
$app->forgetScopedInstances();$request=Illuminate\Http\Request::create('/teams/league');$directory=$kernel->handle($request);$kernel->terminate($request,$directory);
checkCollector($directory->getStatusCode()===200&&substr_count($directory->getContent(),'data-team-row ')===14&&substr_count($directory->getContent(),'class="league-team-logo"')===14,'Teams directory needs 14 compact rows with separate logo viewers.');
checkCollector(str_contains($directory->getContent(),'href="/teams/current/team-0"')&&str_contains($directory->getContent(),'league-teams.css?v=')&&str_contains($directory->getContent(),'league-teams.js?v=')&&str_contains($directory->getContent(),'W–L–T'),'Rows must display records, link to rosters and load fingerprinted directory assets.');
if($output=getenv('ECFHL_TEAMS_QA_HTML'))file_put_contents($output,$directory->getContent());
$owner=new \App\Models\User();$owner->setRelation('claim',new \App\Models\TeamClaim(['team_name'=>'Team 0']));
\Illuminate\Support\Facades\Auth::setUser($owner);
$ownedHtml=view('teams.league',['teams'=>\App\Support\CurrentTeams::standings()])->render();
$doc=new DOMDocument();@$doc->loadHTML($ownedHtml);$xpath=new DOMXPath($doc);
checkCollector($xpath->query('//tbody[@data-teams-rows]/tr[1]')->item(0)->getAttribute('data-team-mine')==='1','Owned team must be first even when its rank is last.');
checkCollector($xpath->query('//tr[contains(@class,"league-team-row-mine")]')->length===1&&str_contains($ownedHtml,'My team first'),'Highlight exactly the claimed team and offer owned-team sorting.');
if($output=getenv('ECFHL_TEAMS_OWNER_QA_HTML'))file_put_contents($output,$ownedHtml);
\Illuminate\Support\Facades\Auth::forgetGuards();
checkCollector(!str_contains($response->getContent(),'/job-status#collector-standings'),'Collector link must respect admin access');
$admin=new \App\Models\User;$admin->forceFill(['is_admin'=>true]);$admin->setRelation('claim',null);\Illuminate\Support\Facades\Auth::guard()->setUser($admin);$app->forgetScopedInstances();
$request=Illuminate\Http\Request::create('/standings');$response=$kernel->handle($request);$kernel->terminate($request,$response);
checkCollector($response->getStatusCode()===200&&str_contains($response->getContent(),'/job-status#collector-standings'),'Administrator needs direct access to the standings collector card');
// Reproduce the production mismatch end-to-end with the actual official response.
$official=(new FantraxStandings)->parse(json_decode(file_get_contents(__DIR__.'/fixtures/fantrax-regular-standings.json'),true));
foreach($official as $index=>$row)DB::table('team_seasons')->where('team_season_id','ts'.$index)->update(['original_name'=>$row['team_name'],'rank'=>14-$index,'fantasy_points_for'=>1]);
$app->instance(FantraxStandings::class,new class($official) extends FantraxStandings{
 public function __construct(private array $rows){}
 public function fetch():array{return ['rows'=>$this->rows,'as_of_date'=>'2026-10-04'];}
});
checkCollector(Artisan::call('ecfhl:refresh-current-standings')===0,'Official regular-season response failed publication.');
checkCollector(DB::table('job_run_history')->orderByDesc('id')->value('target_date')==='2026-10-04','Collector history must record the actual collected fantasy day.');
$published=\App\Support\CurrentTeams::standings();
foreach($official as $index=>$row){
 checkCollector($published[$index]['team']===$row['team_name']&&(int)$published[$index]['rank']===$row['rank']&&(float)$published[$index]['fantasy_points_for']===$row['fantasy_points_for'],'Official collector output was replaced by historical/calculated/cached standings.');
}
$app->forgetScopedInstances();$request=Illuminate\Http\Request::create('/standings','GET',[],[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);$response=$kernel->handle($request);$kernel->terminate($request,$response);
$dom=new DOMDocument;@$dom->loadHTML('<?xml encoding="UTF-8">'.$response->getContent());$xpath=new DOMXPath($dom);
$displayed=$xpath->query('//table/tbody/tr');
checkCollector($response->getStatusCode()===200&&$displayed->length===14,'Official standings fragment did not render all 14 teams.');
foreach($official as $index=>$row){
 $cells=$displayed->item($index)->getElementsByTagName('td');
 checkCollector(trim($cells->item(0)->textContent)===(string)$row['rank']&&str_contains($cells->item(1)->textContent,$row['team_name'])&&trim($cells->item(6)->textContent)===number_format($row['fantasy_points_for'],0),'Rendered standings must exactly match official collector rank/team/FPts.');
}
(new Illuminate\Filesystem\Filesystem)->deleteDirectory($cacheDirectory);
echo "Standings collector checks passed: real command publication/outcomes/history, failure preservation, locks, shared cadence and auto-refresh page.\n";
