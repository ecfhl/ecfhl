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
// Count active lineup players, including teammates sharing the same NHL event.
foreach($snapshots as $date=>$snapshot){
 $viewTeams=(new App\Support\LiveScoring\ViewData)->teams($snapshot);
 foreach($snapshot['teams'] as $id=>$team){
  foreach(['2'=>'games_in_progress','1'=>'games_not_started'] as $state=>$key){
   $participants=array_filter($snapshot['players'],fn($p)=>$p['fantasy_team_id']===$id && $p['scoring_status']==='ACTIVE' && $p['game_status']===(string)$state);
   checkLive($viewTeams[$id][$key]===count($participants),'Incorrect active player game count for '.$date.' '.$key);
  }
 }
}
// Five active players in two NHL games must still show 5 Live, plus 1 Upcoming.
$countSnapshot=$snapshots['2026-10-02'];
$countTeamId=array_key_first($countSnapshot['teams']);
$countTemplate=$countSnapshot['players'][0];
$countSnapshot['players']=[];
foreach(['2','2','2','2','2','1','2','1','2'] as $i=>$state){
 $countSnapshot['players'][]=array_merge($countTemplate,['fantasy_team_id'=>$countTeamId,'player_id'=>'count-'.$i,'game_status'=>$state,'game_id'=>$i<3?'nhl-one':'nhl-two','scoring_status'=>$i<6?'ACTIVE':'BENCH','roster_status'=>$i<6?'ACTIVE':(['BENCH','INJURED_RESERVE','MINORS'][$i-6])]);
}
$countView=(new App\Support\LiveScoring\ViewData)->teams($countSnapshot)[$countTeamId];
checkLive($countView['games_in_progress']===5 && $countView['games_not_started']===1,'Same-game players must count separately; bench, IR and minors must not inflate activity');
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

