<?php

use App\Support\Archive as EcfhlData;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function (EcfhlData $data) {
    $seasons=$data->seasons(); $teams=$data->teams(); $trades=$data->trades(); $latest=$seasons[0]??null;
    $latestStandings=$latest?$data->teamSeasons($latest['season']):[]; $latestLeader=$latestStandings[0]['team']??null;
    $championships=count(array_filter($seasons,fn($s)=>!empty($s['champion'])));
    $prizesAwarded=array_sum(array_column($data->prizeTotals(),'awards'));
    $leaders=$data->overviewLeaders();
    return view('home',compact('seasons','teams','trades','latest','latestLeader','championships','prizesAwarded','leaders'));
});

Route::get('/seasons', function (EcfhlData $data) {
    $seasons=$data->seasons(); foreach($seasons as &$season)$season['regular_top3']=$data->seasonRegularTop3($season['season']); unset($season);
    $seasonLeaders=$data->seasonLeaders();
    $all=$data->teamSeasons(); $worst=[];
    foreach($all as $r){$g=($r['w']??0)+($r['l']??0)+($r['t']??0);if(!$g)continue;$pct=(2*($r['w']??0)+($r['t']??0))/(2*$g);$worst[]=['team'=>$r['team'],'season'=>$r['season'],'score'=>$pct,'value'=>number_format($pct*100,1).'%','detail'=>($r['w']??0).'-'.($r['l']??0).'-'.($r['t']??0)];}
    usort($worst,fn($a,$b)=>$a['score']<=>$b['score']); $seasonLeaders['worst_records']=$worst;
    $awardCounts=[]; foreach($data->awardEvents() as $a){if(!in_array($a['id'],['president','leader','art_ross','norris','vezina','calder'],true))continue;$k=$a['season'].'|'.$a['team'];$awardCounts[$k]=($awardCounts[$k]??0)+1;}
    $awardRows=[];foreach($awardCounts as $k=>$n){[$season,$team]=explode('|',$k,2);$awardRows[]=['team'=>$team,'season'=>$season,'value'=>$n,'score'=>$n];}usort($awardRows,fn($a,$b)=>$b['score']<=>$a['score']);$seasonLeaders['season_awards']=$awardRows;
    return view('seasons.index',compact('seasons','seasonLeaders'));
});

Route::get('/seasons/{season}', function(string $season,EcfhlData $data){
    $season=rawurldecode($season);$row=$data->season($season);
    if(!$row)return redirect('/seasons')->with('notice','This season is outside the selected season types.');
    $standings=$data->teamSeasons($season);
    $tradeCounts=[];
    foreach($data->seasonTradeLeaders($season) as $r)$tradeCounts[$r['team']]=(int)$r['value'];
    $tradeLeaders=[];
    foreach($standings as $r){$name=$r['team'];$tradeLeaders[]=['team'=>$name,'value'=>$tradeCounts[$name]??0,'score'=>$tradeCounts[$name]??0];}
    usort($tradeLeaders,fn($a,$b)=>($b['score']<=>$a['score'])?:strnatcasecmp($a['team'],$b['team']));
    $draftPicks=$data->draftSeason($season);
    $firstRoundPicks=array_values(array_filter($draftPicks,fn($p)=>(int)($p['round']??0)===1));
    return view('seasons.show',[
        'season'=>$row,
        'tradeLeaders'=>$tradeLeaders,
        'standings'=>$standings,
        'awards'=>$data->seasonAwards($season),
        'tradeCount'=>$data->seasonTradeCount($season),
        'topPicks'=>$firstRoundPicks,
    ]);
})->where('season','.*');

Route::get('/teams', function(EcfhlData $data){
    $type=$data->mode();$status=request('status','all');$allTeams=$data->teamLedger($type,'all');$teams=$data->teamLedger($type,$status);$overview=$data->overviewLeaders();
    $totals=[];foreach($data->teamSeasons() as $r){$id=$r['franchise_id']??null;if($id&&$r['fantasy_points_for']!==null)$totals[$id]=($totals[$id]??0)+(float)$r['fantasy_points_for'];}
    foreach($teams as &$t)$t['total_fpts']=$totals[$t['id']]??null;unset($t);
    $pres=[];foreach($allTeams as $t)if(($t['president']??0)>0)$pres[]=['team'=>$t['team'],'value'=>$t['president'],'score'=>$t['president']];usort($pres,fn($a,$b)=>$b['score']<=>$a['score']);
    $franchiseLeaders=['championships'=>$overview['championships'],'presidents'=>$pres,'winning_pct'=>$overview['winning_pct'],'first_picks'=>$overview['first_picks'],'trades'=>$overview['trades'],'awards'=>$overview['awards']];
    return view('teams.index',compact('teams','allTeams','type','status','franchiseLeaders'));
});

