<?php

putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x', 32)));
putenv('THE_ODDS_API_KEY=test-only');
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1); });

use App\Support\NhlOdds;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function oddsCheck(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
foreach (['2026_09_30_000006_create_todays_odds_table.php','2026_10_07_180000_add_commence_at_to_todays_odds.php'] as $file) (require __DIR__.'/../database/migrations/'.$file)->up();
CarbonImmutable::setTestNow('2026-10-07T23:00:00Z');
$game = ['home_team'=>'St Louis Blues','away_team'=>'Toronto Maple Leafs','commence_time'=>'2026-10-08T00:00:00Z',
    'bookmakers'=>array_map(fn($price)=>['last_update'=>'2026-10-07T22:55:00Z','markets'=>[['key'=>'h2h','outcomes'=>[
        ['name'=>'St. Louis Blues','price'=>$price], ['name'=>'Toronto Maple Leafs','price'=>150],
    ]]]], [-160,-140])];
$live = array_replace($game, ['home_team'=>'Boston Bruins','away_team'=>'New York Rangers','commence_time'=>'2026-10-07T22:00:00Z']);
Http::preventStrayRequests();
$requestFrom=null;
Http::fake(['api.the-odds-api.com/*'=>function($request) use($game,$live,&$requestFrom){
    $requestFrom=$request['commenceTimeFrom'];
    return Http::response([$game,$live],200);
}]);
$odds = new NhlOdds;
$data = $odds->fetch();
oddsCheck(count($data['rows'])===2 && $data['rows'][0]['team']==='STL' && $data['rows'][0]['american_odds']===-150, 'Only pregame prices, with punctuation aliases and bookmaker median, may be collected.');
oddsCheck($data['rows'][0]['game_date']==='2026-10-07', 'Odds must match the Pacific fantasy date across UTC midnight.');
oddsCheck($requestFrom==='2026-10-07T23:00:00Z','The odds request must exclude games that already started.');
oddsCheck($odds->publish($data,['2026-10-07','2026-10-08'])['2026-10-07']===2, 'Pregame odds must publish both sides.');
CarbonImmutable::setTestNow('2026-10-07T23:50:00Z');
$data['rows'][0]['american_odds']=-180;
$odds->publish($data,['2026-10-07','2026-10-08']);
$saved=DB::table('todays_odds')->where('team','STL')->first();
oddsCheck($saved->american_odds===-180 && $saved->checked_at==='2026-10-07 23:50:00','Pregame updates must remain enabled.');
CarbonImmutable::setTestNow('2026-10-08T00:00:00Z');
$data['rows'][0]['american_odds']=220;
oddsCheck(array_sum($odds->publish($data,['2026-10-07','2026-10-08']))===0, 'A response arriving at puck drop must not publish live odds.');
$after=DB::table('todays_odds')->where('team','STL')->first();
oddsCheck($after->american_odds===-180 && $after->checked_at===$saved->checked_at && $after->updated_at===$saved->updated_at,'Started games must freeze both price and collection timestamps.');
$delayed=$data;
foreach($delayed['rows'] as &$row) $row['commence_at']='2026-10-08 01:00:00';
unset($row);
oddsCheck(array_sum($odds->publish($delayed,['2026-10-07','2026-10-08']))===0,'A changed provider start time must not unfreeze a game already locked at puck drop.');
$empty=['rows'=>[],'source_updated_at'=>null];
$odds->publish($empty,['2026-10-07','2026-10-08']);
oddsCheck(DB::table('todays_odds')->count()===2,'Finished games disappearing from the feed must retain their last pregame odds.');
oddsCheck($odds->fetch()['rows']===[],'Started games must be excluded defensively even if the provider ignores the time filter.');
CarbonImmutable::setTestNow('2026-10-08T18:00:00Z');
foreach($data['rows'] as &$row){$row['game_date']='2026-10-08';$row['commence_at']='2026-10-09 00:00:00';}
unset($row);
oddsCheck($odds->publish($data,['2026-10-08','2026-10-09'])['2026-10-08']===2 && DB::table('todays_odds')->count()===4,'The next fantasy day must collect fresh odds without overwriting yesterday.');
Http::swap(new Illuminate\Http\Client\Factory);
Http::preventStrayRequests();
Http::fake(['api.the-odds-api.com/*'=>Http::response(['message'=>'Unavailable'],503)]);
try { $odds->fetch(); throw new LogicException('Expected failed fetch'); } catch (Illuminate\Http\Client\RequestException $e) {}
oddsCheck(DB::table('todays_odds')->count()===4,'A failed fetch must preserve all saved odds.');
CarbonImmutable::setTestNow();
echo "Odds checks passed: pregame-only requests, aliases, median, Pacific dates, pregame updates, puck-drop freeze, retained timestamps, disappearing games, next-day resume and failure preservation.\n";
