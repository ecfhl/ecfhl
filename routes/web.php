<?php

use App\Support\Archive as EcfhlData;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function (EcfhlData $data) {
    $seasons=$data->seasons(); $teams=$data->teams(); $trades=$data->trades();
    $latest=null; foreach($seasons as $season){if(!empty($season['champion'])){$latest=$season;break;}}
    $latestLeader=null;if($latest)foreach($data->seasonAwards($latest['season']) as $award)if($award['id']==='president'){$latestLeader=$award['team'];break;}
    $championships=count(array_filter($seasons,fn($s)=>!empty($s['champion'])));
    $selectedSeasonIds=array_column($seasons,'season_id');
    $prizesAwarded=$selectedSeasonIds ? ((int)DB::table('prize_awards')->whereIn('season_id',$selectedSeasonIds)->sum('amount_cents'))/100 : 0;
    $leaders=$data->overviewLeaders();
    return view('home',compact('seasons','teams','trades','latest','latestLeader','championships','prizesAwarded','leaders'));
});
Route::get('/seasons', function (EcfhlData $data) {
    $seasons=$data->seasons(); foreach($seasons as &$season)$season['regular_top3']=$data->seasonRegularTop3($season['season']); unset($season); $seasonLeaders=$data->seasonLeaders(); $all=$data->teamSeasons(); $worst=[];
    foreach($all as $r){$g=($r['w']??0)+($r['l']??0)+($r['t']??0);if(!$g)continue;$pct=(2*($r['w']??0)+($r['t']??0))/(2*$g);$worst[]=['team'=>$r['team'],'season'=>$r['season'],'score'=>$pct,'value'=>$g?number_format($pct*100,1).'%':'—','detail'=>($r['w']??0).'-'.($r['l']??0).'-'.($r['t']??0)];} usort($worst,fn($a,$b)=>$a['score']<=>$b['score']); $seasonLeaders['worst_records']=$worst;
    $awardCounts=[];
    foreach($seasons as $season) foreach($data->seasonAwards($season['season']) as $a){$key=$season['season'].'|'.($a['franchise_id']??$a['team']);if(!isset($awardCounts[$key]))$awardCounts[$key]=['team'=>$a['team'],'season'=>$season['season'],'value'=>0,'score'=>0];$awardCounts[$key]['value']++;$awardCounts[$key]['score']++;}
    $awardRows=array_values($awardCounts);usort($awardRows,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($b['season'],$a['season']));$seasonLeaders['season_awards']=$awardRows;$seasonLeaders['top_earners']=$data->seasonPrizeLeaders();return view('seasons.index',compact('seasons','seasonLeaders'));
});
Route::get('/seasons/{season}', function(string $season,EcfhlData $data){
    $season=rawurldecode($season);$row=$data->season($season);if(!$row)return redirect('/seasons')->with('notice','This season is outside the selected season types.');$standings=$data->teamSeasons($season);$tradeCounts=[];
    foreach($data->trades() as $trade){if(($trade['season']??null)!==$season||!empty($trade['vetoed']))continue;foreach(array_unique(array_filter([$trade['from_id']??null,$trade['to_id']??null])) as $franchiseId)$tradeCounts[$franchiseId]=($tradeCounts[$franchiseId]??0)+1;}
    $tradeLeaders=[];foreach($standings as $r){$franchiseId=$r['franchise_id']??null;$count=$franchiseId?($tradeCounts[$franchiseId]??0):0;$tradeLeaders[]=['team'=>$r['team'],'value'=>$count,'score'=>$count];}usort($tradeLeaders,fn($a,$b)=>($b['score']<=>$a['score'])?:strnatcasecmp($a['team'],$b['team']));$draftPicks=$data->draftSeason($season);$firstRoundPicks=array_values(array_filter($draftPicks,fn($p)=>(int)($p['round']??0)===1));
    return view('seasons.show',['season'=>$row,'tradeLeaders'=>$tradeLeaders,'standings'=>$standings,'awards'=>$data->seasonAwards($season),'tradeCount'=>$data->seasonTradeCount($season),'topPicks'=>$firstRoundPicks]);
})->where('season','.*');
Route::get('/teams', function(EcfhlData $data){$type=$data->mode();$status=request('status','all');$allTeams=$data->teamLedger($type,'all');$teams=$data->teamLedger($type,$status);$overview=$data->overviewLeaders();$totals=[];foreach($data->teamSeasons() as $r){$id=$r['franchise_id']??null;if($id&&$r['fantasy_points_for']!==null)$totals[$id]=($totals[$id]??0)+(float)$r['fantasy_points_for'];}foreach($teams as &$t)$t['total_fpts']=$totals[$t['id']]??null;unset($t);$pres=[];foreach($allTeams as $t)if(($t['president']??0)>0)$pres[]=['team'=>$t['team'],'value'=>$t['president'],'score'=>$t['president']];usort($pres,fn($a,$b)=>$b['score']<=>$a['score']);$franchiseLeaders=['championships'=>$overview['championships'],'presidents'=>$pres,'winning_pct'=>$overview['winning_pct'],'first_picks'=>$overview['first_picks'],'trades'=>$overview['trades'],'awards'=>$overview['awards']];return view('teams.index',compact('teams','allTeams','type','status','franchiseLeaders'));});
Route::get('/teams/current', function() {
    $tz='America/Halifax';
    $now=\Carbon\CarbonImmutable::now($tz);
    $today=$now->toDateString();
    $tomorrow=$now->addDay()->toDateString();
    $date=(string)request('date',$today);
    if(!in_array($date,[$today,$tomorrow],true))$date=$today;

    $currentNames=DB::table('team_seasons as ts')
        ->join('seasons as s','s.season_id','=','ts.season_id')
        ->where('s.season_name','2026-27')
        ->orderBy('ts.original_name')
        ->pluck('ts.original_name')
        ->all();

    $rows=DB::table('active_fantasy_rosters')
        ->whereDate('game_date',$date)
        ->whereIn('fantasy_team_name',$currentNames)
        ->get();

    $normName=fn($v)=>preg_replace('/[^\pL\pN]+/u','',mb_strtolower(trim((string)$v)))??'';
    $normTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $pp=DB::table('active_pp_lines')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name));
    $lines=DB::table('active_line_combinations')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name).'|'.strtoupper(trim($r->position_group)));

    $rows=$rows->map(function($p)use($pp,$lines,$normName,$normTeam){
        $team=$normTeam($p->nhl_team);
        $name=$normName($p->player_name);
        $pos=strtoupper(trim((string)$p->position));
        $line=$lines[$team.'|'.$name.'|'.$pos]??null;
        $power=$pp[$team.'|'.$name]??null;
        $p->line_number=$line?(int)$line->line_number:null;
        $p->pp_unit=$power?(int)$power->pp_unit:null;
        return $p;
    });

    $teams=[];
    foreach($currentNames as $teamName){
        $teamRows=$rows->where('fantasy_team_name',$teamName);
        $positions=[];
        foreach(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $code=>$label){
            $positionRows=$teamRows->where('position',$code)->reject(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){
                $rank=fn($p)=>(bool)$p->is_ir?3:((bool)$p->is_bench?2:(!empty($p->opponent)?0:1));
                $ar=$rank($a);$br=$rank($b);
                if($ar!==$br)return $ar<=>$br;
                return ((float)($b->projected_fpts??-INF)<=>(float)($a->projected_fpts??-INF));
            })->values();
            $positions[$code]=['label'=>$label,'rows'=>$positionRows];
        }
        $minorRows=$teamRows->filter(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){$ar=(bool)$a->is_ir?3:(!empty($a->opponent)?0:1);$br=(bool)$b->is_ir?3:(!empty($b->opponent)?0:1);return $ar!==$br?$ar<=>$br:((float)($b->projected_fpts??-INF)<=>(float)($a->projected_fpts??-INF));})->values();
        $positions['M']=['label'=>'Minors','rows'=>$minorRows];
        $teams[]=[
            'name'=>$teamName,
            'slug'=>\Illuminate\Support\Str::slug($teamName),
            'positions'=>$positions,
            'count'=>$teamRows->count(),
        ];
    }

    $lastUpdate=$rows->max('last_update');
    return view('teams.current-index',compact('teams','date','today','tomorrow','lastUpdate'));
});

