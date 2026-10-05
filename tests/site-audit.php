<?php
// Real historical data and sanitized live fixtures, with a disposable database/cache.
require __DIR__.'/performance.php';

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Http\Request;
use Carbon\CarbonImmutable;
use App\Support\CurrentTeams;
use App\Support\Archive;
use App\Support\LiveScoring\SnapshotBuilder;
use App\Support\LiveScoring\SnapshotRepository;

Http::preventStrayRequests();
CarbonImmutable::setTestNow('2026-10-03T18:00:00Z');
foreach(['2026-10-01','2026-10-02','2026-10-03'] as $date){
    $source=json_decode(gzdecode(file_get_contents(__DIR__.'/fixtures/live-scoring/'.$date.'.json.gz')),true,512,JSON_THROW_ON_ERROR);
    $snapshot=(new SnapshotBuilder)->build($date,$source['day'],$source['period'],$source['details']);
    app(SnapshotRepository::class)->publish($snapshot,$source,CarbonImmutable::now());
}
foreach($snapshot['players'] as $player){
    DB::table('active_fantasy_rosters')->insert([
        'game_date'=>'2026-10-03','fantasy_team_id'=>$player['fantasy_team_id'],
        'fantasy_team_name'=>$snapshot['teams'][$player['fantasy_team_id']]['name'],'player_id'=>$player['player_id'],
        'player_name'=>$player['player_name'],'nhl_team'=>$player['nhl_team'],'position'=>$player['position'],
        'roster_status'=>$player['scoring_status'],
    ]);
}
$admin=App\Models\User::create(['name'=>'Audit admin','email'=>'audit@example.test','password'=>password_hash('test-password',PASSWORD_BCRYPT)]);
$admin->forceFill(['is_admin'=>true])->save();

function auditPage(string $path, bool $signedIn=false, bool $fragment=false): array{
    global $app,$kernel,$admin;
    $app->forgetScopedInstances();Auth::forgetGuards();
    if($signedIn)Auth::guard()->setUser($admin);
    DB::flushQueryLog();
    $request=Request::create($path,'GET',[],[],[], $fragment?['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']:[]);
    $start=microtime(true);$response=$kernel->handle($request);$kernel->terminate($request,$response);
    checkSpeed($response->getStatusCode()===200,'Site audit failed: '.$path.' '.$response->getStatusCode().' '.substr(strip_tags($response->getContent()),0,300));
    $html=$response->getContent();
    if(!$fragment){
        checkSpeed(str_contains($html,'themes.css?v=1'),'Shared theme missing: '.$path);
        checkSpeed(strpos($html,'localStorage.getItem')<strpos($html,'<body'),'Theme applied after page paint: '.$path);
    }
    return ['path'=>$path,'queries'=>count(DB::getQueryLog()),'ms'=>round((microtime(true)-$start)*1000,1),'bytes'=>strlen($html)];
}
$public=['/','/seasons','/standings','/teams','/teams/league','/teams/current','/trades','/draft','/prizes','/players/history?q=Sidney','/rules','/daily-targets','/players','/notifications','/login','/register'];
$app->instance('request',Request::create('/?type=all'));
$archive=app(Archive::class);
foreach($archive->seasons() as $season)$public[]='/seasons/'.rawurlencode($season['season']).'?type=all';
foreach($archive->teams() as $team)$public[]='/teams/'.$team['id'].'?type=all';
foreach(CurrentTeams::standings() as $team)$public[]='/teams/current/'.$team['slug'];
try{
    foreach($public as $path)auditPage($path);
    foreach(['/account','/account/claim-team','/notifications','/admin','/admin/teams','/admin/advisors','/admin/projections','/job-status'] as $path)auditPage($path,true);
    echo 'Site audit passed: '.count($public).' public/detail pages and 8 authenticated/admin pages use the shared light/dark theme without upstream calls.'.PHP_EOL;
    foreach(['/standings','/teams/league','/players','/teams/current'] as $path){
        auditPage($path);$profile=auditPage($path);
        echo json_encode($profile).PHP_EOL;
    }
    $full=auditPage('/standings');$fragment=auditPage('/standings',false,true);
    checkSpeed($fragment['bytes']<$full['bytes']/2,'Standings refresh should omit layout/scripts and cut payload at least in half.');
    checkSpeed($fragment['queries']<=4,'Warm standings refresh query budget exceeded.');
    echo 'Standings refresh: '.$full['bytes'].' -> '.$fragment['bytes'].' bytes ('.round(100*(1-$fragment['bytes']/$full['bytes'])).'% smaller).'.PHP_EOL;
}finally{
    CarbonImmutable::setTestNow();
    (new Illuminate\Filesystem\Filesystem)->deleteDirectory($cacheDirectory);
}
