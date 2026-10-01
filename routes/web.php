<?php

use App\Support\Archive as EcfhlData;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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
Route::get('/standings', function(EcfhlData $data){
    $seasonName='2026-27';
    $season=$data->season($seasonName);
    if(!$season)return redirect('/seasons');
    $standings=$data->teamSeasons($seasonName);
    $standingsLastUpdate=DB::table('job_run_history')
        ->where('job_name','ecfhl:refresh-current-standings')
        ->max('completed_at');
    return view('standings',compact('season','standings','standingsLastUpdate'));
});

Route::get('/seasons/{season}', function(string $season,EcfhlData $data){
    $season=rawurldecode($season);$row=$data->season($season);if(!$row)return redirect('/seasons')->with('notice','This season is outside the selected season types.');$standings=$data->teamSeasons($season);$tradeCounts=[];
    foreach($data->trades() as $trade){if(($trade['season']??null)!==$season||!empty($trade['vetoed']))continue;foreach(array_unique(array_filter([$trade['from_id']??null,$trade['to_id']??null])) as $franchiseId)$tradeCounts[$franchiseId]=($tradeCounts[$franchiseId]??0)+1;}
    $tradeLeaders=[];foreach($standings as $r){$franchiseId=$r['franchise_id']??null;$count=$franchiseId?($tradeCounts[$franchiseId]??0):0;$tradeLeaders[]=['team'=>$r['team'],'value'=>$count,'score'=>$count];}usort($tradeLeaders,fn($a,$b)=>($b['score']<=>$a['score'])?:strnatcasecmp($a['team'],$b['team']));$draftPicks=$data->draftSeason($season);$firstRoundPicks=array_values(array_filter($draftPicks,fn($p)=>(int)($p['round']??0)===1));
    $standingsLastUpdate=$season==='2026-27'
        ? DB::table('job_run_history')->where('job_name','ecfhl:refresh-current-standings')->max('completed_at')
        : null;
    return view('seasons.show',['season'=>$row,'tradeLeaders'=>$tradeLeaders,'standings'=>$standings,'awards'=>$data->seasonAwards($season),'tradeCount'=>$data->seasonTradeCount($season),'topPicks'=>$firstRoundPicks,'standingsLastUpdate'=>$standingsLastUpdate]);
})->where('season','.*');
Route::get('/teams', function(EcfhlData $data){$type=$data->mode();$status=request('status','all');$allTeams=$data->teamLedger($type,'all');$teams=$data->teamLedger($type,$status);$overview=$data->overviewLeaders();$totals=[];foreach($data->teamSeasons() as $r){$id=$r['franchise_id']??null;if($id&&$r['fantasy_points_for']!==null)$totals[$id]=($totals[$id]??0)+(float)$r['fantasy_points_for'];}foreach($teams as &$t)$t['total_fpts']=$totals[$t['id']]??null;unset($t);$pres=[];foreach($allTeams as $t)if(($t['president']??0)>0)$pres[]=['team'=>$t['team'],'value'=>$t['president'],'score'=>$t['president']];usort($pres,fn($a,$b)=>$b['score']<=>$a['score']);$franchiseLeaders=['championships'=>$overview['championships'],'presidents'=>$pres,'winning_pct'=>$overview['winning_pct'],'first_picks'=>$overview['first_picks'],'trades'=>$overview['trades'],'awards'=>$overview['awards']];return view('teams.index',compact('teams','allTeams','type','status','franchiseLeaders'));});
Route::get('/teams/current', function() {
    $tz='America/Halifax';
    $fantasyDay=\Carbon\CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
    $today=$fantasyDay->toDateString();
    $yesterday=$fantasyDay->subDay()->toDateString();
    $tomorrow=$fantasyDay->addDay()->toDateString();
    $date=(string)request('date',$today);
    if(!in_array($date,[$yesterday,$today,$tomorrow],true))$date=$today;

    $autoRefresh=false;
    if($date===$today){
        try {
            $response=Http::timeout(8)->get('https://api-web.nhle.com/v1/score/'.$today);
            $starts=collect($response->json('games')??[])
                ->map(function($game){
                    $utc=$game['startTimeUTC']??null;
                    if(!$utc)return null;
                    try{return \Carbon\CarbonImmutable::parse($utc)->utc();}catch(\Throwable){return null;}
                })
                ->filter();
            if($starts->isNotEmpty()){
                $nowUtc=\Carbon\CarbonImmutable::now('UTC');
                $first=$starts->sort()->first();
                $last=$starts->sortDesc()->first();
                $autoRefresh=$nowUtc->betweenIncluded($first,$last->addHours(4));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

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

    $scheduleByTeam=DB::table('active_starting_goalies')
        ->whereDate('game_date',$date)
        ->get()
        ->keyBy(fn($g)=>strtoupper(trim((string)$g->team)));

    $rows=$rows->map(function($p)use($scheduleByTeam){
        $team=strtoupper(trim((string)$p->nhl_team));
        if($team!=='' && isset($scheduleByTeam[$team])){
            $game=$scheduleByTeam[$team];
            $p->is_playing=true;
            if(empty($p->opponent))$p->opponent=$game->opponent;
            if(empty($p->home_away))$p->home_away=$game->home_away;
        }
        return $p;
    });

    $scoreName=function($v){$name=trim((string)$v);if(str_contains($name,',')){[$last,$first]=array_map('trim',explode(',',$name,2));if($first!==''&&$last!=='')$name=$first.' '.$last;}return preg_replace('/[^\\pL\\pN]+/u','',mb_strtolower($name))??'';};
    $scoreTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $dailyScoreRows=DB::table('active_daily_scores')->whereDate('game_date',$date)->get();
    $scoreLastUpdate=DB::table('job_run_history')
        ->where('job_name','ecfhl:refresh-daily-scores')
        ->whereDate('target_date',$date)
        ->max('completed_at');
    $dailyScores=$dailyScoreRows->keyBy(fn($r)=>$scoreTeam($r->nhl_team).'|'.$scoreName($r->player_name));
    $finishedNhlTeams=$dailyScoreRows
        ->filter(function($r){
            $opp=trim((string)($r->opponent_display??''));
            return $opp!=='' && (bool)preg_match('/(?:\\bF\\b|\\bFinal\\b)\\s*$/i',$opp);
        })
        ->map(fn($r)=>$scoreTeam($r->nhl_team));

    $finishedNhlTeams=$finishedNhlTeams
        ->merge(
            $rows->filter(function($p){
                $text=trim((string)($p->game_time??''));
                return $text!=='' && (bool)preg_match('/(?:\\bF\\b|\\bFinal\\b)\\s*$/i',$text);
            })->map(fn($p)=>$scoreTeam($p->nhl_team))
        )
        ->filter()
        ->unique()
        ->flip();

    $rows=$rows->map(function($p)use($dailyScores,$scoreName,$scoreTeam,$finishedNhlTeams){
        $score=$dailyScores[$scoreTeam($p->nhl_team).'|'.$scoreName($p->player_name)]??null;
        $p->today_fpts=$score?(float)$score->today_fpts:0.0;
        $p->today_fpts_changed=$score?(bool)($score->fpts_changed??false):false;
        $p->live_opponent_display=$score?($score->opponent_display??null):null;
        $liveOpp=trim((string)($p->live_opponent_display??''));
        $p->game_finished=isset($finishedNhlTeams[$scoreTeam($p->nhl_team)])
            || ($liveOpp!=='' && (bool)preg_match('/(?:\\bF\\b|\\bFinal\\b)\\s*$/i',$liveOpp));
        $p->game_in_progress=$liveOpp!=='' && !$p->game_finished
            && (bool)preg_match('/\\b\\d+\\s+@?[A-Z]{2,4}\\s+\\d+\\b/i',$liveOpp);
        foreach(['gp','g','a','ppg','shg','gwg','w','so'] as $stat){
            $p->{'today_'.$stat}=$score?(int)($score->{$stat}??0):0;
        }
        return $p;
    });

    $normName=function($v){$name=trim((string)$v);if(str_contains($name,',')){[$last,$first]=array_map('trim',explode(',',$name,2));if($first!==''&&$last!=='')$name=$first.' '.$last;}return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';};
    $normTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $pp=DB::table('active_pp_lines')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name));
    $lines=DB::table('active_line_combinations')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name).'|'.strtoupper(trim($r->position_group)));
    $oddsByTeam=DB::table('todays_odds')->whereDate('game_date',$date)->get()->keyBy(fn($r)=>$normTeam($r->team));

    $rows=$rows->map(function($p)use($pp,$lines,$normName,$normTeam,$oddsByTeam){
        $team=$normTeam($p->nhl_team);
        $name=$normName($p->player_name);
        $pos=strtoupper(trim((string)$p->position));
        $line=$lines[$team.'|'.$name.'|'.$pos]??null;
        $power=$pp[$team.'|'.$name]??null;
        $p->line_number=$line?(int)$line->line_number:null;
        $p->pp_unit=$power?(int)$power->pp_unit:null;
        $p->vegas_odds=null;
        $p->vegas_odds_class=null;
        if($pos==='G' && isset($oddsByTeam[$team]) && $oddsByTeam[$team]->american_odds!==null){
            $p->vegas_odds=(int)$oddsByTeam[$team]->american_odds;
            $p->vegas_odds_class=$p->vegas_odds<=-130?'vegas-odds-good':($p->vegas_odds>=130?'vegas-odds-bad':'vegas-odds-even');
        }
        $contractLabel=strtoupper((string)$p->roster_status)==='MINORS'?'Minors':trim((string)$p->contract);
        $contractKey=strtoupper($contractLabel);
        $p->contract_label=$contractLabel;
        $p->contract_class=$contractKey==='MINORS'
            ? 'team-minors'
            : (in_array($contractKey,['FA','1 YEAR','1 YEAR(S)','1 YR'],true)
                ? 'contract-green'
                : ($contractKey==='TBD'
                    ? 'contract-yellow'
                    : (preg_match('/^[234]\s*(?:YEAR|YEARS|YR|YRS)/',$contractKey)?'contract-red':'')));
        return $p;
    });

    $teams=[];
    foreach($currentNames as $teamName){
        $teamRows=$rows->where('fantasy_team_name',$teamName);
        $positions=[];
        foreach(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $code=>$label){
            $positionRows=$teamRows->where('position',$code)->reject(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){
                $rank=fn($p)=>(!empty($p->is_playing)?0:2)+((bool)$p->is_ir?1:0);
                $ar=$rank($a);$br=$rank($b);
                if($ar!==$br)return $ar<=>$br;
                return strnatcasecmp((string)$a->player_name,(string)$b->player_name);
            })->values();
            $positions[$code]=['label'=>$label,'rows'=>$positionRows];
        }
        $minorRows=$teamRows->filter(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){$ar=(bool)$a->is_ir?3:(!empty($a->opponent)?0:1);$br=(bool)$b->is_ir?3:(!empty($b->opponent)?0:1);return $ar!==$br?$ar<=>$br:strnatcasecmp((string)$a->player_name,(string)$b->player_name);})->values();
        $positions['M']=['label'=>'Minors','rows'=>$minorRows];
        $scoringRows=$teamRows->reject(fn($p)=>(bool)$p->is_bench || (bool)$p->is_ir || strtoupper((string)$p->roster_status)==='MINORS');
        $dailyStats=[];
        foreach(['gp','g','a','ppg','shg','gwg','w','so'] as $stat){
            $dailyStats[$stat]=(int)$scoringRows->sum(fn($p)=>(int)($p->{'today_'.$stat}??0));
        }
        $inProgressGames=$teamRows
            ->filter(fn($p)=>(bool)($p->game_in_progress??false))
            ->map(function($p){
                $a=strtoupper(trim((string)($p->nhl_team??'')));
                $b=strtoupper(trim((string)($p->opponent??'')));
                $pair=array_filter([$a,$b]);
                sort($pair,SORT_STRING);
                return implode('|',$pair);
            })
            ->filter()
            ->unique()
            ->count();

        $teams[]=[
            'id'=>(string)($teamRows->first()?->fantasy_team_id ?? ''),
            'name'=>$teamName,
            'slug'=>\Illuminate\Support\Str::slug($teamName),
            'positions'=>$positions,
            'count'=>$teamRows->count(),
            'today_stats'=>$dailyStats,
            'today_fpts'=>$scoringRows->sum(fn($p)=>(float)($p->today_fpts??0)),
            'today_fpts_changed'=>$scoringRows->contains(fn($p)=>(bool)($p->today_fpts_changed??false)),
            'games_in_progress'=>$inProgressGames,
        ];
    }

    $matchupScoreChanges=DB::table('active_matchup_scores')
        ->whereDate('game_date',$date)
        ->get()
        ->keyBy(fn($r)=>(string)$r->fantasy_team_id);

    $matchups=[];
    $scheduleLabel=null;
    try {
        $schedule=app(\App\Support\FantraxSchedule::class)->forDate(\Carbon\CarbonImmutable::parse($date,$tz));
        $scheduleLabel=trim((string)($schedule['caption']??''));
        $teamsById=collect($teams)->filter(fn($t)=>$t['id']!=='')->keyBy('id');
        $used=[];
        foreach(($schedule['matchups']??[]) as $pair){
            $away=$teamsById[$pair['away_team_id']]??null;
            $home=$teamsById[$pair['home_team_id']]??null;
            if(!$away || !$home)continue;
            $away['week_fpts']=$pair['away_score']??null;
            $home['week_fpts']=$pair['home_score']??null;
            $away['week_fpts_changed']=(bool)($matchupScoreChanges[(string)$away['id']]->week_fpts_changed??false);
            $home['week_fpts_changed']=(bool)($matchupScoreChanges[(string)$home['id']]->week_fpts_changed??false);
            $matchups[]=['away'=>$away,'home'=>$home];
            $used[$away['id']]=true;
            $used[$home['id']]=true;
        }
        foreach($teams as $team){
            if($team['id']!=='' && isset($used[$team['id']]))continue;
            $team['week_fpts']=$team['today_fpts']??0;
            $matchups[]=['away'=>$team,'home'=>null];
        }
    } catch (\Throwable $e) {
        report($e);
        foreach($teams as $team){$team['week_fpts']=$team['today_fpts']??0;$matchups[]=['away'=>$team,'home'=>null];}
    }

    $matchupRank=function($matchup){
        $names=[
            strtolower((string)($matchup['away']['name']??'')),
            strtolower((string)($matchup['home']['name']??'')),
        ];
        $hasLoneTsar=(bool)collect($names)->first(function($name){
            return (str_contains($name,'lone')&&str_contains($name,'tsar'))
                || str_contains($name,mb_strtolower('Ꮮ૦ท૯⚡️𐌕รคг'));
        });
        $hasOneManBang=(bool)collect($names)->first(fn($name)=>str_contains($name,'one man bang'));
        if($hasLoneTsar&&$hasOneManBang)return 0;
        if($hasLoneTsar)return 1;
        if($hasOneManBang)return 2;
        return 3;
    };
    usort($matchups,function($a,$b)use($matchupRank){
        $ar=$matchupRank($a);
        $br=$matchupRank($b);
        if($ar!==$br)return $ar<=>$br;
        $an=strtolower((string)($a['away']['name']??$a['home']['name']??''));
        $bn=strtolower((string)($b['away']['name']??$b['home']['name']??''));
        return $an<=>$bn;
    });

    $lastUpdate=$rows->max('last_update');
    return view('teams.current-index',compact('teams','matchups','scheduleLabel','date','yesterday','today','tomorrow','lastUpdate','scoreLastUpdate','autoRefresh'));
});

