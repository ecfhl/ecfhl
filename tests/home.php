<?php
// Use the existing in-memory Laravel setup and real migrations.
require __DIR__.'/season-players.php';

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Http::preventStrayRequests();
CarbonImmutable::setTestNow('2026-10-05T18:00:00Z');
foreach (['season_player_stats','player_projections','active_fantasy_rosters','team_seasons','scoring_period_matchups'] as $table) DB::table($table)->delete();

$names=['One Man Bang 💥','North Shore Explorers','Mullet Mafia','Lone Tsar','Brasse Camarade','Big Bogan Beaking','Morning Sherwoods','Multiple Scoregasms','BookHockey','West Coast','Ammon Keys Balls','Young Guns','Green Machine','Formenton’s Construction Company'];
foreach ($names as $i=>$name) DB::table('team_seasons')->insert(['team_season_id'=>'home-'.$i,'season_id'=>'2026-27','franchise_id'=>'home-'.$i,'original_name'=>$name,'rank'=>$i+1,'w'=>7,'l'=>2,'t'=>1,'fantasy_points_for'=>12345-$i]);
for ($i=0;$i<14;$i+=2) DB::table('scoring_period_matchups')->insert(['season_id'=>'2026-27','period_number'=>2,'start_date'=>'2026-10-05','end_date'=>'2026-10-11','away_team_name'=>$names[$i],'home_team_name'=>$names[$i+1],'away_score'=>123+$i,'home_score'=>456+$i]);

foreach (['F'=>['Benjamin Kindel','Parker Kelly','Nicolas Roy'],'D'=>['Josh Manson','Samuel Girard','Kaedan Korczak'],'G'=>['Joel Hofer','Dylan Garand','Joey Daccord']] as $position=>$players) {
    for ($i=1;$i<=6;$i++) {
        $id=$position.$i;
        DB::table('season_player_stats')->insert(['player_id'=>$id,'season_id'=>$i===5?'2025-26':'2026-27','player_name'=>$players[$i-1]??'Extra '.$id,'nhl_team'=>'MTL','position'=>$position,'season_fpts'=>100+$i,'season_gp'=>10,'season_fpts_per_game'=>10,'stats_json'=>'{}','stats_through'=>'2026-10-04','refreshed_at'=>now()]);
        $projection=['player_id'=>$id,'as_of_date'=>'2026-10-05','window_end_date'=>'2026-10-04','projected_fpts_per_game'=>$i===6?999:10-$i,'refreshed_at'=>now()];
        foreach ([7,14,21] as $days) foreach (['gp','fpts','fpts_per_game'] as $stat) $projection[$stat.'_'.$days.'d']=0;
        DB::table('player_projections')->insert($projection);
        // The highest projection is owned in the latest snapshot, including bench/minors/IR.
        if ($i===6) DB::table('active_fantasy_rosters')->insert(['game_date'=>'2026-10-05','fantasy_team_id'=>'owner','fantasy_team_name'=>'Owner','player_id'=>$id,'player_name'=>'Owned '.$id,'position'=>$position,'roster_status'=>['F'=>'BENCH','D'=>'MINORS','G'=>'INJURED_RESERVE'][$position]]);
    }
}
// A released player was owned yesterday but must be available today.
DB::table('active_fantasy_rosters')->insert(['game_date'=>'2026-10-04','fantasy_team_id'=>'owner','fantasy_team_name'=>'Owner','player_id'=>'F1','player_name'=>'Released player','position'=>'F']);

function homeDocument(string $html): DOMXPath {
    $doc=new DOMDocument();
    @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
    return new DOMXPath($doc);
}
$html=seasonRequest('/')->getContent();
$xpath=homeDocument($html);
$hasClass=fn($class)=>'contains(concat(" ",normalize-space(@class)," ")," '.$class.' ")';
foreach (['F','D','G'] as $i=>$position) {
    $rows=$xpath->query('(//div['.$hasClass('home-player-group').'])['.($i+1).']/a');
    verifySeason($rows->length===3,'Home must keep exactly three available players per position.');
    foreach ($rows as $j=>$row) {
        verifySeason($row->getAttribute('href')==='/players/'.$position.($j+1),'Home must sort by EC Proj, ignore old-season players, and exclude every owned roster slot.');
        verifySeason(str_contains($row->textContent,(string)(101+$j).' FPts'),'Home must display actual season FPts, not the projection used for sorting.');
    }
}
verifySeason($xpath->query('//div['.$hasClass('home-season-grid').']/div['.$hasClass('home-column').']/section')->length===4,'All four cards must belong to the two desktop stacks.');
verifySeason($xpath->query('//div['.$hasClass('season-home').']/div['.$hasClass('home-history-link').']')->length===1,'Malformed closing tags must not push the archive footer outside the Home shell.');
verifySeason($xpath->query('//nav['.$hasClass('mobile-primary-nav').']/a[1]')->item(0)->getAttribute('href')==='/','Home must be the first mobile navigation item.');
verifySeason(str_contains($html,'Times in Atlantic · Fantasy day follows Pacific time')&&str_contains($html,'thescore-mark'),'Home must retain the time note and theScore link/icon.');

if ($output=getenv('ECFHL_HOME_QA_HTML')) file_put_contents($output,$html);
DB::table('season_player_stats')->delete();
$empty=homeDocument(seasonRequest('/')->getContent());
verifySeason($empty->query('//div['.$hasClass('home-player-group').']/h3')->length===3&&$empty->query('//div['.$hasClass('home-player-group').']/p')->length===3,'Empty Player Watch must still show all three position headings and useful empty states.');
CarbonImmutable::setTestNow();
echo "Home checks passed: available-only EC Proj ordering, actual FPts, three players per position, released/bench/minors/IR ownership, current season, valid card/footer structure and mobile Home link.\n";
