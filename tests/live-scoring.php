<?php

// Disposable SQLite and real, sanitized Fantrax response fixtures. No network or production writes.
putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n".$e->getTraceAsString()."\n"); exit(1); });

use App\Support\FantasyDay;
use App\Support\LiveScoring\FantraxClient;
use App\Support\LiveScoring\RefreshLiveScoring;
use App\Support\LiveScoring\SnapshotBuilder;
use App\Support\LiveScoring\SnapshotRepository;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

function checkLive(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
function liveFixture(string $date): array { return json_decode(gzdecode(file_get_contents(__DIR__.'/fixtures/live-scoring/'.$date.'.json.gz')), true, 512, JSON_THROW_ON_ERROR); }
config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
foreach (glob(__DIR__.'/../database/migrations/*create*.php') as $file) (require $file)->up();
(require __DIR__.'/../database/migrations/2026_10_01_235500_add_fantasy_team_to_push_notifications.php')->up();
(require __DIR__.'/../database/migrations/2026_10_02_010000_add_fantasy_team_to_push_subscriptions.php')->up();

$days = new FantasyDay;
$expected = ['yesterday'=>'2026-10-01','today'=>'2026-10-02','tomorrow'=>'2026-10-03'];
checkLive($days->dates(CarbonImmutable::parse('2026-10-03 00:30','America/Halifax')) === $expected, 'Atlantic midnight shifted the fantasy day');
checkLive($days->dates(CarbonImmutable::parse('2026-10-03T06:59:59Z')) === $expected, 'Day rolled over before Pacific midnight');
checkLive($days->dates(CarbonImmutable::parse('2026-10-03T07:00:00Z')) === ['yesterday'=>'2026-10-02','today'=>'2026-10-03','tomorrow'=>'2026-10-04'], 'Pacific midnight did not roll over');
checkLive($days->today(CarbonImmutable::parse('2026-11-01T06:30:00Z'))->toDateString() === '2026-10-31', 'DST day boundary failed');
try { $days->parse('2026-02-30'); throw new RuntimeException('Invalid date accepted'); } catch (InvalidArgumentException) {}

$builder = new SnapshotBuilder;
$repository = new SnapshotRepository;
$snapshots = [];
foreach ($expected as $date) {
    $source = liveFixture($date);
    $snapshot = $builder->build($date, $source['day'], $source['period'], $source['details']);
    $snapshots[$date] = $snapshot;
    checkLive(count($snapshot['matchups']) === 7 && count($snapshot['teams']) === 14, 'Incomplete matchups for '.$date);
    $repository->publish($snapshot, $source, CarbonImmutable::parse('2026-10-03T04:30:00Z'));
    foreach ($snapshot['teams'] as $id=>$team) {
        $players = array_filter($snapshot['players'], fn($p)=>$p['fantasy_team_id'] === $id && $p['scoring_status'] === 'ACTIVE');
        checkLive(abs(array_sum(array_column($players,'daily_fpts')) - $team['daily_fpts']) < 0.02, 'Bench/minor points entered team total');
        $raw = $source['day']['statsPerTeam']['allTeamsStats'][$id]['ACTIVE'];
        checkLive($team['daily_fpts'] === (float)$raw['totalFpts'], 'Daily total changed');
        checkLive($team['period_fpts'] === (float)$source['period']['statsPerTeam']['allTeamsStats'][$id]['ACTIVE']['totalFpts'] + (float)($source['period']['statsPerTeam']['allTeamsStats'][$id]['ACTIVE']['pointsAdjustment'] ?? 0), 'Period total changed');
        $projectedTotal = 0;
        foreach ($players as $player) {
            checkLive($player['fantasy_date'] === $date && $player['source_date'] === $date, 'Player date changed');
            checkLive($player['daily_fpts'] === (float)($raw['statsMap'][$player['player_id']]['object1'] ?? 0), 'Player points changed');
            checkLive($player['game_id'] !== '', 'Event identity lost');
            checkLive($player['opponent'] !== null, 'Dated opponent missing');
            $pid = $player['player_id'];
            $original = $raw['projectedTotalsMap'][$pid];
            $calculated = $raw['calculatedProjectedTotalsMap'][$pid] ?? $original;
            $finished = !empty($source['day']['allEventsFinished']) || ($raw['remainingEventPercent'][$pid] ?? null) === 0 || ($raw['remainingEventPercent'][$pid] ?? null) === 0.0;
            checkLive($player['daily_projected_fpts'] === ($finished ? $original : $calculated), 'Player daily projection changed');
            $projectedTotal += $calculated;
            $game = explode('|',$raw['gameStatusMap'][$pid]);
            checkLive($player['game_id'] === $game[1] && $player['game_status'] === $game[2], 'Game status changed');
        }
        checkLive($team['daily_projected_fpts'] === round($projectedTotal,2), 'Team daily projection changed');
    }
}
$lone = array_values(array_filter($snapshots['2026-10-02']['players'], fn($p)=>$p['fantasy_team_id']==='65yfc2nwmolvao6q' && $p['scoring_status']==='ACTIVE'));
$byId = array_column($lone, null, 'player_id');
checkLive(count($lone) === 4, 'Lone Tsar daily lineup does not match Fantrax');
checkLive($byId['05y3a']['daily_fpts'] === 2.0 && $byId['03wpi']['daily_fpts'] === 2.0, 'Carlsson/Dubois regression');
checkLive(isset($byId['03924']) && $byId['03rf2']['daily_fpts'] === 0.0, 'Zero-point active player disappeared');
checkLive($byId['05y3a']['gp'] === 1, 'Carlsson GP missing');
checkLive($byId['05y3a']['opponent'] === 'VGK' && $byId['03wpi']['opponent'] === 'CAR' && $byId['03924']['opponent'] === 'ANA' && $byId['03rf2']['opponent'] === 'STL', 'Final/in-progress opponents were not parsed');
$tomorrow = array_filter($snapshots['2026-10-03']['players'], fn($p)=>$p['fantasy_team_id']==='65yfc2nwmolvao6q' && $p['scoring_status']==='ACTIVE');
checkLive(count($tomorrow) === 13, 'Tomorrow was not populated before games started');
checkLive(!in_array('05y3a', array_column($tomorrow,'player_id'),true), 'Tomorrow copied today');
checkLive($snapshots['2026-10-03']['teams']['65yfc2nwmolvao6q']['daily_projected_fpts'] > 0, 'Tomorrow projections missing');

// Fantrax may omit the original estimate while tomorrow's calculated estimate exists.
$source = liveFixture('2026-10-03');
$active = collect($snapshots['2026-10-03']['players'])->firstWhere('scoring_status', 'ACTIVE');
$tid = $active['fantasy_team_id']; $pid = $active['player_id'];
$source['day']['statsPerTeam']['allTeamsStats'][$tid]['ACTIVE']['calculatedProjectedTotalsMap'][$pid] = 1.43;
unset($source['day']['statsPerTeam']['allTeamsStats'][$tid]['ACTIVE']['projectedTotalsMap'][$pid]);
$withoutOriginal = $builder->build('2026-10-03', $source['day'], $source['period'], $source['details']);
$optional = collect($withoutOriginal['players'])->first(fn($p)=>$p['fantasy_team_id']===$tid && $p['player_id']===$pid);
checkLive($optional['daily_projection_original']===null && $optional['daily_projection_calculated']===1.43 && $optional['daily_projected_fpts']===1.43, 'Missing original estimate blocked a valid calculated estimate.');
checkLive(count($withoutOriginal['players'])===count($snapshots['2026-10-03']['players']) && $withoutOriginal['teams'][$tid]['daily_fpts']===$snapshots['2026-10-03']['teams'][$tid]['daily_fpts'], 'Missing estimates changed lineup membership or actual points.');
unset($source['day']['statsPerTeam']['allTeamsStats'][$tid]['ACTIVE']['calculatedProjectedTotalsMap'][$pid]);
$withoutBoth = $builder->build('2026-10-03', $source['day'], $source['period'], $source['details']);
$optional = collect($withoutBoth['players'])->first(fn($p)=>$p['fantasy_team_id']===$tid && $p['player_id']===$pid);
checkLive($optional['daily_projected_fpts']===null && $withoutBoth['teams'][$tid]['daily_projected_fpts']===null, 'Unavailable source estimates must stay null instead of publishing a false zero or partial team projection.');
$source['day']['statsPerTeam']['allTeamsStats'][$tid]['ACTIVE']['projectedTotalsMap'][$pid] = 0;
$withZero = $builder->build('2026-10-03', $source['day'], $source['period'], $source['details']);
checkLive(collect($withZero['players'])->first(fn($p)=>$p['fantasy_team_id']===$tid && $p['player_id']===$pid)['daily_projected_fpts']===0, 'A genuine zero estimate must remain zero.');

// Injury icons must not change Fantrax ACTIVE membership or team scoring.
$source = liveFixture('2026-10-02');
$source['day']['scorerMap']['ACTIVE']['65yfc2nwmolvao6q']['2010'][0]['scorer']['icons'][] = ['typeId'=>'2'];
$injured = $builder->build('2026-10-02',$source['day'],$source['period'],$source['details']);
checkLive($injured['teams']['65yfc2nwmolvao6q']['daily_fpts'] === $snapshots['2026-10-02']['teams']['65yfc2nwmolvao6q']['daily_fpts'], 'Injury flag removed ACTIVE scoring');
foreach ($injured['players'] as $p) if ($p['scoring_status']==='ACTIVE') checkLive($p['roster_status']==='ACTIVE', 'Badge or supplemental roster status overrode Fantrax ACTIVE lineup');

Http::preventStrayRequests();
$badDate = true;
$missingOriginal = false;
Http::fake(function ($request) use (&$badDate, &$missingOriginal) {
    $message = $request->data()['msgs'][0];
    $date = $message['data']['date'] ?? $message['data']['startDate'];
    $fixture = liveFixture($date);
    if ($message['method'] === 'getLiveScoringStats') {
        $data = $fixture[$message['data']['viewType']==='1'?'day':'period'];
        if ($missingOriginal && $date==='2026-10-03' && $message['data']['viewType']==='1') {
            foreach ($data['statsPerTeam']['allTeamsStats'] as &$team) { $team['ACTIVE']['projectedTotalsMap'] = []; }
            unset($team);
        }
        if ($badDate && $date==='2026-10-02') $data['date'] = '2026-10-03';
    } else {
        $key = $message['data']['positionOrGroup'].':1';
        if (isset($message['data']['searchName'])) {
            foreach ($fixture['details'] as $candidateKey=>$candidate) {
                foreach ($candidate['statsTable'] as $entry) {
                    if ($entry['scorer']['name'] === $message['data']['searchName']) $key = $candidateKey;
                }
            }
        }
        $data = $fixture['details'][$key];
    }
    return Http::response(['responses'=>[['data'=>$data]]],200);
});
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03 00:30','America/Halifax'));
$before = $repository->get('2026-10-02');
$messages = [];
$refresh = new RefreshLiveScoring(new FantraxClient,$repository,$builder);
checkLive(!$refresh->refresh('2026-10-02', function($m)use(&$messages){$messages[]=$m;}), 'Wrong-date response accepted');
checkLive($repository->get('2026-10-02') === $before, 'Failed date destroyed previous valid snapshot');
checkLive(count($messages) === 3 && str_contains($messages[0],'published') && str_contains($messages[2],'published'), 'One failed date prevented independent dates publishing');
$badDate = false;
checkLive($refresh->refresh('2026-10-02', fn($m)=>null), 'Valid independent refresh failed');

// A missing original estimate must still publish all three dates successfully.
$missingOriginal = true; $messages = [];
checkLive($refresh->refresh('2026-10-02', function($m)use(&$messages){$messages[]=$m;}), 'Missing original future estimates still fail the refresh.');
checkLive(count(array_filter($messages,fn($m)=>str_contains($m,'published')))===3, 'Missing estimates prevented one date from publishing.');
$missingOriginal = false;

// A real refresh emits the current game totals and fantasy owner name, only for active scoring gains.
$notificationCount=DB::table('push_notifications')->count();
$scorer=$byId['05y3a'];
foreach($expected as $date){
 $oldSnapshot=$snapshots[$date];$changedBench=false;$changedActive=false;
 foreach($oldSnapshot['players'] as &$oldPlayer){
  if($date==='2026-10-02'){
   if($oldPlayer['player_id']===$scorer['player_id'] && $oldPlayer['fantasy_team_id']===$scorer['fantasy_team_id'])$oldPlayer['daily_fpts']=0;
   elseif(!$changedBench && $oldPlayer['scoring_status']==='BENCH'){$oldPlayer['daily_fpts']-=1;$changedBench=true;}
   elseif(!$changedActive && $oldPlayer['scoring_status']==='ACTIVE'){$oldPlayer['daily_fpts']+=1;$changedActive=true;}
  }elseif(!$changedActive && $oldPlayer['scoring_status']==='ACTIVE'){$oldPlayer['daily_fpts']-=1;$changedActive=true;}
 }
 unset($oldPlayer);
 $repository->publish($oldSnapshot,liveFixture($date),CarbonImmutable::now('UTC'));
}
checkLive($refresh->refresh('2026-10-02',fn($m)=>null),'Scoring notification refresh failed');
checkLive(DB::table('push_notifications')->count()===$notificationCount+1,'Bench/decreased/yesterday/tomorrow score emitted an alert, or active increase was missing');
$notification=DB::table('push_notifications')->orderByDesc('id')->first();
checkLive($notification->title==='ECFHL · '.$snapshots['2026-10-02']['teams'][$scorer['fantasy_team_id']]['name'],'Notification omitted ECFHL team name');
$expectedStats=[];foreach(['G','A','PPG','SHG','GWG'] as $stat)$expectedStats[]=$stat.': '.(int)($scorer['stats'][$stat]['value']??0);
checkLive($notification->body===\App\Support\PlayerName::display($scorer['player_name']).' · '.$scorer['daily_fpts']." FPts\n".implode(' · ',$expectedStats),'Notification stat line does not match dated Fantrax totals');
checkLive($notification->fantasy_team_id===$scorer['fantasy_team_id']&&$notification->category==='live-score'&&$notification->url==='/teams/current?date=2026-10-02','Scoring alert lost ownership/date/category');
checkLive($refresh->refresh('2026-10-02',fn($m)=>null)&&DB::table('push_notifications')->count()===$notificationCount+1,'Unchanged score duplicated notification');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$loneSlug=Illuminate\Support\Str::slug($snapshots['2026-10-02']['teams']['65yfc2nwmolvao6q']['name']);
DB::table('seasons')->insert(['season_id'=>'test-current','season_name'=>'2026-27']);
foreach ($snapshots['2026-10-02']['teams'] as $id=>$team) {
    DB::table('team_seasons')->insert(['team_season_id'=>'test-'.$id,'season_id'=>'test-current','franchise_id'=>$id,'original_name'=>$team['name']]);
}
foreach ($expected as $date) {
    $app->forgetScopedInstances();
    $request=Illuminate\Http\Request::create('/teams/current?date='.$date);
    $response=$kernel->handle($request);
    checkLive($response->getStatusCode()===200, 'Live scoring render failed: '.$date.' '.$response->getContent());
    $html=$response->getContent();
    checkLive(str_contains($html,'data-page-logo="'.App\Support\TeamImages::url('league-logo',160).'"') && str_contains($html,'data-page-name="ECFHL"'), 'Live Scoring must use the league loading logo');
    checkLive(preg_match_all('/<details\\b[^>]*class="[^"]*\\bmatchup-card\\b[^"]*"/', $html)===7, 'Rendered matchups incomplete');
    checkLive(str_contains($html, 'matchup-scoreboard.css') && substr_count($html, 'team-live-matchup-summary')>=7, 'Live Scoring does not use the shared Home scoreboard');
    checkLive(str_contains($html,'date=2026-10-01') && str_contains($html,'date=2026-10-02') && str_contains($html,'date=2026-10-03'), 'Date buttons wrong after Atlantic midnight');
    if ($date==='2026-10-02') checkLive(str_contains($html,'Carlsson, Leo') && str_contains($html,'Hintz, Roope'), 'Regression players missing in rendered page');
    $kernel->terminate($request,$response);
    $app->forgetScopedInstances();
    $request=Illuminate\Http\Request::create('/teams/current/'.$loneSlug.'?date='.$date);
    $response=$kernel->handle($request);
    checkLive($response->getStatusCode()===200, 'My Team matchup render failed: '.$date.' '.substr(strip_tags($response->getContent()),0,1500));
    if ($date==='2026-10-02') checkLive(str_contains($response->getContent(),'Carlsson, Leo'), 'My Team still depended on old participation flags');
    $rosterHtml=$response->getContent();
    $rosterDocument=new DOMDocument();
    @$rosterDocument->loadHTML('<?xml encoding="UTF-8">'.$rosterHtml);
    $rosterXPath=new DOMXPath($rosterDocument);
    $rosterSection=$rosterXPath->query('//section[contains(concat(" ",normalize-space(@class)," ")," team-roster-section ")]')->item(0);
    checkLive($rosterSection!==null, 'Roster section missing');
    checkLive(strpos($rosterHtml,'class="team-roster-section"')<strpos($rosterHtml,'class="team-summary-grid"'), 'Roster must precede matchup and advisor');
    $picksPosition=strpos($rosterHtml,'class="card team-future-picks"');
    checkLive($picksPosition>strpos($rosterHtml,'class="team-roster-section"') && $picksPosition<strpos($rosterHtml,'class="team-summary-grid"'), 'Draft picks must follow the roster before matchup and advisor');
    foreach(['F'=>'Forward','D'=>'Defense','G'=>'Goalie'] as $position=>$label){
        $targets=$rosterXPath->query('//details[@data-target-position="'.$position.'"]')->item(0);
        checkLive($targets!==null && !$targets->hasAttribute('open'), $label.' targets must render collapsed even when empty');
        checkLive(str_contains($targets->firstElementChild->textContent,$label.' Targets'), 'Wrong target heading');
    }
    checkLive(!str_contains($rosterHtml,'ecfhl-team-targets:'), 'Targets must default collapsed instead of restoring a saved open state');
    checkLive($rosterXPath->query('//head/link[contains(@href,"/team-roster.css")]')->length===1, 'Roster styles must load in the head before first paint');
    $fantraxImage=$rosterXPath->query('//img[@src="/fantrax-icon.png"]')->item(0);
    checkLive($fantraxImage?->getAttribute('width')==='14' && $fantraxImage->getAttribute('height')==='14', 'Fantrax badge must reserve its compact dimensions before styling');
    $loader=$rosterXPath->query('//*[@id="navigation-loading"]')->item(0);
    checkLive($loader?->getAttribute('data-page-logo')===App\Support\TeamImages::url($loneSlug,160), 'Team loading screen must use the team logo');
    checkLive($loader->getAttribute('data-league-logo')===App\Support\TeamImages::url('league-logo',160), 'Other pages must use the league loading logo');
    checkLive($rosterXPath->query('//*[@id="team-hide-non-playing"]')->length===0, 'Removed roster filter still rendered');
    $contractBadges=$rosterXPath->query('.//span[contains(concat(" ",normalize-space(@class)," ")," team-contract-sticker ")]',$rosterSection);
    foreach($contractBadges as $badge){
        checkLive(str_contains($badge->parentNode->getAttribute('class'),'team-player-name-wrap'), 'Contract separated from the player name');
        checkLive($badge->previousElementSibling?->nodeName==='strong', 'Contract must follow player/team');
    }
    foreach($rosterXPath->query('.//tr[@data-playing="0"]',$rosterSection) as $idlePlayer){
        checkLive(str_contains($idlePlayer->getAttribute('class'),'team-not-playing'), 'Non-playing player missing its visual state');
    }

    $kernel->terminate($request,$response);
}
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03T07:00:00Z'));
$app->forgetScopedInstances();
$request=Illuminate\Http\Request::create('/api/live-scoring');
$response=$kernel->handle($request);
$calendar=json_decode($response->getContent(),true,512,JSON_THROW_ON_ERROR);
checkLive($response->getStatusCode()===200 && $calendar['selected_date']==='2026-10-03' && $calendar['dates']===['yesterday'=>'2026-10-02','today'=>'2026-10-03','tomorrow'=>'2026-10-04'], 'HTTP default date did not change at Pacific midnight');
$kernel->terminate($request,$response);
CarbonImmutable::setTestNow();
echo "Live scoring checks passed: Pacific midnight/DST, three Fantrax dates, seven matchups, IDs, zero-point players, future projections, optional missing estimates, preserved actual scoring, injury flags, failed-date preservation, independent publication, scoring notification team names/current stat lines/trigger isolation, and page rendering.\n";