Route::get('/teams/current/{slug}', function(string $slug) {
    $tz='America/Halifax';
    $fantasyDay=\Carbon\CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
    $today=$fantasyDay->toDateString();
    $yesterday=$fantasyDay->subDay()->toDateString();
    $tomorrow=$fantasyDay->addDay()->toDateString();
    $date=(string)request('date',$today);
    if(!in_array($date,[$yesterday,$today,$tomorrow],true))$date=$today;

    $currentNames=DB::table('team_seasons as ts')
        ->join('seasons as s','s.season_id','=','ts.season_id')
        ->where('s.season_name','2026-27')
        ->orderBy('ts.original_name')
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

    $scheduleByTeam=DB::table('active_starting_goalies')
        ->whereDate('game_date',$date)
        ->get()
        ->keyBy(fn($g)=>strtoupper(trim((string)$g->team)));

    $rows=$rows->map(function($p)use($scheduleByTeam){
        $team=strtoupper(trim((string)$p->nhl_team));
        if($team!=='' && isset($scheduleByTeam[$team])){
            $game=$scheduleByTeam[$team];
            $p->is_playing=true;
            if(empty($p->opponent))$p->opponent=$game->opponent;
            if(empty($p->home_away))$p->home_away=$game->home_away;
        }
        return $p;
    });

    $scoreName=function($v){$name=trim((string)$v);if(str_contains($name,',')){[$last,$first]=array_map('trim',explode(',',$name,2));if($first!==''&&$last!=='')$name=$first.' '.$last;}return preg_replace('/[^\\pL\\pN]+/u','',mb_strtolower($name))??'';};
    $scoreTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $dailyScoreRows=DB::table('active_daily_scores')->whereDate('game_date',$date)->get();
    $scoreLastUpdate=DB::table('job_run_history')
        ->where('job_name','ecfhl:refresh-daily-scores')
        ->whereDate('target_date',$date)
        ->max('completed_at');
    $dailyScores=$dailyScoreRows->keyBy(fn($r)=>$scoreTeam($r->nhl_team).'|'.$scoreName($r->player_name));
    $rows=$rows->map(function($p)use($dailyScores,$scoreName,$scoreTeam){
        $score=$dailyScores[$scoreTeam($p->nhl_team).'|'.$scoreName($p->player_name)]??null;
        $p->today_fpts=$score?(float)$score->today_fpts:0.0;
        $p->live_opponent_display=$score?($score->opponent_display??null):null;
        $liveOpp=trim((string)($p->live_opponent_display??''));
        $p->game_finished=$liveOpp!=='' && (bool)preg_match('/(?:\bF\b|\bFinal\b)\s*$/i',$liveOpp);
        $p->game_in_progress=$liveOpp!=='' && !$p->game_finished
            && (bool)preg_match('/\b\d+\s+@?[A-Z]{2,4}\s+\d+\b/i',$liveOpp);
        foreach(['gp','g','a','ppg','shg','gwg','w','so'] as $stat){
            $p->{'today_'.$stat}=$score?(int)($score->{$stat}??0):0;
        }
        return $p;
    });

    $normName=function($v){$name=trim((string)$v);if(str_contains($name,',')){[$last,$first]=array_map('trim',explode(',',$name,2));if($first!==''&&$last!=='')$name=$first.' '.$last;}return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';};
    $normTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $pp=DB::table('active_pp_lines')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name));
    $lines=DB::table('active_line_combinations')->get()->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name).'|'.strtoupper(trim($r->position_group)));
    $oddsByTeam=DB::table('todays_odds')->whereDate('game_date',$date)->get()->keyBy(fn($r)=>$normTeam($r->team));

    $decorate=function($p)use($pp,$lines,$normName,$normTeam,$oddsByTeam){
        $team=$normTeam($p->nhl_team);
        $name=$normName($p->player_name);
        $pos=strtoupper(trim((string)$p->position));
        $line=$lines[$team.'|'.$name.'|'.$pos]??null;
        $power=$pp[$team.'|'.$name]??null;
        $p->line_number=$line?(int)$line->line_number:null;
        $p->pp_unit=$power?(int)$power->pp_unit:null;
        $p->vegas_odds=null;
        $p->vegas_odds_class=null;
        if($pos==='G' && isset($oddsByTeam[$team]) && $oddsByTeam[$team]->american_odds!==null){
            $p->vegas_odds=(int)$oddsByTeam[$team]->american_odds;
            $p->vegas_odds_class=$p->vegas_odds<=-130?'vegas-odds-good':($p->vegas_odds>=130?'vegas-odds-bad':'vegas-odds-even');
        }
        $contractLabel=strtoupper((string)$p->roster_status)==='MINORS'?'Minors':trim((string)$p->contract);
        $contractKey=strtoupper($contractLabel);
        $p->contract_label=$contractLabel;
        $p->contract_class=$contractKey==='MINORS'
            ? 'team-minors'
            : (in_array($contractKey,['FA','1 YEAR','1 YEAR(S)','1 YR'],true)
                ? 'contract-green'
                : ($contractKey==='TBD'
                    ? 'contract-yellow'
                    : (preg_match('/^[234]\s*(?:YEAR|YEARS|YR|YRS)/',$contractKey)?'contract-red':'')));
        return $p;
    };
    $rows=$rows->map($decorate);

    $positions=[];
    foreach(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $code=>$label){
        $positionRows=$rows->where('position',$code)->reject(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){
            $rank=fn($p)=>(!empty($p->is_playing)?0:2)+((bool)$p->is_ir?1:0);
            $ar=$rank($a);$br=$rank($b);
            if($ar!==$br)return $ar<=>$br;
            return strnatcasecmp((string)$a->player_name,(string)$b->player_name);
        })->values();
        $positions[$code]=['label'=>$label,'rows'=>$positionRows];
    }
    $minorRows=$rows->filter(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS')->sort(function($a,$b){$ar=(bool)$a->is_ir?3:(!empty($a->opponent)?0:1);$br=(bool)$b->is_ir?3:(!empty($b->opponent)?0:1);return $ar!==$br?$ar<=>$br:strnatcasecmp((string)$a->player_name,(string)$b->player_name);})->values();
    $positions['M']=['label'=>'Minors','rows'=>$minorRows];
    $teamTodayFpts=$rows
        ->reject(fn($p)=>(bool)$p->is_bench || (bool)$p->is_ir || strtoupper((string)$p->roster_status)==='MINORS')
        ->sum(fn($p)=>(float)($p->today_fpts??0));

    $matchupScoreChanges=DB::table('active_matchup_scores')
        ->whereDate('game_date',$date)
        ->get()
        ->keyBy(fn($r)=>(string)$r->fantasy_team_id);

    $liveMatchup=null;
    $fantasyTeamId=(string)($rows->first()->fantasy_team_id??'');
    if($fantasyTeamId!==''){
        try {
            $schedule=app(\App\Support\FantraxSchedule::class)->forDate(\Carbon\CarbonImmutable::parse($date,$tz));
            foreach(($schedule['matchups']??[]) as $pair){
                $isAway=(string)($pair['away_team_id']??'')===$fantasyTeamId;
                $isHome=(string)($pair['home_team_id']??'')===$fantasyTeamId;
                if(!$isAway && !$isHome)continue;

                $opponentId=(string)($isAway?($pair['home_team_id']??''):($pair['away_team_id']??''));
                $opponentName=(string)($isAway?($pair['home_name']??''):($pair['away_name']??''));
                $opponentRows=DB::table('active_fantasy_rosters')
                    ->whereDate('game_date',$date)
                    ->where('fantasy_team_id',$opponentId)
                    ->get()
                    ->map(function($p)use($scheduleByTeam){
                        $team=strtoupper(trim((string)$p->nhl_team));
                        if($team!=='' && isset($scheduleByTeam[$team])){
                            $game=$scheduleByTeam[$team];
                            $p->is_playing=true;
                            if(empty($p->opponent))$p->opponent=$game->opponent;
                            if(empty($p->home_away))$p->home_away=$game->home_away;
                        }
                        return $p;
                    })
                    ->map(function($p)use($dailyScores,$scoreName,$scoreTeam){
                        $score=$dailyScores[$scoreTeam($p->nhl_team).'|'.$scoreName($p->player_name)]??null;
                        $p->today_fpts=$score?(float)$score->today_fpts:0.0;
                        $p->live_opponent_display=$score?($score->opponent_display??null):null;
                        $liveOpp=trim((string)($p->live_opponent_display??''));
                        $p->game_finished=$liveOpp!=='' && (bool)preg_match('/(?:\bF\b|\bFinal\b)\s*$/i',$liveOpp);
                        $p->game_in_progress=$liveOpp!=='' && !$p->game_finished
                            && (bool)preg_match('/\b\d+\s+@?[A-Z]{2,4}\s+\d+\b/i',$liveOpp);
                        foreach(['gp','g','a','ppg','shg','gwg','w','so'] as $stat){
                            $p->{'today_'.$stat}=$score?(int)($score->{$stat}??0):0;
                        }
                        return $p;
                    })
                    ->map($decorate);

                $opponentTodayFpts=$opponentRows
                    ->reject(fn($p)=>(bool)$p->is_bench || (bool)$p->is_ir || strtoupper((string)$p->roster_status)==='MINORS')
                    ->sum(fn($p)=>(float)($p->today_fpts??0));

                $liveMatchup=[
                    'team_name'=>$teamName,
                    'team_side'=>$isAway?'AWAY':'HOME',
                    'team_week'=>(float)($isAway?($pair['away_score']??0):($pair['home_score']??0)),
                    'team_week_changed'=>(bool)($matchupScoreChanges[$fantasyTeamId]->week_fpts_changed??false),
                    'team_today'=>(float)$teamTodayFpts,
                    'team_today_changed'=>$rows->reject(fn($p)=>(bool)$p->is_bench || (bool)$p->is_ir || strtoupper((string)$p->roster_status)==='MINORS')->contains(fn($p)=>(bool)($p->today_fpts_changed??false)),
                    'team_rows'=>$rows,
                    'opponent_name'=>$opponentName,
                    'opponent_side'=>$isAway?'HOME':'AWAY',
                    'opponent_week'=>(float)($isAway?($pair['home_score']??0):($pair['away_score']??0)),
                    'opponent_week_changed'=>(bool)($matchupScoreChanges[$opponentId]->week_fpts_changed??false),
                    'opponent_today'=>(float)$opponentTodayFpts,
                    'opponent_today_changed'=>$opponentRows->reject(fn($p)=>(bool)$p->is_bench || (bool)$p->is_ir || strtoupper((string)$p->roster_status)==='MINORS')->contains(fn($p)=>(bool)($p->today_fpts_changed??false)),
                    'opponent_rows'=>$opponentRows,
                    'caption'=>trim((string)($schedule['caption']??'')),
                ];
                break;
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }

    $targetGroups=\App\Support\AiTips::groups([], $date);
    $targetGroups=collect($targetGroups)->map(function($players,$position)use($pp,$lines,$normName,$normTeam,$date){
        $decorated=collect($players)->map(function($player)use($position,$pp,$lines,$normName,$normTeam,$date){
            $team=$normTeam($player['team']??'');
            $name=$normName($player['name']??'');
            $line=$lines[$team.'|'.$name.'|'.$position]??null;
            $power=$pp[$team.'|'.$name]??null;
            $player['line_number']=$line?(int)$line->line_number:null;
            $player['pp_unit']=$power?(int)$power->pp_unit:null;
            $player['add_url']='https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName='.rawurlencode((string)($player['name']??'')).';positionOrGroup=ALL;';
            $goalieStatus=strtolower(trim((string)($player['starting_status']??'')));
            $player['starting_status_class']=match($goalieStatus){
                'confirmed','starting'=>'goalie-status-confirmed',
                'likely','probable'=>'goalie-status-likely',
                'unconfirmed'=>'goalie-status-unconfirmed',
                'not starting','not_starting'=>'goalie-status-not-starting',
                '', 'na', 'n/a'=>'goalie-status-na',
                default=>'goalie-status-na',
            };
            if($position==='G'){
                $odds=\Illuminate\Support\Facades\DB::table('todays_odds')
                    ->whereDate('game_date',$date)
                    ->whereRaw('UPPER(TRIM(team)) = ?', [$team])
                    ->first();
                $player['vegas_odds']=$odds?->american_odds;
                $player['vegas_odds_class']=$odds && $odds->american_odds!==null
                    ? ($odds->american_odds<=-130?'vegas-odds-good':($odds->american_odds>=130?'vegas-odds-bad':'vegas-odds-even'))
                    : null;
            }
            return $player;
        });

        $decorated=$decorated->filter(function($player)use($position){
            if(!empty($player['injury_status'])) return true;
            $line=$player['line_number']??null;
            return $position==='G'
                ? in_array($line,[1,2],true)
                : in_array($line,[1,2,3,4],true);
        })->values();

        if(in_array($position,['F','D'],true)){
            $decorated=$decorated->sort(function($a,$b){
                $rank=function($player){
                    $pp=$player['pp_unit']??null;
                    $line=$player['line_number']??null;
                    return [
                        $pp===1?1:($pp===2?2:3),
                        in_array($line,[1,2,3,4],true)?$line:99,
                    ];
                };
                $ar=$rank($a);$br=$rank($b);
                if($ar[0]!==$br[0])return $ar[0]<=>$br[0];
                if($ar[1]!==$br[1])return $ar[1]<=>$br[1];
                $ap=$a['projected_points']??-PHP_FLOAT_MAX;
                $bp=$b['projected_points']??-PHP_FLOAT_MAX;
                if($ap!==$bp)return $bp<=>$ap;
                $as=$a['source_rank']??PHP_INT_MAX;
                $bs=$b['source_rank']??PHP_INT_MAX;
                if($as!==$bs)return $as<=>$bs;
                return strcasecmp((string)($a['name']??''),(string)($b['name']??''));
            });
        }else{
            $decorated=$decorated->sort(function($a,$b){
                $rank=function($player){
                    if(!empty($player['not_starting']))return 5;
                    return match(strtolower(trim((string)($player['starting_status']??'')))){
                        'starting','confirmed'=>1,
                        'likely','probable'=>2,
                        'unconfirmed'=>3,
                        '', 'na', 'n/a'=>4,
                        'not starting','not_starting'=>5,
                        default=>4,
                    };
                };
                $ar=$rank($a);$br=$rank($b);
                if($ar!==$br)return $ar<=>$br;
                return (($b['projected_points']??-PHP_FLOAT_MAX)<=>($a['projected_points']??-PHP_FLOAT_MAX))
                    ?: (($a['source_rank']??PHP_INT_MAX)<=>($b['source_rank']??PHP_INT_MAX))
                    ?: strcasecmp((string)($a['name']??''),(string)($b['name']??''));
            });
        }

        return $decorated->values()->all();
    })->all();

    $lastUpdate=$rows->max('last_update');
    $fantasyTeamId=$rows->first()->fantasy_team_id??null;
    $fantraxTeamUrl=$fantasyTeamId?'https://www.fantrax.com/fantasy/league/092zcn40molvao69/team/roster;teamId='.$fantasyTeamId:null;
    $teamChoices=array_map(fn($name)=>['name'=>$name,'slug'=>\Illuminate\Support\Str::slug($name)],$currentNames);
    return view('teams.current',compact('teamName','slug','date','yesterday','today','tomorrow','positions','targetGroups','lastUpdate','scoreLastUpdate','fantraxTeamUrl','teamChoices','teamTodayFpts','liveMatchup'));
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

Route::get('/push/config', function () {
    try {
        return response()->json([
            'publicKey'=>app(\App\Support\WebPush::class)->publicKey(),
            'latestId'=>(int)(DB::table('push_notifications')->max('id')??0),
        ])->header('Cache-Control','no-store');
    } catch (\Throwable $e) {
        report($e);
        return response()->json(['message'=>'Push notifications are temporarily unavailable.'],500);
    }
});

Route::post('/push/subscribe', function () {
    $endpoint=(string)request('endpoint','');
    try {
        $latestId=app(\App\Support\WebPush::class)->subscribe($endpoint);
        return response()->json(['ok'=>true,'latestId'=>$latestId]);
    } catch (\Throwable $e) {
        report($e);
        return response()->json(['ok'=>false,'message'=>$e->getMessage()],422);
    }
});

Route::post('/push/unsubscribe', function () {
    $endpoint=(string)request('endpoint','');
    if($endpoint!=='')app(\App\Support\WebPush::class)->unsubscribe($endpoint);
    return response()->json(['ok'=>true]);
});

Route::get('/push/notifications', function () {
    $after=max(0,(int)request('after',0));
    $rows=DB::table('push_notifications')
        ->where('id','>',$after)
        ->orderBy('id')
        ->limit(25)
        ->get(['id','category','title','body','url']);
    return response()->json(['notifications'=>$rows])->header('Cache-Control','no-store');
});

Route::get('/job-status', function () {
    $tz = 'America/Halifax';
    $now = \Carbon\CarbonImmutable::now($tz);
    $dbTime = fn($value) => $value ? \Carbon\CarbonImmutable::createFromFormat('Y-m-d H:i:s', (string)$value, $tz) : null;
    $format = function($value) use ($dbTime,$now,$tz) {
        $dt=$dbTime($value);
        if(!$dt)return null;
        $dt=$dt->setTimezone($tz);
        $seconds=max(0,(int)floor($dt->diffInSeconds($now)));
        if($seconds<60)return $seconds===1?'1 second ago':$seconds.' seconds ago';
        if($seconds<3600){
            $minutes=(int)floor($seconds/60);
            return $minutes===1?'1 minute ago':$minutes.' minutes ago';
        }
        return $dt->format('M j, Y · g:i:s a T');
    };
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
    $nextQuarterHourly = function () use ($now) {
        $minute=(int)$now->format('i');
        $nextMinute=(int)(ceil(($minute+0.001)/15)*15);
        $next=$nextMinute>=60 ? $now->addHour()->startOfHour() : $now->startOfHour()->minute($nextMinute);
        return $next->format('M j · g:i a T');
    };
    $nextFiveMinutes = function () use ($now) {
        $minute=(int)$now->format('i');
        $nextMinute=(int)(ceil(($minute+0.001)/5)*5);
        $next=$nextMinute>=60 ? $now->addHour()->startOfHour() : $now->startOfHour()->minute($nextMinute);
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
    $scoresLast = DB::table('active_daily_scores')->max('checked_at');
    $standingsLast = DB::table('job_run_history')->where('job_name','ecfhl:refresh-current-standings')->max('completed_at');
    $collectorStates = \Illuminate\Support\Facades\Schema::hasTable('collector_job_statuses')
        ? DB::table('collector_job_statuses')->get()->keyBy('job_key')
        : collect();
    $withOutcome = function(array $job) use ($collectorStates) {
        $row=$collectorStates[$job['key']]??null;
        $job['outcome']=$row?($row->status??null):null;
        $job['outcome_message']=$row?($row->message??null):null;
        $job['outcome_ran_at']=$row?($row->ran_at??null):null;
        return $job;
    };
    $jobs = array_map($withOutcome, [
        ['key'=>'players','name'=>'Fantrax Available Players','schedule'=>'Every 15 minutes (:00, :15, :30, :45)','last_update'=>$format($fantraxLast),'records'=>DB::table('active_daily_players')->count(),'next_run'=>$nextQuarterHourly(),'state'=>$state($fantraxLast,30),'description'=>'Available players playing today and tomorrow, including projected fantasy points.'],
        ['key'=>'goalies','name'=>'Daily Faceoff Goalies','schedule'=>'Every 5 minutes','last_update'=>$format($goaliesLast),'records'=>DB::table('active_starting_goalies')->count(),'next_run'=>$nextFiveMinutes(),'state'=>$state($goaliesLast,12),'description'=>'Starting-goalie status for today and tomorrow.'],
        ['key'=>'lines','name'=>'Daily Faceoff Lines','schedule'=>'Every hour at :00','last_update'=>$format($linesLast),'records'=>DB::table('active_pp_lines')->count(),'next_run'=>$nextHourly(0),'state'=>$state($linesLast,90),'description'=>'Current line combinations and PP1/PP2 assignments for all NHL teams.'],
        ['key'=>'odds','name'=>'NHL Odds','schedule'=>'Every 2 hours at :00','last_update'=>$format($oddsLast),'records'=>DB::table('todays_odds')->count(),'next_run'=>($now->hour%2===0 && $now->minute===0 ? $now->format('M j · g:i a T') : $now->addHours($now->hour%2===0?2:1)->startOfHour()->format('M j · g:i a T')),'state'=>$state($oddsLast,150),'description'=>'Consensus NHL moneyline odds for today and tomorrow from The Odds API.'],
        ['key'=>'teams','name'=>'Fantasy Team Rosters','schedule'=>'Every 15 minutes (:00, :15, :30, :45)','last_update'=>$format($teamsLast),'records'=>DB::table('active_fantasy_rosters')->count(),'next_run'=>$nextQuarterHourly(),'state'=>$state($teamsLast,30),'description'=>'Current Fantrax rosters for every fantasy team, enriched with projections, opponents, injuries, line and power-play assignments.'],
        ['key'=>'scores','name'=>'Live Daily Scores','schedule'=>'Every minute during games','last_update'=>$format($scoresLast),'records'=>DB::table('active_daily_scores')->count(),'next_run'=>'During live game window','state'=>$state($scoresLast,6),'description'=>'Fantrax player FPts and live NHL category stats from first game start until 4 hours after the last game starts.'],
        ['key'=>'standings','name'=>'Current Standings','schedule'=>'Every 5 minutes during games','last_update'=>$format($standingsLast),'records'=>DB::table('team_seasons')->where('season_id','2026-27')->count(),'next_run'=>'Every 5 min during live game window','state'=>$state($standingsLast,15),'description'=>'2026-27 standings and fantasy points from Fantrax scoring-period data, refreshed during the live game window.'],
    ]);
    return response()
        ->view('job-status', compact('jobs'))
        ->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma','no-cache');
});

Route::get('/ai-tips', function () {
    $fantasyDay = \Carbon\CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
    $today = $fantasyDay->toDateString();
    $tomorrow = $fantasyDay->addDay()->toDateString();
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