<?php
/** Independent totals and edge cases for queries used by public pages. */
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Support\Archive;
function archiveFor(string $mode): Archive {
    global $app;
    $app->forgetScopedInstances();
    $app->instance('request',Request::create('/?type='.$mode));
    return $app->make(Archive::class);
}
$beforeSeed = DB::table('draft_picks')->count();
try {
    (new Database\Seeders\DatabaseSeeder)->run();
    throw new RuntimeException('Clipped import was accepted');
} catch (RuntimeException $e) {
    check(str_contains($e->getMessage(),'Import refused before database writes'),'Unexpected import failure: '.$e->getMessage());
}
check(DB::table('draft_picks')->count()===$beforeSeed,'Rejected import changed existing data');
foreach (['h2h'=>[4410,4410], 'total'=>[6500,6500], 'all'=>[10910,10910], 'none'=>[4410,4410]] as $mode=>[$winnings,$fees]) {
    $data=archiveFor($mode);
    $totals=$data->prizeTotals();
    check(abs(array_sum(array_column($totals,'awards'))-$winnings)<0.001,"Prize sum: $mode");
    check(abs(array_sum(array_column($totals,'fees'))-$fees)<0.001,"Fees including COVID credit: $mode");
    check(abs(array_sum(array_column($totals,'net'))-($winnings-$fees))<0.001,"Net sum: $mode");
    check(array_sum(array_column($data->seasonPrizeLeaders(),'score'))===$winnings*100,"Season prizes differ from overview: $mode");
    $allowed=array_column($data->seasons(),'season');
    foreach($data->draftSeason('all') as $p) check(in_array($p['season'],$allowed,true),"Draft filter: $mode");
    foreach($data->teamSeasons() as $r) check(in_array($r['season'],$allowed,true),"Standings filter: $mode");
    foreach($data->trades() as $t) check(in_array($t['season'],$allowed,true),"Trade filter: $mode");
    foreach($data->teams() as $team) {
        $expected=count(array_filter($data->trades(),fn($t)=>!$t['vetoed']&&in_array($team['id'],[$t['from_id'],$t['to_id']],true)));
        check($data->teamTradeCount($team['id'])===$expected,"Trade count filter: $mode {$team['id']}");
    }
    foreach($data->seasonLeaders()['top_seasons'] as $r) check(($r['w']+$r['l']+$r['t'])>0,'Unplayed season ranked');
    foreach($data->seasonLeaders()['most_fpts'] as $r) check($r['fantasy_points_for']!==null,'Missing Fpts ranked');
    $response=$kernel->handle(Request::create('/prizes?type='.$mode));
    check($response->getStatusCode()===200 && array_sum(array_column($response->original->getData()['totals'],'awards'))===$winnings,"Rendered prize totals: $mode");
    $kernel->terminate(Request::create('/prizes?type='.$mode),$response);
}
$data=archiveFor('all');
$members=DB::table('season_members')->where('season_id','S2008')->pluck('franchise_id')->all();
$ledger=array_column($data->teams(),null,'id');
foreach($members as $fid) {
    $expected=DB::table('season_members')->where('franchise_id',$fid)->distinct()->count('season_id');
    check($ledger[$fid]['seasons']===$expected,'Lost Yahoo standings must not remove membership');
}
check($data->seasonRegularTop3('2026-27')===[],'Unranked upcoming teams shown as top three');
check(archiveFor('h2h')->seasonAwards('2018-19')===[],'Season award filter leaked');
$data=archiveFor('all');
foreach($data->seasons() as $season) foreach($data->seasonAwards($season['season']) as $a) {
    $historical=DB::table('team_seasons')->where('season_id',$season['season_id'])->where('franchise_id',$a['franchise_id'])->value('original_name');
    if($historical) check($a['team']===$historical,'Season award uses current franchise name');
}
// An incomplete cache must supplement, not replace, relational picks.
$baseline=$data->draftSeason('all');
$sample=$baseline[0];
DB::table('source_cache')->updateOrInsert(['source_key'=>'drafts'],['source_url'=>'fixture','payload'=>json_encode([$sample['season']=>[$sample['team']=>[['overall'=>$sample['overall'],'round'=>$sample['round'],'pick'=>$sample['pick'],'player'=>'Stale cached name']]]])]);
$data=archiveFor('all');
$merged=$data->draftSeason('all');
check(count($merged)===count($baseline),'Partial draft cache hides relational seasons or duplicates picks');
check($merged[0]['player']===$sample['player'],'Stale cached player replaced relational record');
foreach($merged as $p) {
    $expected=DB::table('draft_picks as p')->join('drafts as d','d.draft_id','=','p.draft_id')->join('seasons as s','s.season_id','=','d.season_id')->where('s.season_name',$p['season'])->where('p.overall_pick',$p['overall'])->value('p.franchise_id');
    check($p['franchise_id']===$expected,'Draft franchise ID was discarded');
}
DB::table('source_cache')->where('source_key','drafts')->delete();
// Query output contracts must survive every season and franchise detail page.
$data=archiveFor('all');
$detailPaths=array_map(fn($s)=>'/seasons/'.rawurlencode($s['season']).'?type=all',$data->seasons());
foreach($data->teams() as $team)$detailPaths[]='/teams/'.$team['id'].'?type=all';
foreach($detailPaths as $path){
    $app->forgetScopedInstances();$request=Request::create($path);$response=$kernel->handle($request);
    check($response->getStatusCode()===200,'Detail query/render failed: '.$path);
    $kernel->terminate($request,$response);
}
$app->forgetScopedInstances();$request=Request::create('/seasons?type=all');$response=$kernel->handle($request);
$awardLeaderTotal=array_sum(array_column($response->original->getData()['seasonLeaders']['season_awards'],'score'));
$expectedAwards=DB::table('awards')->whereIn('award_type_id',['president','leader','art_ross','norris','vezina','calder'])->count();
check($awardLeaderTotal===$expectedAwards,'Most Awards omitted non-player trophies');
$kernel->terminate($request,$response);
foreach(['2027 Round 3 Pick','2026 Round 2 Pick 10','2026 Draft Pick Round 1 (Orcas)'] as $pick){
    $html=view('partials.trade-asset',['item'=>$pick,'senderTeam'=>'Orcas','season'=>'2025-26'])->render();
    check(str_contains($html,'/draft?season='),'Draft pick incorrectly linked as a player');
}
// Broad player searches yield one event per trade, with the page's expected schema.
$data=archiveFor('all');$events=$data->playerHistory('a');$seen=[];$previous='';
foreach($events as $event) {
    check(isset($event['kind'],$event['data'],$event['sort']),'Player history schema');
    check(strcmp($previous,$event['sort'])<=0,'Player history chronology');$previous=$event['sort'];
    if($event['kind']==='trade') { $key=$event['season'].'|'.$event['data']['id'];check(!isset($seen[$key]),'Duplicate trade in player results');$seen[$key]=true; }
}
foreach(['/?type=total','/draft?type=all&season=all&franchise=F002','/trades?type=all&team=Androids','/players/history?type=all&q=Sidney','/rules'] as $path) {
    $app->forgetScopedInstances();$request=Request::create($path);$response=$kernel->handle($request);
    check($response->getStatusCode()===200,'Query regression page: '.$path);
    if($path==='/?type=total') check(!str_contains($response->getContent(),"President's Trophy:"),'Total points incorrectly labelled President trophy');
    if(str_contains($path,'/draft?')) {
        foreach($response->original->getData()['picks'] as $p) check($p['franchise_id']==='F002','Franchise draft link includes another owner');
    }
    if(str_contains($path,'/trades?')) check($response->original->getData()['selectedFranchise']==='F002','Legacy franchise trade link lost its filter');
    $kernel->terminate($request,$response);
}
echo "Query audit passed: prize/fee reconciliation, four season filters, membership, draft cache merge and IDs, historical awards, player chronology and filtered links.\n";
