<?php
// Reuse the real player-list setup, migrations and combined-filter regressions.
require __DIR__.'/season-players.php';
use App\Support\FutureDraftPicks;
use App\Support\PlayerGames;
use App\Support\PlayerProfile;
use Illuminate\Support\Facades\Http;

Http::preventStrayRequests();
\Carbon\CarbonImmutable::setTestNow('2026-10-05T02:30:00Z'); // Oct 4 in Pacific.
$data=app(PlayerProfile::class)->data('p1');
verifySeason(array_column($data['statRows'],'label')===['Current Season','Last 7 days','Last 14 days','Last 21 days','Fantrax Proj'],'Player profile must contain all five source rows.');
verifySeason((float)$data['statRows'][0]['fpts']===1234.5&&(float)$data['statRows'][1]['fpts']===699.0&&(float)$data['statRows'][4]['fpts']===998.0,'Player profile must keep season, recent and Fantrax stats separate.');
verifySeason($data['roster']->fantasy_team_id==='beta','Player profile ownership must use the latest roster.');
$html=seasonRequest('/players/p1')->getContent();
verifySeason(str_contains($html,'Player &lt;unsafe&gt;')&&!str_contains($html,'Player <unsafe>')&&str_contains($html,'<h2>Stats</h2>'),'Player profile must escape names and display current season categories.');
$fragment=seasonRequest('/players/p1',true);$json=json_decode($fragment->getContent(),true);
verifySeason(str_contains($json['html'],'Last 21 days')&&!str_contains($json['html'],'<html')&&str_contains($fragment->headers->get('Cache-Control'),'no-store'),'Player popup must provide only its stats section and avoid shared caching.');
$missing=app(PlayerProfile::class)->data('p65');
verifySeason($missing['statRows'][1]['fpts']===null&&$missing['statRows'][4]['rate']===null,'Missing recent/projection data must remain unknown rather than zero.');
$default=$service->data(\Illuminate\Http\Request::create('/players?availability=all&positions=F,D,G'));
verifySeason($default['selectedLines']===[]&&$default['selectedPps']===[]&&$default['players']->total()===65,'Default assignment filters must be unselected and include every player.');
$multi=$service->data(\Illuminate\Http\Request::create('/players?availability=all&line=1,2&pp=1,2'));
$singles=[];
foreach(['1','2'] as $line)foreach(['1','2'] as $pp){$single=$service->data(\Illuminate\Http\Request::create('/players?availability=all&line='.$line.'&pp='.$pp));$singles[]=$single['players']->total();}
verifySeason($multi['players']->total()===array_sum($singles),'Line and PP choices must combine within groups before pagination.');
$empty=$service->data(\Illuminate\Http\Request::create('/players?availability=all&line=empty'));
verifySeason($empty['players']->total()===65&&$empty['selectedLines']===[],'Deselecting all lines must include all players.');
$games=app(PlayerGames::class)->forDate('2026-10-05');
verifySeason(isset($games['SJS'],$games['LAK'])&&$games['SJS']['opponent']==='LAK'&&$games['LAK']['opponent']==='SJS','Game columns must normalize aliases and include both sides.');
verifySeason($games['SJS']['away']!==$games['LAK']['away'],'Home/away flags must reverse for the opponent.');
$teams=[['team'=>'Alpha','franchise_id'=>'a'],['team'=>'Beta','franchise_id'=>'b'],['team'=>'Gamma','franchise_id'=>'c']];
$picks=FutureDraftPicks::ownership($teams,[
 ['pick_original_franchise_id'=>'a','draft_round'=>1,'from_franchise_id'=>'a','to_franchise_id'=>'b'],
 ['pick_original_franchise_id'=>'a','draft_round'=>1,'from_franchise_id'=>'b','to_franchise_id'=>'c'],
 ['pick_original_team_raw'=>'Beta','draft_round'=>2,'from_franchise_id'=>'b','to_franchise_id'=>'a'],
],3);
verifySeason(count($picks)===9,'Draft transfers must conserve the total number of picks.');
$alphaPick=collect($picks)->first(fn($p)=>$p['original_franchise_id']==='a'&&$p['round']===1);
verifySeason($alphaPick['owner']==='c','A pick traded onward must belong to its final owner.');
verifySeason(collect($picks)->where('owner','a')->count()===3,'Traded-out and acquired picks must be reflected in availability.');
\Carbon\CarbonImmutable::setTestNow();
echo "Season experience checks passed: safe full/popup profiles, five stat sources, missing data, multiselect unions/empty choices, Pacific games, home/away pairs and chained draft transfers.\n";