Route::get('/teams/{slug}', function(string $slug,EcfhlData $data){
    $team=$data->team($slug);abort_unless($team,404);$history=$data->teamSeasons(null,$team['team']);$tradeCount=$data->teamTradeCount($team['id']);
    $picks=array_values(array_filter($data->draftSeason('all'),fn($p)=>($p['franchise_id']??null)===$team['id']));$first=array_values(array_filter($picks,fn($p)=>(int)($p['round']??0)===1));$firstRoundCount=count($first);
    $bySeason=[];foreach($first as $p)$bySeason[$p['season']]=($bySeason[$p['season']]??0)+1;$firstRoundBySeason=[];foreach($bySeason as $s=>$n)$firstRoundBySeason[]=['team'=>$s,'value'=>$n,'score'=>$n];usort($firstRoundBySeason,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($b['team'],$a['team']));
    $partners=[];foreach($data->trades() as $t){if($t['vetoed'])continue;if(($t['from_id']??null)===$team['id'])$pid=$t['to_id']??null;elseif(($t['to_id']??null)===$team['id'])$pid=$t['from_id']??null;else continue;if($pid)$partners[$pid]=($partners[$pid]??0)+1;}
    $names=array_column($data->teamLedger($data->mode(),'all'),'team','id');$tradePartners=[];foreach($partners as $id=>$n)$tradePartners[]=['team'=>$names[$id]??$id,'value'=>$n,'score'=>$n];usort($tradePartners,fn($a,$b)=>$b['score']<=>$a['score']);
    return view('teams.show',compact('team','history','tradeCount','firstRoundCount','firstRoundBySeason','tradePartners'));
});

Route::get('/trades', function(EcfhlData $data){
    $trades=$data->trades();$seasons=array_values(array_unique(array_column($trades,'season')));rsort($seasons);
    $teams=[];foreach($trades as $t)foreach(($t['filter_teams']??[$t['from']??null,$t['to']??null]) as $name)if($name)$teams[$name]=true;$teams=array_keys($teams);sort($teams,SORT_NATURAL|SORT_FLAG_CASE);
    $selectedSeason=(string)request('season','');$selectedTeam=(string)request('team','');
    $names=array_column($data->teamLedger($data->mode(),'all'),'team','id');$traderCounts=[];$partnerCounts=[];
    foreach($trades as $t){if($t['vetoed'])continue;$ids=array_values(array_unique(array_filter([$t['from_id']??null,$t['to_id']??null])));foreach($ids as $id)$traderCounts[$id]=($traderCounts[$id]??0)+1;if(count($ids)===2){sort($ids);$key=implode('|',$ids);$partnerCounts[$key]=($partnerCounts[$key]??0)+1;}}
    arsort($traderCounts);$topTraders=[];foreach($traderCounts as $id=>$n)$topTraders[]=['team'=>$names[$id]??$id,'value'=>$n,'score'=>$n];
    arsort($partnerCounts);$topTradePartners=[];foreach($partnerCounts as $key=>$n){[$a,$b]=explode('|',$key,2);$topTradePartners[]=['team'=>($names[$a]??$a).' ↔ '.($names[$b]??$b),'value'=>$n,'score'=>$n];}
    $firstRoundTraded=[];foreach($trades as $t){if($t['vetoed'])continue;foreach(['from','to'] as $side){$sender=$t[$side.'_id']??null;if(!$sender)continue;foreach(($t[$side.'_items']??[]) as $item){if(preg_match('/(?:draft\s+pick\s+)?round\s*1(?!\d)/i',(string)$item))$firstRoundTraded[$sender]=($firstRoundTraded[$sender]??0)+1;}}}
    arsort($firstRoundTraded);$topFirstRoundTraders=[];foreach($firstRoundTraded as $id=>$n)$topFirstRoundTraders[]=['team'=>$names[$id]??$id,'value'=>$n,'score'=>$n];
    return view('trades.index',compact('trades','seasons','teams','selectedSeason','selectedTeam','topTraders','topTradePartners','topFirstRoundTraders'));
});