// IR and minors share BENCH scoring membership but are separate roster slots.
$presenter = new \App\Support\LiveScoring\ViewData;
foreach (['INJURED_RESERVE'=>false, 'MINORS'=>false, 'BENCH'=>true, 'RESERVE'=>true] as $slot=>$expectedBench) {
    $display = $presenter->player(array_replace($active, ['scoring_status'=>'BENCH', 'roster_status'=>$slot]));
    checkLive($display->is_bench===$expectedBench, 'Bench badge incorrectly inferred from non-scoring membership for '.$slot);
    checkLive($display->is_ir===($slot==='INJURED_RESERVE'), 'Correcting bench badges changed IR status for '.$slot);
    checkLive($display->today_fpts===$active['daily_fpts'], 'Correcting roster badges changed daily scoring');
}

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
$expectedAlert=App\Support\LiveScoring\ScoringAlert::payload($snapshots['2026-10-02'],$scorer,$scorer);
checkLive($notification->title===$expectedAlert['title'],'Notification omitted team/player points');
checkLive($notification->body===$expectedAlert['body'],'Notification event summary does not match dated score change');
checkLive($notification->fantasy_team_id===$scorer['fantasy_team_id']&&$notification->category==='live-score'&&$notification->url==='/teams/current?date=2026-10-02','Scoring alert lost ownership/date/category');
checkLive($refresh->refresh('2026-10-02',fn($m)=>null)&&DB::table('push_notifications')->count()===$notificationCount+1,'Unchanged score duplicated notification');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$loneSlug=Illuminate\Support\Str::slug($snapshots['2026-10-02']['teams']['65yfc2nwmolvao6q']['name']);
DB::table('seasons')->insert(['season_id'=>'test-current','season_name'=>'2026-27']);
$standingRank = 0;
foreach ($snapshots['2026-10-02']['teams'] as $id=>$team) {
    DB::table('team_seasons')->insert(['team_season_id'=>'test-'.$id,'season_id'=>'test-current','franchise_id'=>$id,'original_name'=>$team['name']]);
    DB::table('team_seasons')->insert(['team_season_id'=>'standing-'.$id,'season_id'=>'2026-27','franchise_id'=>$id,'original_name'=>$team['name'], 'rank'=>++$standingRank, 'w'=>0, 'l'=>1, 't'=>0]);
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
    checkLive(substr_count($html,'class="team-live-games"')===14 && substr_count($html,'active players in live games')===14,'Every fantasy team must show its active live and not-started player counts');
    checkLive(substr_count($html, 'class="team-live-record"')===14 && substr_count($html, '>0–1–0</span>')===14, 'Live Scoring must show each team record separately, including zero wins/ties');
    foreach (['1st','2nd','3rd','5th','11th','12th','13th','14th'] as $ordinal) checkLive(str_contains($html, 'class="team-live-rank">('.$ordinal.')</span>'), 'Live Scoring rank suffix missing: '.$ordinal);
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
$statPlayer=(object)['position'=>'F','today_gp'=>1,'today_g'=>1,'today_a'=>2,'today_ppg'=>1,'today_shg'=>1,'today_gwg'=>1];
checkLive(App\Support\LiveScoring\PlayerStatLine::text($statPlayer,true)==='1 goal - 2 assists - 1 PPG - 1 SHG - GWG','Live stats must use verbal labels without GP or a GWG count');
$statPlayer->today_g=0;$statPlayer->today_ppg=0;$statPlayer->today_shg=0;$statPlayer->today_gwg=0;
checkLive(App\Support\LiveScoring\PlayerStatLine::text($statPlayer,true)==='2 assists','Zero categories must disappear');
$statPlayer->today_gp=0;
checkLive(App\Support\LiveScoring\PlayerStatLine::text($statPlayer,true)==='Not Playing','Started/finished games without participation must say Not Playing');
$statPlayer->today_a=0;
checkLive(App\Support\LiveScoring\PlayerStatLine::text($statPlayer,false)==='','Upcoming games must not be called Not Playing');
$statPlayer->position='G';$statPlayer->today_gp=1;$statPlayer->today_w=1;$statPlayer->today_so=1;
checkLive(App\Support\LiveScoring\PlayerStatLine::text($statPlayer,true)==='1 win - 1 SO','Goalie stat lines must omit GP and zero categories');
$eventPlayer=['fantasy_team_id'=>'team','player_name'=>'Matthew Tkachuk','nhl_team'=>'FLA','position'=>'F','daily_fpts'=>2,'stats'=>['G'=>['value'=>1],'GWG'=>['value'=>1],'A'=>['value'=>0]]];
$eventSnapshot=['fantasy_date'=>'2026-10-07','teams'=>['team'=>['name'=>'Morning Sherwood']]];
$event=App\Support\LiveScoring\ScoringAlert::payload($eventSnapshot,$eventPlayer,['stats'=>[]]);
checkLive($event['title']==='Morning Sherwood - 2pts' && $event['body']==="Tkachuk, Matthew (FLA) scores the game winner.\nG: 1 · GWG: 1",'Score message must describe the event without zero categories');
$eventPlayer['stats']['A']['value']=1;$oldEvent=$eventPlayer;$oldEvent['stats']['A']['value']=0;
checkLive(App\Support\LiveScoring\ScoringAlert::payload($eventSnapshot,$eventPlayer,$oldEvent)['body']==="Tkachuk, Matthew (FLA) gets an assist.\nG: 1 · A: 1 · GWG: 1",'Old goals must not be repeated for a new assist');
$eventPlayer['position']='G';$eventPlayer['stats']=['W'=>['value'=>1],'SHO'=>['value'=>1]];
checkLive(App\Support\LiveScoring\ScoringAlert::payload($eventSnapshot,$eventPlayer,['stats'=>[]])['body']==="Tkachuk, Matthew (FLA) records a win and records a shutout.\nW: 1 · SO: 1",'Goalie message must describe a win/shutout');
$eventPlayer['position']='F';
foreach(['G'=>'scores a goal','A'=>'gets an assist','PPG'=>'scores a power-play goal','SHG'=>'scores a short-handed goal','GWG'=>'scores the game winner'] as $key=>$sentence){
 $eventPlayer['stats']=['GP'=>['value'=>1],$key=>['value'=>1],'W'=>['value'=>1]];
 $alert=App\Support\LiveScoring\ScoringAlert::payload($eventSnapshot,$eventPlayer);
 checkLive($alert['body']==="Tkachuk, Matthew (FLA) ".$sentence.".\n".$key.': 1','Skater sentence/stat filtering failed for '.$key);
}
$eventPlayer['position']=['G'];
foreach(['L'=>'takes a loss','OTL'=>'takes an overtime loss','OL+ShL'=>'takes an overtime loss','SO'=>'records a shutout','SHO'=>'records a shutout'] as $key=>$sentence){
 $eventPlayer['stats']=['GP'=>['value'=>1],$key=>['value'=>1],'G'=>['value'=>1]];
 $label=in_array($key,['SO','SHO'],true)?'SO':($key==='L'?'L':'OTL');
 checkLive(App\Support\LiveScoring\ScoringAlert::payload($eventSnapshot,$eventPlayer)['body']==="Tkachuk, Matthew (FLA) ".$sentence.".\n".$label.': 1','Goalie sentence/stat alias failed for '.$key);
}
$eventPlayer['stats']=['GP'=>['value'=>1],'W'=>['value'=>0],'L'=>['value'=>0]];
checkLive(App\Support\LiveScoring\ScoringAlert::payload($eventSnapshot,$eventPlayer)['body']==='Tkachuk, Matthew (FLA) has a score update.','All-zero stats must omit the summary line');
$eventPlayer+=['player_id'=>'one','scoring_status'=>'ACTIVE'];
$eventPlayer['stats']=['GP'=>['value'=>1],'W'=>['value'=>1],'SHO'=>['value'=>1],'OL+ShL'=>['value'=>1]];
$eventSnapshot['players']=[$eventPlayer,array_merge($eventPlayer,['player_id'=>'bench','scoring_status'=>'BENCH']),array_merge($eventPlayer,['player_id'=>'ir','scoring_status'=>'INJURED_RESERVE']),array_merge($eventPlayer,['player_id'=>'minor','scoring_status'=>'MINORS'])];
$popupState=App\Support\LiveScoring\ScoringAlert::state($eventSnapshot);
checkLive(count($popupState['players'])===1&&$popupState['players'][0]['key']==='team|one'&&$popupState['players'][0]['goalie'],'Popup baseline must only include active scoring players and stable team/player IDs');
checkLive($popupState['players'][0]['stats']['SO']===1&&$popupState['players'][0]['stats']['OTL']===1&&!isset($popupState['players'][0]['stats']['GP']),'Popup state must normalize goalie aliases and omit GP');
CarbonImmutable::setTestNow();
echo "Live scoring checks passed: Pacific midnight/DST, three Fantrax dates, seven matchups, IDs, zero-point players, future projections, optional missing estimates, preserved actual scoring, injury flags, failed-date preservation, independent publication, scoring notification team names/current stat lines/trigger isolation, and page rendering.\n";

// Bench ordering is applied within each playing group, before player names.
$sortRow=static fn($name,$playing,$bench)=>(object)['player_name'=>$name,'daily_participant'=>$playing,'is_bench'=>$bench,'is_ir'=>false];
$sorted=\App\Support\LiveScoring\ViewData::sortPlayers(collect([
    $sortRow('Alpha Bench Idle',false,true),$sortRow('Alpha Bench Playing',true,true),
    $sortRow('Zulu Idle',false,false),$sortRow('Zulu Playing',true,false),
    $sortRow('Beta Playing',true,false),$sortRow('Beta Idle',false,false),
]));
checkLive($sorted->pluck('player_name')->all()===['Beta Playing','Zulu Playing','Alpha Bench Playing','Beta Idle','Zulu Idle','Alpha Bench Idle'],'Playing first, then bench last within each group, then alphabetical');