Route::get('/teams/current/{slug}', function(string $slug) {
    $tz='America/Halifax';
    $now=\Carbon\CarbonImmutable::now($tz);
    $today=$now->toDateString();
    $tomorrow=$now->addDay()->toDateString();
    $date=(string)request('date',$today);
    if(!in_array($date,[$today,$tomorrow],true))$date=$today;

    $currentNames=DB::table('team_seasons as ts')
        ->join('seasons as s','s.season_id','=','ts.season_id')
        ->where('s.season_name','2026-27')
        ->pluck('ts.original_name')
        ->all();

    $teamName=null;
    foreach($currentNames as $name){
        if(\Illuminate\Support\Str::slug($name)===$slug){$teamName=$name;break;}
    }
    abort_unless($teamName,404);

    $rows=DB::table('active_fantasy_rosters')
        ->whereDate('game_date',$date)
        ->where('fantasy_team_name',$teamName)
        ->get();

    $normName=fn($v)=>preg_replace('/[^\pL\pN]+/u','',mb_strtolower(trim((string)$v)))??'';
    $normTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $pp=DB::table('active_pp_lines')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name));
    $lines=DB::table('active_line_combinations')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name).'|'.strtoupper(trim($r->position_group)));

    $decorate=function($p)use($pp,$lines,$normName,$normTeam){
        $team=$normTeam($p->nhl_team);
        $name=$normName($p->player_name);
        $pos=strtoupper(trim((string)$p->position));
        $line=$lines[$team.'|'.$name.'|'.$pos]??null;
        $power=$pp[$team.'|'.$name]??null;
        $p->line_number=$line?(int)$line->line_number:null;
        $p->pp_unit=$power?(int)$power->pp_unit:null;
        return $p;
    };
    $rows=$rows->map($decorate);

    $positions=[];
    foreach(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $code=>$label){
        $positionRows=$rows->where('position',$code)->reject(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){
            $rank=fn($p)=>(bool)$p->is_ir?3:((bool)$p->is_bench?2:(!empty($p->opponent)?0:1));
            $ar=$rank($a);$br=$rank($b);
            if($ar!==$br)return $ar<=>$br;
            return ((float)($b->projected_fpts??-INF)<=>(float)($a->projected_fpts??-INF));
        })->values();
        $positions[$code]=['label'=>$label,'rows'=>$positionRows];
    }
    $minorRows=$rows->filter(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){$ar=(bool)$a->is_ir?3:(!empty($a->opponent)?0:1);$br=(bool)$b->is_ir?3:(!empty($b->opponent)?0:1);return $ar!==$br?$ar<=>$br:((float)($b->projected_fpts??-INF)<=>(float)($a->projected_fpts??-INF));})->values();
    $positions['M']=['label'=>'Minors','rows'=>$minorRows];

    $lastUpdate=$rows->max('last_update');
    return view('teams.current',compact('teamName','slug','date','today','tomorrow','positions','lastUpdate'));
});