Route::get('/draft', function(EcfhlData $data){
    $seasons=$data->draftSeasons();$selected=request('season',$seasons[0]??'all');if($selected!=='all'&&!in_array($selected,$seasons,true))$selected=$seasons[0]??'all';$q=trim((string)request('q',''));$team=trim((string)request('team',''));
    $allPicks=$data->draftSeason('all');$counts=['overall1'=>[],'top5'=>[],'round1'=>[]];
    $franchiseNames=[];$aliasToFranchise=[];
    foreach($data->teamLedger($data->mode(),'all') as $f){$franchiseNames[$f['id']]=$f['team'];$aliasToFranchise[mb_strtolower(trim($f['team']))]=$f['id'];}
    foreach($data->teamSeasons() as $h){if(empty($h['franchise_id']))continue;$aliasToFranchise[mb_strtolower(trim($h['team']??$h['original_name']??''))]=$h['franchise_id'];if(!isset($franchiseNames[$h['franchise_id']]))$franchiseNames[$h['franchise_id']]=$h['team']??$h['original_name'];}
    foreach($allPicks as $p){$teamName=trim((string)($p['team']??''));$id=$p['franchise_id']??null;if(!$id&&$teamName!=='')$id=$aliasToFranchise[mb_strtolower($teamName)]??null;if(!$id)continue;$overall=(int)($p['overall']??0);$round=(int)($p['round']??0);if($overall===1)$counts['overall1'][$id]=($counts['overall1'][$id]??0)+1;if($overall>=1&&$overall<=5)$counts['top5'][$id]=($counts['top5'][$id]??0)+1;if($round===1)$counts['round1'][$id]=($counts['round1'][$id]??0)+1;}
    $draftLeaders=[];foreach($counts as $key=>$rows){arsort($rows);$draftLeaders[$key]=[];foreach($rows as $id=>$n)$draftLeaders[$key][]=['team'=>$franchiseNames[$id]??$id,'value'=>$n,'score'=>$n];}
    $picks=$data->draftSeason($selected);if($q!==''){$needle=mb_strtolower($q);$picks=array_values(array_filter($picks,fn($p)=>str_contains(mb_strtolower(($p['player']??'').' '.($p['team']??'')),$needle)));}if($team!==''){$needle=mb_strtolower($team);$picks=array_values(array_filter($picks,fn($p)=>mb_strtolower($p['team']??'')===$needle));}
    return view('draft.index',compact('seasons','selected','picks','q','draftLeaders'));
});

Route::get('/prizes',fn(EcfhlData $data)=>view('prizes',['totals'=>$data->prizeTotals(),'awardEvents'=>$data->awardEvents(),'leaders'=>$data->overviewLeaders(),'seasonLeaders'=>$data->seasonLeaders()]));
Route::get('/players',function(EcfhlData $data){$q=trim((string)request('q',''));return view('players',['q'=>$q,'events'=>$data->playerHistory($q)]);});
Route::get('/rules',function(){$sections=DB::table('rules')->orderBy('rule_id')->get()->groupBy('section')->map(fn($items)=>$items->map(fn($r)=>trim(($r->subsection?$r->subsection.' — ':'').$r->rule_text))->all())->all();foreach($sections as $title=>&$items)if(preg_match('/injur.*reserve/i',$title))$items=['Each team is allowed 5 injured reserve spots. Players on injured reserve can be replaced with free agents.'];unset($items);return view('rules',compact('sections'));});
Route::get('/api/debug/db-status',function(){$tables=['seasons','franchises','team_seasons','players','drafts','draft_picks','trades','trade_assets','award_types','awards','prize_awards','season_prizes','rules'];$counts=[];foreach($tables as $table)try{$counts[$table]=DB::table($table)->count();}catch(\Throwable $e){$counts[$table]='ERROR: '.$e->getMessage();}return response()->json($counts);});
