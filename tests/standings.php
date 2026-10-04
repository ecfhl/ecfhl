<?php
// Standings must freeze live days, including the last day of a scoring period.
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});

use App\Support\FantraxStandings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function assertStanding(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
function standingsTable(string $range,float $away,float $home):array{
    $rows=[];
    for($i=0;$i<14;$i+=2)$rows[]=['cells'=>[
        ['teamId'=>'t'.$i,'content'=>'Team '.$i],['content'=>$away],
        ['teamId'=>'t'.($i+1),'content'=>'Team '.($i+1)],['content'=>$home],
    ]];
    return ['subCaption'=>$range,'rows'=>$rows];
}
$schedule=['tableList'=>[
    standingsTable('(Mon Sep 28, 2026 - Wed Sep 30, 2026)',10,20),
    standingsTable('(Thu Oct 1, 2026 - Sun Oct 4, 2026)',1000,2000),
    standingsTable('(Mon Oct 5, 2026 - Sun Oct 11, 2026)',5000,6000),
]];
function collectStandings(string $instant,array $states,bool $badNhl=false):array{
    global $schedule;
    CarbonImmutable::setTestNow(CarbonImmutable::parse($instant));
    Cache::flush();
    Http::fake(function($request)use($states,$badNhl,$schedule){
        if(str_contains($request->url(),'api-web.nhle.com')){
            $date=basename($request->url());
            if($badNhl)return Http::response(['games'=>[]]);
            $games=array_map(fn($s)=>['gameState'=>$s],$states[$date]??[]);
            return Http::response(['currentDate'=>$date,'games'=>$games]);
        }
        $message=$request->data()['msgs'][0];
        if($message['method']==='getStandings')return Http::response(['responses'=>[['data'=>$schedule]]]);
        $day=$message['data']['date'];
        $teams=[];$stats=[];
        for($i=0;$i<14;$i++){
            $id='t'.$i;$teams[]=['id'=>$id,'name'=>'Team '.$i];
            $stats[$id]=['ACTIVE'=>['totalFpts'=>(float)substr($day,-2),'pointsAdjustment'=>$i===0?-1:0]];
        }
        return Http::response(['responses'=>[['data'=>[
            'date'=>$day,'displayedSelections'=>['date'=>$day,'viewTypeId'=>'1','realOrStatProjProvider'=>['id'=>'-1']],
            'fantasyTeams'=>$teams,'statsPerTeam'=>['allTeamsStats'=>$stats],
        ]]]]);
    });
    return (new FantraxStandings)->fetch();
}
$yesterday=['2026-10-03'=>['OFF','FINAL']];
foreach([['FUT','FUT'],['FINAL','LIVE'],['FINAL','FUT'],['OFF','CRIT']] as $todayStates){
    $result=collectStandings('2026-10-04T17:00:00Z',$yesterday+['2026-10-04'=>$todayStates]);
    $teams=array_column($result['rows'],null,'team_id');
    assertStanding($result['completed_through']==='2026-10-03','Incomplete day entered standings');
    assertStanding($teams['t0']['l']===1&&$teams['t1']['w']===1,'Open period awarded W/L early');
    assertStanding($teams['t0']['fantasy_points_for']===13.0&&$teams['t1']['fantasy_points_for']===26.0,'Live FPts entered standings or daily adjustment lost');
}
$atlanticMidnight=collectStandings('2026-10-05T00:30:00Z',$yesterday+['2026-10-04'=>['LIVE','FUT']]);
assertStanding($atlanticMidnight['completed_through']==='2026-10-03','Atlantic midnight finalized the Pacific fantasy day');
$late=collectStandings('2026-10-05T07:01:00Z',$yesterday+['2026-10-04'=>['FINAL','LIVE'],'2026-10-05'=>['FUT']]);
assertStanding($late['completed_through']==='2026-10-03','Midnight finalized a game still running');
$final=collectStandings('2026-10-04T23:30:00Z',$yesterday+['2026-10-04'=>['FINAL','OFF']]);
$teams=array_column($final['rows'],null,'team_id');
assertStanding($final['completed_through']==='2026-10-04','Final game did not release same-day standings');
assertStanding($teams['t0']['l']===2&&$teams['t1']['w']===2&&$teams['t1']['standings_points']===4,'Finished scoring period did not award W/L/PTS');
assertStanding($teams['t0']['fantasy_points_for']===1010.0&&$teams['t1']['fantasy_points_for']===2020.0,'Final period scores or future period isolation failed');
$empty=collectStandings('2026-10-04T17:00:00Z',$yesterday);
assertStanding($empty['completed_through']==='2026-10-03','Empty current-day schedule finalized today');
try{collectStandings('2026-10-04T17:00:00Z',[],true);throw new LogicException('Invalid NHL response accepted');}catch(RuntimeException $e){assertStanding(str_contains($e->getMessage(),'Existing standings preserved'),'Wrong NHL failure');}
CarbonImmutable::setTestNow();
echo "Standings checks passed: live/future games, final-game release, completed daily FPts/adjustments, period W/L/PTS, future periods, Atlantic/Pacific midnight, late games, empty schedule, and invalid upstream data.\n";