Route::get('/teams/{slug}', function(string $slug,EcfhlData $data){
    $team=$data->team($slug);abort_unless($team,404);$history=$data->teamSeasons(null,$team['team']);$tradeCount=$data->teamTradeCount($team['id']);
    $selectedSeasonNames=array_values(array_filter(array_column($data->seasons(),'season')));
    $aliasNames=DB::table('franchise_aliases')->where('franchise_id',$team['id'])->pluck('alias_name')->all();$canonicalName=DB::table('franchises')->where('franchise_id',$team['id'])->value('franchise_name');$knownNames=array_values(array_unique(array_filter(array_merge([$canonicalName],$aliasNames))));
    $draftRows=DB::table('draft_picks as dp')->join('drafts as d','d.draft_id','=','dp.draft_id')->join('seasons as s','s.season_id','=','d.season_id')->where('dp.round',1)->whereIn('s.season_name',$selectedSeasonNames)->where(function($q)use($team,$knownNames){$q->where('dp.franchise_id',$team['id']);if($knownNames)$q->orWhereIn('dp.team_name_raw',$knownNames);})->select('s.season_name',DB::raw('COUNT(*) as pick_count'))->groupBy('s.season_name')->get();
    $firstRoundBySeason=$draftRows->map(fn($r)=>['team'=>$r->season_name,'value'=>(int)$r->pick_count,'score'=>(int)$r->pick_count])->all();usort($firstRoundBySeason,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($b['team'],$a['team']));$firstRoundCount=array_sum(array_column($firstRoundBySeason,'value'));
    $partners=[];foreach($data->trades() as $t){if($t['vetoed'])continue;if(($t['from_id']??null)===$team['id'])$pid=$t['to_id']??null;elseif(($t['to_id']??null)===$team['id'])$pid=$t['from_id']??null;else continue;if($pid)$partners[$pid]=($partners[$pid]??0)+1;}$names=array_column($data->teamLedger($data->mode(),'all'),'team','id');$tradePartners=[];foreach($partners as $id=>$n)$tradePartners[]=['team'=>$names[$id]??$id,'value'=>$n,'score'=>$n];usort($tradePartners,fn($a,$b)=>$b['score']<=>$a['score']);return view('teams.show',compact('team','history','tradeCount','firstRoundCount','firstRoundBySeason','tradePartners'));
});
Route::get('/trades', function(EcfhlData $data){$trades=$data->trades();$seasons=array_values(array_unique(array_column($trades,'season')));rsort($seasons);$franchises=[];foreach($trades as $t)foreach(['from','to'] as $side){$id=$t[$side.'_id']??null;if($id)$franchises[$id]=$data->franchiseName($id);}asort($franchises,SORT_NATURAL|SORT_FLAG_CASE);$selectedSeason=(string)request('season','');$selectedFranchise=$data->franchiseId((string)request('franchise',request('team','')))??'';$names=array_column($data->teamLedger($data->mode(),'all'),'team','id');$traderCounts=[];$partnerCounts=[];foreach($trades as $t){if($t['vetoed'])continue;$ids=array_values(array_unique(array_filter([$t['from_id']??null,$t['to_id']??null])));foreach($ids as $id)$traderCounts[$id]=($traderCounts[$id]??0)+1;if(count($ids)===2){sort($ids);$key=implode('|',$ids);$partnerCounts[$key]=($partnerCounts[$key]??0)+1;}}arsort($traderCounts);$topTraders=[];foreach($traderCounts as $id=>$n)$topTraders[]=['team'=>$names[$id]??$id,'value'=>$n,'score'=>$n];arsort($partnerCounts);$topTradePartners=[];foreach($partnerCounts as $key=>$n){[$a,$b]=explode('|',$key,2);$topTradePartners[]=['team'=>($names[$a]??$a).' ↔ '.($names[$b]??$b),'value'=>$n,'score'=>$n];}$firstRoundTraded=[];foreach($trades as $t){if($t['vetoed'])continue;foreach(['from','to'] as $side){$sender=$t[$side.'_id']??null;if(!$sender)continue;foreach(($t[$side.'_items']??[]) as $item){if(preg_match('/(?:draft\s+pick\s+)?round\s*1(?!\d)/i',(string)$item))$firstRoundTraded[$sender]=($firstRoundTraded[$sender]??0)+1;}}}arsort($firstRoundTraded);$topFirstRoundTraders=[];foreach($firstRoundTraded as $id=>$n)$topFirstRoundTraders[]=['team'=>$names[$id]??$id,'value'=>$n,'score'=>$n];return view('trades.index',compact('trades','seasons','franchises','selectedSeason','selectedFranchise','topTraders','topTradePartners','topFirstRoundTraders'));});
Route::get('/draft', function(EcfhlData $data){$seasons=$data->draftSeasons();$selected=request('season',$seasons[0]??'all');if($selected!=='all'&&!in_array($selected,$seasons,true))$selected=$seasons[0]??'all';$q=trim((string)request('q',''));$team=trim((string)request('team',''));$selectedFranchise=$data->franchiseId((string)request('franchise',$team));$allPicks=$data->draftSeason('all');$counts=['overall1'=>[],'top5'=>[],'round1'=>[]];$franchiseNames=[];$aliasToFranchise=[];foreach($data->teamLedger($data->mode(),'all') as $f){$franchiseNames[$f['id']]=$f['team'];$aliasToFranchise[mb_strtolower(trim($f['team']))]=$f['id'];}foreach($data->teamSeasons() as $h){if(empty($h['franchise_id']))continue;$aliasToFranchise[mb_strtolower(trim($h['team']??$h['original_name']??''))]=$h['franchise_id'];if(!isset($franchiseNames[$h['franchise_id']]))$franchiseNames[$h['franchise_id']]=$h['team']??$h['original_name'];}foreach($allPicks as $p){$teamName=trim((string)($p['team']??''));$id=$p['franchise_id']??null;if(!$id&&$teamName!=='')$id=$aliasToFranchise[mb_strtolower($teamName)]??null;if(!$id)continue;$overall=(int)($p['overall']??0);$round=(int)($p['round']??0);if($overall===1)$counts['overall1'][$id]=($counts['overall1'][$id]??0)+1;if($overall>=1&&$overall<=5)$counts['top5'][$id]=($counts['top5'][$id]??0)+1;if($round===1)$counts['round1'][$id]=($counts['round1'][$id]??0)+1;}$draftLeaders=[];foreach($counts as $key=>$rows){arsort($rows);$draftLeaders[$key]=[];foreach($rows as $id=>$n)$draftLeaders[$key][]=['team'=>$franchiseNames[$id]??$id,'value'=>$n,'score'=>$n];}$picks=$data->draftSeason($selected);if($q!==''){$needle=mb_strtolower($q);$picks=array_values(array_filter($picks,fn($p)=>str_contains(mb_strtolower(($p['player']??'').' '.($p['team']??'')),$needle)));}if($selectedFranchise){$picks=array_values(array_filter($picks,fn($p)=>($p['franchise_id']??null)===$selectedFranchise));}elseif($team!==''||request('franchise')){$picks=[];}return view('draft.index',compact('seasons','selected','picks','q','draftLeaders','selectedFranchise'));});
Route::get('/prizes',fn(EcfhlData $data)=>view('prizes',['totals'=>$data->prizeTotals(),'awardEvents'=>$data->awardEvents(),'leaders'=>$data->overviewLeaders(),'seasonLeaders'=>$data->seasonLeaders()]));
Route::get('/players',function(EcfhlData $data){$q=trim((string)request('q',''));$events=$q!==''?$data->playerHistory($q):[];$counts=['overall1'=>[],'trades'=>[],'round1'=>[]];foreach($data->draftSeason('all') as $p){$name=trim((string)($p['player']??''));if($name==='')continue;$key=mb_strtolower($name);if((int)($p['overall']??0)===1){$counts['overall1'][$key]['name']=$name;$counts['overall1'][$key]['count']=($counts['overall1'][$key]['count']??0)+1;}if((int)($p['round']??0)===1){$counts['round1'][$key]['name']=$name;$counts['round1'][$key]['count']=($counts['round1'][$key]['count']??0)+1;}}foreach($data->trades() as $t){if(!empty($t['vetoed']))continue;foreach(['from_items','to_items'] as $field){foreach(($t[$field]??[]) as $item){$name=trim((string)$item);if($name===''||preg_match('/draft\s+pick|round\s*\d|\b1st\b|\b2nd\b|\b3rd\b/i',$name))continue;$name=trim((string)preg_replace('/\s*\((?:FA|MINORS?|TBD|[1-4]\s+Years?)\)\s*$/i','',$name));if($name==='')continue;$key=mb_strtolower($name);$counts['trades'][$key]['name']=$name;$counts['trades'][$key]['count']=($counts['trades'][$key]['count']??0)+1;}}}$playerLeaders=[];foreach($counts as $type=>$rows){$list=[];foreach($rows as $row)$list[]=['team'=>$row['name'],'value'=>$row['count'],'score'=>$row['count']];usort($list,fn($a,$b)=>($b['score']<=>$a['score'])?:strnatcasecmp($a['team'],$b['team']));$playerLeaders[$type]=$list;}return view('players',compact('q','events','playerLeaders'));});
Route::get('/rules',function(){$sections=DB::table('rules')->orderBy('rule_id')->get()->groupBy('section')->map(fn($rows)=>$rows->pluck('rule_text')->all())->all();return view('rules',compact('sections'));
});

