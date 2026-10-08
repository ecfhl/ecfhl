<?php
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE=:memory:');putenv('SESSION_DRIVER=array');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';$app=require __DIR__.'/../bootstrap/app.php';
$console=$app->make(Illuminate\Contracts\Console\Kernel::class);$console->bootstrap();$console->rerouteSymfonyCommandEvents();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
use App\Support\CollectorSchedule;
use App\Support\CollectorStatus;
use App\Support\FantasyDay;
use Carbon\CarbonImmutable as Clock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
function verifyCollector($ok,$message){if(!$ok)throw new RuntimeException($message);}
foreach(glob(__DIR__.'/../database/migrations/*create*.php') as $f)(require $f)->up();
Http::preventStrayRequests();
function windowAt(string $instant,array $games=[],bool $clear=true):CollectorSchedule {
 Clock::setTestNow(Clock::parse($instant));if($clear)Cache::flush();
 Http::swap(new \Illuminate\Http\Client\Factory);Http::preventStrayRequests();
 Http::fake(function($request)use($games){$date=basename($request->url());return Http::response(['currentDate'=>$date,'games'=>$games[$date]??[]]);});return new CollectorSchedule;
}
function nhlGame(string $start,string $state='FUT'):array{return ['startTimeUTC'=>$start,'gameState'=>$state];}
$schedule=windowAt('2026-10-08T10:15:00Z');
verifyCollector(!$schedule->due('players')&&!$schedule->due('goalies')&&!$schedule->due('standings')&&!$schedule->due('odds'),'No-game overnight polling must be reduced');
$schedule=windowAt('2026-10-08T10:00:00Z');
verifyCollector($schedule->due('players')&&$schedule->due('goalies')&&$schedule->due('advisor'),'Three-hour idle checks missing');
$games=['2026-10-08'=>[nhlGame('2026-10-08T23:00:00Z')]];
$schedule=windowAt('2026-10-08T17:05:00Z',$games);
verifyCollector($schedule->due('goalies')&&!$schedule->due('standings')&&$schedule->due('odds'),'Six-hour pregame window missing');
$schedule=windowAt('2026-10-08T23:01:00Z',$games);
verifyCollector($schedule->due('scores')&&!$schedule->due('standings')&&!$schedule->due('odds'),'Scheduled puck drop must enable minute scoring before live state arrives');
$schedule=windowAt('2026-10-08T23:05:00Z',$games);verifyCollector($schedule->due('standings'),'Five-minute live standings missing');
$games['2026-10-08'][0]['gameState']='LIVE';$schedule=windowAt('2026-10-09T08:01:00Z',$games);
verifyCollector($schedule->due('scores'),'Overnight/long overtime must remain live across Pacific midnight');
$games['2026-10-08'][0]['gameState']='OFF';$schedule=windowAt('2026-10-09T05:00:00Z',$games);
verifyCollector($schedule->due('standings')&&!$schedule->due('odds'),'Final reconciliation missing or postgame odds polling');
DB::table('collector_job_statuses')->insert(['job_key'=>'standings','status'=>'success','ran_at'=>now(),'message'=>'Completed']);
$schedule=windowAt('2026-10-09T05:05:00Z',$games,false);verifyCollector(!$schedule->due('standings'),'Final reconciliation must stop after success');
DB::table('collector_job_statuses')->where('job_key','standings')->update(['status'=>'failed']);verifyCollector($schedule->due('standings'),'Failed reconciliation must retry');
$postponed=nhlGame('2026-10-08T23:00:00Z');$postponed['gameScheduleState']='PPD';
$schedule=windowAt('2026-10-08T23:05:00Z',['2026-10-08'=>[$postponed]]);verifyCollector(!$schedule->due('scores')&&!$schedule->due('odds'),'Postponement kept a window open');
Cache::flush();Http::swap(new \Illuminate\Http\Client\Factory);Http::fake(fn()=>Http::response([],503));$schedule=new CollectorSchedule;
verifyCollector($schedule->due('scores')&&!$schedule->due('odds'),'NHL outage must preserve scoring and suppress paid odds polling');
// Historical DST transition: future Pacific offset rules can differ between
// system timezone databases, while FantasyDay must keep using Vancouver rules.
foreach(['2025-11-02T06:59:00Z'=>'2025-11-01','2025-11-02T07:00:00Z'=>'2025-11-02','2025-11-03T07:59:00Z'=>'2025-11-02','2025-11-03T08:00:00Z'=>'2025-11-03'] as $time=>$day){Clock::setTestNow(Clock::parse($time));verifyCollector((new FantasyDay)->today()->toDateString()===$day,'DST fantasy date mismatch');}
Clock::setTestNow(Clock::parse('2026-10-08T17:00:00Z'));CollectorStatus::start('players');CollectorStatus::advance('players');$state=CollectorStatus::snapshot()['players'];
verifyCollector($state['status']==='running'&&$state['completed']===1&&$state['total']===2,'Progress must count real units');CollectorStatus::finish('players',1);
verifyCollector(CollectorStatus::snapshot()['players']['status']==='failed','Failure missing');CollectorStatus::start('players');Clock::setTestNow(Clock::parse('2026-10-08T20:00:00Z'));
verifyCollector(CollectorStatus::snapshot()['players']['status']==='interrupted','Killed worker remains running');
$app->instance(App\Support\FantraxAvailablePlayers::class,new class extends App\Support\FantraxAvailablePlayers {
 public function fetch(Clock $date,string $position='ALL'):array{return ['rows'=>[]];}
});
verifyCollector(Artisan::call('ecfhl:refresh-daily-players')===0,'Fake daily-player collection failed');$state=CollectorStatus::snapshot()['players'];
verifyCollector($state['status']==='success'&&$state['completed']===2,'Real command events must finish progress');
// Available goalies depend on both sources. DFO publication must immediately
// refresh the joined status without waiting for another Fantrax player run.
$day=(new FantasyDay)->today()->toDateString();
DB::table('active_daily_players')->insert(['game_date'=>$day,'player_name'=>'Test Goalie','team'=>'EDM','position'=>'G','availability'=>'FA']);
$app->instance(App\Support\DailyFaceoffStartingGoalies::class,new class extends App\Support\DailyFaceoffStartingGoalies {
 public function fetch(Clock $date):array{return ['url'=>'https://example.test','source'=>'test','rows'=>[['team_name'=>'Edmonton Oilers','opponent_name'=>'Vancouver Canucks','player_name'=>'Test Goalie','starting_status'=>'Confirmed','home_away'=>'HOME','source_updated_at'=>null]]];}
});
verifyCollector(Artisan::call('ecfhl:refresh-starting-goalies')===0,'DFO dependency collection failed');
verifyCollector(DB::table('active_available_goalies')->where('game_date',$day)->value('starting_status')==='Confirmed','DFO changes must rebuild available goalies');
DB::statement("CREATE TRIGGER fail_rebuild BEFORE INSERT ON active_available_goalies BEGIN SELECT RAISE(ABORT, 'test rebuild failure'); END");
verifyCollector(Artisan::call('ecfhl:refresh-starting-goalies')===1,'DFO must propagate dependency failures');
DB::statement('DROP TRIGGER fail_rebuild');
$rosters=new class extends App\Support\FantraxTeamRosters {
 public array $dates=[];
 public function fetch(Clock $date):array{$this->dates[]=$date->toDateString();return ['rows'=>[]];}
};
$app->instance(App\Support\FantraxTeamRosters::class,$rosters);
$app->instance(App\Support\FantraxDailyMoves::class,new class extends App\Support\FantraxDailyMoves {
 public function fetch(Clock $date,?Clock $periodStart=null):array{return [];}
});
$window=new class extends CollectorSchedule {
 public bool $final=false;
 public function window():array{return ['known'=>true,'todayFinished'=>$this->final];}
};
$app->instance(CollectorSchedule::class,$window);
verifyCollector(Artisan::call('ecfhl:refresh-fantasy-rosters')===0&&count($rosters->dates)===2,'Live games must keep current-day rosters refreshable');
DB::table('active_fantasy_rosters')->insert(['game_date'=>$day,'fantasy_team_id'=>'t','fantasy_team_name'=>'Team','player_id'=>'p','player_name'=>'Saved Player']);
$window->final=true;$rosters->dates=[];
verifyCollector(Artisan::call('ecfhl:refresh-fantasy-rosters')===0&&count($rosters->dates)===1&&DB::table('active_fantasy_rosters')->where('game_date',$day)->count()===1,'Final games must freeze today and still fetch tomorrow');
verifyCollector(!DB::table('collector_job_statuses')->where('job_key','advisor')->exists(),'Roster refresh must not trigger redundant advice');
$events=$app->make(Illuminate\Console\Scheduling\Schedule::class)->events();
foreach(['refresh-pp-lines'=>'0 */3 * * *','refresh-odds'=>'0 */4 * * *','refresh-player-birthdates'=>'50 3 * * 1','refresh-player-projections'=>'0 4 * * *','refresh-scoring-period-matchups'=>'0 8 * * 1'] as $command=>$cron){$event=collect($events)->first(fn($event)=>str_contains((string)$event->command,'ecfhl:'.$command));verifyCollector($event&&$event->expression===$cron&&$event->withoutOverlapping,'Cadence changed: '.$command);}
verifyCollector(!collect($events)->contains(fn($event)=>str_contains((string)$event->command,'refresh-available-goalies')),'Hourly rebuild remains');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);$request=Illuminate\Http\Request::create('/job-status/state','GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);$response=$kernel->handle($request);$kernel->terminate($request,$response);
verifyCollector($response->getStatusCode()===401,'State endpoint must require admin sign-in');
$user=new App\Models\User;$user->forceFill(['is_admin'=>true]);$user->setRelation('claim',null);Illuminate\Support\Facades\Auth::guard()->setUser($user);
$request=Illuminate\Http\Request::create('/job-status');$response=$kernel->handle($request);$kernel->terminate($request,$response);
verifyCollector($response->getStatusCode()===200&&substr_count($response->getContent(),'<progress ')===11&&str_contains($response->getContent(),'collector-status.js?v='),'Page must render 11 jobs and accessible progress');
if($path=getenv('ECFHL_COLLECTOR_QA_HTML'))file_put_contents($path,$response->getContent());Clock::setTestNow();
echo "Collector checks passed: game windows, live cadence, overnight/DST, final retries, outages, progress, admin access and fixed cadences.\n";