Route::get('/job-status', function () {
    $tz = 'America/Halifax';
    $now = \Carbon\CarbonImmutable::now($tz);
    $dbTime = fn($value) => $value ? \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i:s', (string)$value, $tz) : null;
    $format = fn($value) => ($dt=$dbTime($value)) ? $dt->setTimezone($tz)->format('M j, Y · g:i a T') : null;
    $state = function ($value, int $minutes) use ($now, $dbTime, $tz) {
        if (!$value) return 'No data';
        $dt=$dbTime($value); if(!$dt) return 'No data';
        return $dt->setTimezone($tz)->gte($now->subMinutes($minutes)) ? 'Current' : 'Stale';
    };
    $nextHourly = function (int $minute) use ($now) {
        $next = $now->startOfHour()->minute($minute);
        if ($next->lte($now)) $next = $next->addHour();
        return $next->format('M j · g:i a T');
    };
    $nextHalfHourly = function () use ($now) {
        $next = $now->minute < 30 ? $now->startOfHour()->minute(30) : $now->addHour()->startOfHour();
        return $next->format('M j · g:i a T');
    };
    $nextFourHourly = function (int $minute) use ($now) {
        $hour = (int)$now->format('G'); $nextHour = $hour - ($hour % 4);
        $next = $now->startOfDay()->addHours($nextHour)->minute($minute);
        if ($next->lte($now)) $next = $next->addHours(4);
        return $next->format('M j · g:i a T');
    };
    $fantraxLast = DB::table('active_daily_players')->max('last_update');
    $goaliesLast = DB::table('active_starting_goalies')->max('checked_at');
    $linesLast = DB::table('active_pp_lines')->max('checked_at');
    $oddsLast = DB::table('todays_odds')->max('checked_at');
    $teamsLast = DB::table('active_fantasy_rosters')->max('last_update');
    $jobs = [
        ['key'=>'players','name'=>'Fantrax Available Players','schedule'=>'Every hour at :00','last_update'=>$format($fantraxLast),'records'=>DB::table('active_daily_players')->count(),'next_run'=>$nextHourly(0),'state'=>$state($fantraxLast,90),'description'=>'Available players playing today and tomorrow, including projected fantasy points.'],
        ['key'=>'goalies','name'=>'Daily Faceoff Goalies','schedule'=>'Every 30 minutes','last_update'=>$format($goaliesLast),'records'=>DB::table('active_starting_goalies')->count(),'next_run'=>$nextHalfHourly(),'state'=>$state($goaliesLast,60),'description'=>'Starting-goalie status for today and tomorrow.'],
        ['key'=>'lines','name'=>'Daily Faceoff Lines','schedule'=>'Every 4 hours at :02','last_update'=>$format($linesLast),'records'=>DB::table('active_pp_lines')->count(),'next_run'=>$nextFourHourly(2),'state'=>$state($linesLast,300),'description'=>'Current line combinations and PP1/PP2 assignments for all NHL teams.'],
        ['key'=>'odds','name'=>'NHL Odds','schedule'=>'Every 4 hours at :06','last_update'=>$format($oddsLast),'records'=>DB::table('todays_odds')->count(),'next_run'=>$nextFourHourly(6),'state'=>$state($oddsLast,300),'description'=>'Consensus NHL moneyline odds for today and tomorrow from The Odds API.'],
        ['key'=>'teams','name'=>'Fantasy Team Rosters','schedule'=>'Every hour at :10','last_update'=>$format($teamsLast),'records'=>DB::table('active_fantasy_rosters')->count(),'next_run'=>$nextHourly(10),'state'=>$state($teamsLast,90),'description'=>'Current Fantrax rosters for every fantasy team, enriched with projections, opponents, injuries, line and power-play assignments.'],
    ];
    return view('job-status', compact('jobs'));
});

Route::get('/ai-tips', function () {
    $now = \Carbon\CarbonImmutable::now('America/Halifax');
    $today = $now->toDateString();
    $tomorrow = $now->addDay()->toDateString();
    $date = request('date', $today);
    abort_unless(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date), 422, 'Use a valid game date.');
    if (!in_array($date, [$today, $tomorrow], true)) $date = $today;
    $selected = $date === $tomorrow ? 'tomorrow' : 'today';
    $fantraxRows = DB::table('active_daily_players')->whereDate('game_date',$date)->orderByRaw('projected_fpts IS NULL')->orderByDesc('projected_fpts')->orderBy('source_rank')->get();
    $norm = fn($v)=>preg_replace('/[^\pL\pN]+/u','',mb_strtolower(trim((string)$v)))??'';
    $ppRows = DB::table('active_pp_lines')->get();
    $ppByTeam=[]; foreach($ppRows as $p){$ppByTeam[strtoupper($p->team)][$norm($p->player_name)] = 'PP'.(int)$p->pp_unit;}
    $decorate=function($rows)use($ppByTeam,$norm){return $rows->map(function($p)use($ppByTeam,$norm){$p->pp_unit=$ppByTeam[strtoupper($p->team)][$norm($p->player_name)]??null;return $p;});};
    $forwards=$decorate($fantraxRows->filter(fn($p)=>strtoupper(trim((string)$p->position))==='F')->values());
    $defensemen=$decorate($fantraxRows->filter(fn($p)=>strtoupper(trim((string)$p->position))==='D')->values());
    $dfo = DB::table('active_starting_goalies')->whereDate('game_date',$date)->get();
    $dfoByTeam=[];$confirmedByTeam=[];foreach($dfo as $g){$team=strtoupper(trim((string)$g->team));$dfoByTeam[$team][$norm($g->player_name)]=$g;if(strtolower(trim((string)$g->starting_status))==='confirmed')$confirmedByTeam[$team]=$norm($g->player_name);}
    $goalies=$fantraxRows->filter(fn($p)=>strtoupper(trim((string)$p->position))==='G')->map(function($p)use($dfoByTeam,$confirmedByTeam,$norm){$team=strtoupper(trim((string)$p->team));$name=$norm($p->player_name);$g=$dfoByTeam[$team][$name]??null;$p->starting_status=$g?ucfirst(strtolower(trim((string)$g->starting_status))):'NA';$p->not_starting=isset($confirmedByTeam[$team])&&$confirmedByTeam[$team]!==$name;if($p->not_starting)$p->starting_status='Not starting';return $p;})->values();
    $rank=['Confirmed'=>0,'Probable'=>1,'Unconfirmed'=>2,'NA'=>3,'Not starting'=>4];$goalies=$goalies->sort(function($a,$b)use($rank){$ra=$rank[$a->starting_status]??3;$rb=$rank[$b->starting_status]??3;return $ra===$rb?((float)($b->projected_fpts??-INF)<=>(float)($a->projected_fpts??-INF)):($ra<=>$rb);})->values();
    return view('ai-tips', compact('date','today','tomorrow','selected','goalies','forwards','defensemen'));
});

require __DIR__.'/ai-tips-db.php';
require __DIR__.'/jobs.php';