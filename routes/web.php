<?php

use App\Support\Archive as EcfhlData;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

Route::get('/csrf-token', function () {
    return response()->json(['token'=>csrf_token()])
        ->header('Cache-Control','no-store, no-cache, must-revalidate');
});

Route::post('/admin/lineup-advisor/reset-and-refresh', function () {
    abort_unless(request()->ajax() && request()->headers->get('X-Requested-With') === 'XMLHttpRequest', 403);
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advice'), 503);

    DB::table('lineup_advice')->truncate();
    $exitCode=\Illuminate\Support\Facades\Artisan::call('ecfhl:refresh-lineup-advice');
    $output=trim(\Illuminate\Support\Facades\Artisan::output());
    $count=DB::table('lineup_advice')->count();

    return response()->json([
        'ok'=>$exitCode===0,
        'count'=>$count,
        'output'=>$output,
    ],$exitCode===0?200:500);
});

Route::post('/league-logo', function() {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('team_icons'),503);
    $user=request()->user();
    abort_unless($user && $user->is_admin,403);

    $validated=request()->validate(['image'=>'required|file|mimes:jpg,jpeg,png,webp|max:2048']);
    $file=$validated['image'];
    $bytes=file_get_contents($file->getRealPath());
    abort_if($bytes===false,422,'Could not read image.');
    $dimensions=@getimagesizefromstring($bytes);
    abort_if(!$dimensions || $dimensions[0]*$dimensions[1]>16000000,422,'Use an image with at most 16 million pixels.');

    $mime=$file->getMimeType()?:'image/png';
    DB::table('team_icons')->updateOrInsert(
        ['team_slug'=>'league-logo'],
        ['mime_type'=>$mime,'image_data'=>base64_encode($bytes),'updated_at'=>now(),'created_at'=>now()]
    );
    \App\Support\TeamImages::generate('league-logo',$bytes,$mime);
    return response()->json([
        'ok'=>true,
        'url'=>\App\Support\TeamImages::url('league-logo'),
        'thumbnail_url'=>\App\Support\TeamImages::url('league-logo',160),
    ]);
})->middleware('auth');

Route::post('/team-icons/{slug}', function(string $slug) {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('team_icons'),503);

    $user=request()->user();
    abort_unless($user,401);
    $ownedSlug=$user->claim ? \Illuminate\Support\Str::slug((string)$user->claim->team_name) : null;
    abort_unless($user->is_admin || $ownedSlug===$slug,403);

    $validCurrent=DB::table('team_seasons as ts')
        ->join('seasons as s','s.season_id','=','ts.season_id')
        ->where('s.season_name','2026-27')
        ->pluck('ts.original_name')
        ->contains(fn($name)=>\Illuminate\Support\Str::slug((string)$name)===$slug);
    $validFranchise=DB::table('franchises')->pluck('franchise_name')
        ->contains(fn($name)=>\Illuminate\Support\Str::slug((string)$name)===$slug);
    $advisorSlugAllowed=false;
    if(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles')){
        $advisorKeys=DB::table('lineup_advisor_profiles')->pluck('advisor_key')->all();
        foreach($advisorKeys as $advisorKey){
            $advisorSlug=$advisorKey==='mike'?'lineup-advisor':'lineup-advisor-'.$advisorKey;
            if($slug===$advisorSlug){$advisorSlugAllowed=true;break;}
        }
    }
    abort_unless($slug==='league-logo'||$advisorSlugAllowed||$validCurrent||$validFranchise,404);

    $validated=request()->validate(['image'=>'required|file|mimes:jpg,jpeg,png,webp|max:2048']);
    $file=$validated['image'];
    $bytes=file_get_contents($file->getRealPath());
    abort_if($bytes===false,422,'Could not read image.');
    $dimensions=@getimagesizefromstring($bytes);
    abort_if(!$dimensions || $dimensions[0]*$dimensions[1]>16000000,422,'Use an image with at most 16 million pixels.');

    DB::table('team_icons')->updateOrInsert(
        ['team_slug'=>$slug],
        ['mime_type'=>$file->getMimeType()?:'image/png','image_data'=>base64_encode($bytes),'updated_at'=>now(),'created_at'=>now()]
    );

    \App\Support\TeamImages::generate($slug, $bytes, $file->getMimeType()?:'image/png');
    return response()->json(['ok'=>true,'url'=>\App\Support\TeamImages::url($slug),'thumbnail_url'=>\App\Support\TeamImages::url($slug,160)]);
})->where('slug','[A-Za-z0-9\-]+');

Route::post('/lineup-advisors/{advisor}/profile', function(string $advisor) {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles'),503);
    $profile=DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->first();
    abort_unless($profile,404);

    $validated=request()->validate(['first_name'=>'required|string|max:40']);
    $firstName=trim((string)$validated['first_name']);
    abort_if($firstName==='',422,'First name is required.');

    DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->update([
        'first_name'=>$firstName,
        'updated_at'=>now(),
    ]);

    return response()->json(['ok'=>true,'advisor'=>$advisor,'first_name'=>$firstName]);
})->where('advisor','[a-z0-9\-]+');

Route::get('/admin', fn()=>view('admin.index'));

Route::get('/admin/notifications', function () {
    abort_unless(request()->user()?->is_admin,403);
    return view('admin.notifications');
})->middleware('auth');

Route::post('/admin/notifications/test', function () {
    abort_unless(request()->user()?->is_admin,403);
    $v=request()->validate(['type'=>'required|string|in:team-score,team-goalie-score,opponent-score,own-goalie,all-goalie,available-today,available-tomorrow,watched-goalie']);
    $endpointHash=(string)request()->cookie('ecfhl_push_device','');
    $result=app(\App\Support\WebPush::class)->testType((int)request()->user()->id,$endpointHash,$v['type']);
    return redirect('/admin/notifications')->with('notice',$result['message']);
})->middleware(['auth','throttle:20,1,admin-notification-test']);

Route::get('/admin/teams', function () {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('team_icons'),503);
    $teams=\App\Support\CurrentTeams::administration();
    return view('admin.team-images',compact('teams'));
});
Route::get('/admin/team-images', fn()=>redirect('/admin/teams'));

Route::post('/admin/teams/{teamId}/unlink', function (string $teamId) {
    $expected=request()->validate(['user_id'=>'required|integer']);
    $name=DB::transaction(function () use ($teamId, $expected) {
        $claim=\App\Models\TeamClaim::whereKey($teamId)->lockForUpdate()->first();
        abort_unless($claim,404,'This team no longer has an associated account.');
        abort_unless((int)$claim->user_id===(int)$expected['user_id'],409,'The associated account changed. Refresh this page.');
        // Stop queued alerts for the former team without disabling the account or devices.
        DB::table('push_deliveries')->whereIn('subscription_id',DB::table('push_subscriptions')->where('user_id',$claim->user_id)->select('id'))->delete();
        $name=$claim->team_name;
        $claim->delete();
        return $name;
    });
    return redirect('/admin/teams')->with('notice','Account unlinked from '.$name.'.');
})->where('teamId','[A-Za-z0-9_-]+');

Route::get('/admin/advisors', function () {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles'),503);
    $advisors=DB::table('lineup_advisor_profiles')->orderBy('sort_order')->orderBy('id')->get();
    return view('admin.advisors',compact('advisors'));
});

Route::post('/admin/advisors', function () {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles'),503);
    $validated=request()->validate([
        'first_name'=>'required|string|max:40',
        'style_text'=>'nullable|string|max:5000',
        'recommendation_style'=>'required|in:conservative,neutral,aggressive',
    ]);
    $firstName=trim((string)$validated['first_name']);
    $base=substr(\Illuminate\Support\Str::slug($firstName)?:'advisor',0,20);
    $key=$base;$suffix=2;
    while(DB::table('lineup_advisor_profiles')->where('advisor_key',$key)->exists()){
        $suffixText='-'.$suffix++;
        $key=substr($base,0,20-strlen($suffixText)).$suffixText;
    }
    $nextOrder=((int)DB::table('lineup_advisor_profiles')->max('sort_order'))+10;
    DB::table('lineup_advisor_profiles')->insert([
        'advisor_key'=>$key,
        'first_name'=>$firstName,
        'style_text'=>trim((string)($validated['style_text']??'')),
        'recommendation_style'=>(string)$validated['recommendation_style'],
        'is_conservative'=>$validated['recommendation_style']==='conservative',
        'sort_order'=>$nextOrder,
        'created_at'=>now(),
        'updated_at'=>now(),
    ]);
    return redirect('/admin/advisors')->with('notice',$firstName.' added.');
});

Route::post('/admin/advisors/{advisor}', function(string $advisor) {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles'),503);
    abort_unless(DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->exists(),404);
    $validated=request()->validate([
        'first_name'=>'required|string|max:40',
        'style_text'=>'nullable|string|max:5000',
        'recommendation_style'=>'required|in:conservative,neutral,aggressive',
    ]);
    DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->update([
        'first_name'=>trim((string)$validated['first_name']),
        'style_text'=>trim((string)($validated['style_text']??'')),
        'recommendation_style'=>(string)$validated['recommendation_style'],
        'is_conservative'=>$validated['recommendation_style']==='conservative',
        'updated_at'=>now(),
    ]);
    return redirect('/admin/advisors')->with('notice','Advisor updated.');
})->where('advisor','[a-z0-9\-]+');

Route::post('/admin/advisors/{advisor}/image', function(string $advisor) {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles'),503);
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('team_icons'),503);

    $profile=DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->first();
    abort_unless($profile,404);

    $validated=request()->validate([
        'image'=>'required|file|mimes:jpg,jpeg,png,webp|max:2048',
    ]);
    $file=$validated['image'];
    $bytes=file_get_contents($file->getRealPath());
    abort_if($bytes===false,422,'Could not read image.');
    $dimensions=@getimagesizefromstring($bytes);
    abort_if(!$dimensions || $dimensions[0]*$dimensions[1]>16000000,422,'Use an image with at most 16 million pixels.');

    $slug=$advisor==='mike'?'lineup-advisor':'lineup-advisor-'.$advisor;
    DB::table('team_icons')->updateOrInsert(
        ['team_slug'=>$slug],
        [
            'mime_type'=>$file->getMimeType()?:'image/png',
            'image_data'=>base64_encode($bytes),
            'updated_at'=>now(),
            'created_at'=>now(),
        ]
    );

    \App\Support\TeamImages::generate($slug,$bytes,$file->getMimeType()?:'image/png');
    return redirect('/admin/advisors')->with('notice',$profile->first_name.' image updated.');
})->where('advisor','[a-z0-9\-]+');

Route::delete('/admin/advisors/{advisor}', function(string $advisor) {
    abort_unless(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles'),503);
    abort_if(DB::table('lineup_advisor_profiles')->count()<=1,422,'At least one advisor is required.');
    $profile=DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->first();
    abort_unless($profile,404);
    DB::table('lineup_advisor_profiles')->where('advisor_key',$advisor)->delete();
    if(\Illuminate\Support\Facades\Schema::hasTable('team_icons')){
        $slug=$advisor==='mike'?'lineup-advisor':'lineup-advisor-'.$advisor;
        DB::table('team_icons')->where('team_slug',$slug)->delete();
    }
    return redirect('/admin/advisors')->with('notice',$profile->first_name.' removed.');
})->where('advisor','[a-z0-9\-]+');

Route::get('/', function () {
    $standings=\App\Support\CurrentTeams::standings();
    $today=app(\App\Support\FantasyDay::class)->today()->toDateString();
    $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($today);
    $currentPeriod=DB::table('scoring_period_matchups')->where('season_id','2026-27')
        ->where('start_date','<=',$today)->where('end_date','>=',$today)->orderBy('period_number')->first();
    $matchups=$currentPeriod ? DB::table('scoring_period_matchups')->where('season_id','2026-27')->where('period_number',$currentPeriod->period_number)->get() : collect();
    $scoringLeaders=app(\App\Support\SeasonPlayers::class)->homeRecommendations($today);
    $topScoring=collect($standings)->filter(fn($t)=>$t['fantasy_points_for']!==null)->sortByDesc('fantasy_points_for')->first();
    $games=app(\App\Support\PlayerGames::class)->forDate($today);
    return view('home',compact('standings','today','snapshot','currentPeriod','matchups','scoringLeaders','topScoring','games'));
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
    $season=(array)DB::table('seasons')->where('season_id',$seasonName)->first();
    if(!$season)return redirect('/seasons');
    $season['season']=$seasonName;

    $standings=\App\Support\CurrentTeams::standings();
    $standingsLastUpdate=DB::table('job_run_history')
        ->where('job_name','ecfhl:refresh-current-standings')
        ->max('completed_at');

    $currentPeriodNumber=null;
    $scoringPeriods=[];

    try {
        if(\Illuminate\Support\Facades\Schema::hasTable('scoring_period_matchups')){
            // The scheduled collector fills dates; page views never wait on upstream requests.

            $rows=\App\Support\PublicData::remember('current-periods',30,fn()=>DB::table('scoring_period_matchups')
                ->where('season_id',$seasonName)->orderBy('period_number')->orderBy('id')->get());

            $scoringPeriods=$rows->groupBy('period_number')->map(function($group,$periodNumber){
                $first=$group->first();
                return [
                    'caption'=>'Scoring Period '.$periodNumber,
                    'period_number'=>(int)$periodNumber,
                    'start'=>$first->start_date ? (string)$first->start_date : null,
                    'end'=>$first->end_date ? (string)$first->end_date : null,
                    'matchups'=>$group->map(fn($r)=>[
                        'away_name'=>(string)$r->away_team_name,
                        'away_display'=>(string)$r->away_team_name,
                        'away_score'=>$r->away_score!==null?(float)$r->away_score:null,
                        'home_name'=>(string)$r->home_team_name,
                        'home_display'=>(string)$r->home_team_name,
                        'home_score'=>$r->home_score!==null?(float)$r->home_score:null,
                    ])->values()->all(),
                ];
            })->values()->all();

            $fantasyToday=app(\App\Support\FantasyDay::class)->today();
            foreach($scoringPeriods as $period){
                if(empty($period['start'])||empty($period['end']))continue;
                $periodStart=\Carbon\CarbonImmutable::parse($period['start'],'America/Halifax')->startOfDay();
                $periodEnd=\Carbon\CarbonImmutable::parse($period['end'],'America/Halifax')->endOfDay();
                if($fantasyToday->betweenIncluded($periodStart,$periodEnd)){
                    $currentPeriodNumber=(int)$period['period_number'];
                    break;
                }
            }
        }
    } catch (\Throwable $e) {
        report($e);
    }

    $awardRaces=\App\Support\PublicData::remember('standings-awards',60,function(){
        $latest=DB::table('active_fantasy_rosters')->select('player_id')->selectRaw('MAX(id) as roster_id')
            ->where('game_date',fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))->groupBy('player_id');
        $races=[];
        foreach([
            'art_ross'=>['Art Ross','🏒','Forwards','F'],
            'norris'=>['Norris','🛡️','Defense','D'],
            'vezina'=>['Vezina','🥅','Goalies','G'],
            'calder'=>['Calder','🌱','Rookies',null],
        ] as $key=>[$label,$icon,$detail,$position]){
            $query=DB::table('season_player_stats as s')->leftJoinSub($latest,'latest','latest.player_id','=','s.player_id')
                ->leftJoin('active_fantasy_rosters as r','r.id','=','latest.roster_id');
            if($position)$query->where('s.position',$position);else $query->where('s.rookie',true);
            $leaders=$query->orderByDesc('s.season_fpts')->orderByDesc('s.season_fpts_per_game')->orderBy('s.player_id')->limit(3)
                ->get(['s.player_id','s.player_name as name','s.nhl_team','r.fantasy_team_name as fantasy_team','s.season_fpts as fpts','s.season_gp as gp','s.season_fpts_per_game as fpts_g'])
                ->map(fn($p)=>(array)$p)->all();
            $races[$key]=compact('label','icon','detail','leaders');
        }
        return $races;
    });

    foreach(['president'=>["President's Trophy",'🏆','Regular-season standings'], 'top_scoring'=>['Top Scoring Team','🔥','Season fantasy points']] as $key=>[$label,$icon,$detail]){
        $teamLeaders=$key==='president'?collect($standings)->filter(fn($t)=>$t['rank']!==null):collect($standings)->filter(fn($t)=>$t['fantasy_points_for']!==null)->sortByDesc('fantasy_points_for');
        $awardRaces[$key]=['label'=>$label,'icon'=>$icon,'detail'=>$detail,'team_award'=>true,'leaders'=>$teamLeaders->take(3)->map(fn($t)=>[
            'name'=>$t['team'],'rank'=>$t['rank'],'record'=>($t['w']??0).'-'.($t['l']??0).'-'.($t['t']??0),'fpts'=>$t['fantasy_points_for'],
        ])->values()->all()];
    }
    $viewData=compact('season','standings','standingsLastUpdate','scoringPeriods','currentPeriodNumber','awardRaces');
    return view(request()->ajax()?'partials.standings-content':'standings',$viewData);
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
Route::get('/teams/league', fn()=>view('teams.league', ['teams'=>\App\Support\CurrentTeams::standings()]));
Route::get('/teams/current', \App\Http\Controllers\LiveScoringController::class);

Route::get('/api/live-scoring', function (\App\Support\FantasyDay $days, \App\Support\LiveScoring\SnapshotRepository $repository) {
    $dates = $days->dates();
    $date = (string)request('date', $dates['today']);
    abort_unless(in_array($date, $dates, true), 422);
    return response()->json(['fantasy_timezone'=>\App\Support\FantasyDay::TIMEZONE, 'dates'=>$dates, 'selected_date'=>$date, 'snapshot'=>$repository->get($date)])
        ->header('Cache-Control','no-store, no-cache, must-revalidate');
});

Route::get('/teams/current/{slug}', function(string $slug) {
    $tz='America/Halifax';
    $fantasyDay=app(\App\Support\FantasyDay::class)->today();
    $today=$fantasyDay->toDateString();
    $yesterday=$fantasyDay->subDay()->toDateString();
    $tomorrow=$fantasyDay->addDay()->toDateString();
    $date=(string)request('date',$today);
    if(!in_array($date,[$yesterday,$today,$tomorrow],true))$date=$today;

    $currentNames=\App\Support\PublicData::teamMenu();

    $teamName=null;
    foreach($currentNames as $name){
        if(\Illuminate\Support\Str::slug($name)===$slug){$teamName=$name;break;}
    }
    abort_unless($teamName,404);

    $rows=DB::table('active_fantasy_rosters')
        ->where('game_date',$date)
        ->where('fantasy_team_name',$teamName)
        ->get();

    // Keep actual roster slots before daily scoring membership is merged into display rows.
    $rosterCountRows=$rows->unique('player_id')->values();

    $snapshot=app(\App\Support\LiveScoring\SnapshotRepository::class)->get($date);
    $presenter=app(\App\Support\LiveScoring\ViewData::class);
    $fantasyTeamId=(string)(collect($snapshot['teams']??[])->first(fn($t)=>$t['name']===$teamName)['id']??$rows->first()->fantasy_team_id??'');
    $dailyPlayers=collect($snapshot['players']??[])->where('fantasy_team_id',$fantasyTeamId)->map(fn($p)=>$presenter->player($p))->keyBy('player_id');
    $scoreLastUpdate=$snapshot['collected_at']??null;
    $rows=$rows->map(function($p)use($dailyPlayers){
        $daily=$dailyPlayers[(string)$p->player_id]??null;
        if($daily)return (object)array_merge((array)$p,(array)$daily);
        $p->daily_participant=false;
        $p->today_fpts=0;
        $p->today_fpts_changed=false;
        $p->live_opponent_display=null;
        $p->game_finished=false;
        $p->game_in_progress=false;
        foreach(['gp','g','a','ppg','shg','gwg','w','so'] as $stat)$p->{'today_'.$stat}=0;
        return $p;
    });
    foreach($dailyPlayers as $id=>$daily){
        if(!$rows->contains(fn($p)=>(string)$p->player_id===(string)$id))$rows->push($daily);
    }

    $normName=function($v){$name=trim((string)$v);if(str_contains($name,',')){[$last,$first]=array_map('trim',explode(',',$name,2));if($first!==''&&$last!=='')$name=$first.' '.$last;}return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';};
    $normTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
    $pp=\App\Support\PublicData::remember('badges:active_pp_lines',30,fn()=>DB::table('active_pp_lines')->get())->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name));
    $lines=\App\Support\PublicData::remember('badges:active_line_combinations',30,fn()=>DB::table('active_line_combinations')->get())->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name).'|'.strtoupper(trim($r->position_group)));
    $oddsByTeam=\App\Support\PublicData::remember('badges:todays_odds:'.$date,30,fn()=>DB::table('todays_odds')->where('game_date',$date)->get())->keyBy(fn($r)=>$normTeam($r->team));
    $goalieStatusByPlayer=DB::table('active_starting_goalies')
        ->where('game_date',$date)
        ->get()
        ->keyBy(fn($r)=>$normTeam($r->team).'|'.$normName($r->player_name));

    $decorate=function($p)use($pp,$lines,$normName,$normTeam,$oddsByTeam,$goalieStatusByPlayer){
        $team=$normTeam($p->nhl_team);
        $name=$normName($p->player_name);
        $pos=strtoupper(trim((string)$p->position));
        $line=$lines[$team.'|'.$name.'|'.$pos]??null;
        $power=$pp[$team.'|'.$name]??null;
        $p->line_number=$line?(int)$line->line_number:null;
        $p->pp_unit=$power?(int)$power->pp_unit:null;
        $p->starting_status=null;
        $p->starting_status_class='goalie-status-na';
        if($pos==='G'){
            $goalieRow=$goalieStatusByPlayer[$team.'|'.$name]??null;
            if($goalieRow){
                $rawStatus=ucfirst(strtolower(trim((string)$goalieRow->starting_status)));
                if($rawStatus==='Probable')$rawStatus='Likely';
                $p->starting_status=$rawStatus;
                $p->starting_status_class=match(strtolower($rawStatus)){
                    'confirmed'=>'goalie-status-confirmed',
                    'likely'=>'goalie-status-likely',
                    'unconfirmed'=>'goalie-status-unconfirmed',
                    'not starting'=>'goalie-status-not-starting',
                    default=>'goalie-status-na',
                };
            }
        }
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
    $rows=$rows->map($decorate)->map(function($p){$p->is_ir=strtoupper((string)$p->roster_status)==='INJURED_RESERVE';return $p;});
    $rows=(new \App\Support\PlayerProjections)->decorate($rows);

    $positions=[];
    foreach(['F'=>'Forwards','D'=>'Defense','G'=>'Goalies'] as $code=>$label){
        $positionRows=\App\Support\LiveScoring\ViewData::sortPlayers($rows->where('position',$code)->reject(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS'));
        $positions[$code]=['label'=>$label,'rows'=>$positionRows];
    }
    $minorRows=\App\Support\LiveScoring\ViewData::sortPlayers($rows->filter(fn($p)=>strtoupper((string)$p->roster_status)==='MINORS'));
    $positions['M']=['label'=>'Minors','rows'=>$minorRows];
    $teamTodayFpts=$snapshot['teams'][$fantasyTeamId]['daily_fpts']??0;

    $liveMatchup=null;
    $liveTeams=$snapshot?$presenter->teams($snapshot):[];
    foreach(($snapshot['matchups']??[]) as $pair){
        $isAway=$pair['away_team_id']===$fantasyTeamId;
        if(!$isAway && $pair['home_team_id']!==$fantasyTeamId)continue;
        $own=$liveTeams[$fantasyTeamId];
        $opponentId=$isAway?$pair['home_team_id']:$pair['away_team_id'];
        $other=$liveTeams[$opponentId];
        $allPlayers=fn($team)=>collect($team['positions'])->flatMap(fn($group)=>$group['rows'])->values();
        $liveMatchup=[
            'team_name'=>$own['name'],'team_side'=>$isAway?'AWAY':'HOME',
            'team_week'=>$own['week_fpts'],'team_week_changed'=>$own['week_fpts_changed'],
            'team_today'=>$own['today_fpts'],'team_today_changed'=>$own['today_fpts_changed'],'team_rows'=>$allPlayers($own),
            'opponent_name'=>$other['name'],'opponent_side'=>$isAway?'HOME':'AWAY',
            'opponent_week'=>$other['week_fpts'],'opponent_week_changed'=>$other['week_fpts_changed'],
            'opponent_today'=>$other['today_fpts'],'opponent_today_changed'=>$other['today_fpts_changed'],'opponent_rows'=>$allPlayers($other),
            'caption'=>'Scoring period '.$snapshot['period'].' '.$snapshot['period_label'],
        ];
        break;
    }

    $targetGroups=\App\Support\AiTips::groups([], $date);
    $targetGroups=collect($targetGroups)->map(function($players,$position)use($pp,$lines,$normName,$normTeam,$date,$oddsByTeam){
        $decorated=collect($players)->map(function($player)use($position,$pp,$lines,$normName,$normTeam,$date,$oddsByTeam){
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
                $odds=$oddsByTeam[$team]??null;
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
                    return $pp===1?1:($pp===2?2:3);
                };
                $ar=$rank($a);$br=$rank($b);
                if($ar!==$br)return $ar<=>$br;
                $ap=$a['projected_points']??-PHP_FLOAT_MAX;
                $bp=$b['projected_points']??-PHP_FLOAT_MAX;
                if($ap!==$bp)return $bp<=>$ap;
                $al=$a['line_number']??99;
                $bl=$b['line_number']??99;
                if($al!==$bl)return $al<=>$bl;
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

    $nextWeekOpponent=null;
    try {
        if(\Illuminate\Support\Facades\Schema::hasTable('scoring_period_matchups')){
            $fantasyToday=app(\App\Support\FantasyDay::class)->today();
            $currentPeriod=DB::table('scoring_period_matchups')
                ->where('season_id','2026-27')
                ->whereNotNull('start_date')
                ->where('start_date','<=',$fantasyToday->toDateString())
                ->where('end_date','>=',$fantasyToday->toDateString())
                ->value('period_number');

            if($currentPeriod){
                $normalizeFantasyTeamName=fn($v)=>preg_replace('/[^\\pL\\pN]+/u','',mb_strtolower((string)$v))??'';
                $teamKey=$normalizeFantasyTeamName($teamName);
                $nextRows=DB::table('scoring_period_matchups')
                    ->where('season_id','2026-27')
                    ->where('period_number',(int)$currentPeriod+1)
                    ->get();

                foreach($nextRows as $nextRow){
                    $awayKey=$normalizeFantasyTeamName($nextRow->away_team_name);
                    $homeKey=$normalizeFantasyTeamName($nextRow->home_team_name);
                    if($awayKey===$teamKey){
                        $nextWeekOpponent=[
                            'period'=>(int)$nextRow->period_number,
                            'opponent'=>(string)$nextRow->home_team_name,
                            'side'=>'AWAY',
                            'start_date'=>$nextRow->start_date,
                            'end_date'=>$nextRow->end_date,
                        ];
                        break;
                    }
                    if($homeKey===$teamKey){
                        $nextWeekOpponent=[
                            'period'=>(int)$nextRow->period_number,
                            'opponent'=>(string)$nextRow->away_team_name,
                            'side'=>'HOME',
                            'start_date'=>$nextRow->start_date,
                            'end_date'=>$nextRow->end_date,
                        ];
                        break;
                    }
                }
            }
        }
    } catch (\Throwable $e) {
        report($e);
    }

    if($nextWeekOpponent){
        $nextWeekOpponent['standings']=collect(\App\Support\CurrentTeams::standings())->first(fn($t)=>\Illuminate\Support\Str::slug($t['team'])===\Illuminate\Support\Str::slug($nextWeekOpponent['opponent']));
    }
    $futurePicks=app(\App\Support\FutureDraftPicks::class)->forTeam($teamName,2027);
    $nextWeekLineup=['F'=>collect(),'D'=>collect(),'G'=>collect(),'Minors'=>collect()];
    if(!empty($nextWeekOpponent)){
        try{
            $oppKey=preg_replace('/[^\\pL\\pN]+/u','',mb_strtolower((string)$nextWeekOpponent['opponent']))??'';
            // active_fantasy_rosters is the current snapshot and has no date column.
            $oppRows=DB::table('active_fantasy_rosters')
                ->get()
                ->filter(fn($p)=>(preg_replace('/[^\\pL\\pN]+/u','',mb_strtolower((string)($p->fantasy_team_name??'')))??'')===$oppKey)
                // The snapshot can contain repeated rows for the same player.
                // The opponent card should show each current roster player once.
                ->unique(function($p){
                    $playerId=trim((string)($p->player_id??''));
                    if($playerId!=='')return 'id:'.$playerId;
                    return 'name:'.mb_strtolower(trim((string)($p->player_name??'')));
                })
                ->values();
            $oppRows=(new \App\Support\PlayerProjections)->decorate($oppRows);
            foreach($oppRows as $p){
                $status=strtoupper((string)($p->roster_status??''));
                if($status==='MINORS'){
                    $nextWeekLineup['Minors']->push($p);
                    continue;
                }
                $pos=strtoupper((string)($p->position??''));
                if(isset($nextWeekLineup[$pos]))$nextWeekLineup[$pos]->push($p);
            }
        }catch(\Throwable $e){report($e);}
    }

    $rosterCounts=[
        'F'=>0,
        'D'=>0,
        'G'=>0,
        'Bench'=>0,
        'IR'=>0,
        'Minors'=>0,
    ];
    foreach($rosterCountRows as $rosterPlayer){
        $status=strtoupper((string)($rosterPlayer->roster_status??''));
        if($status==='MINORS'){
            $rosterCounts['Minors']++;
        } elseif($status==='INJURED_RESERVE'){
            $rosterCounts['IR']++;
        } elseif(in_array($status,['RESERVE','BENCH'],true)){
            $rosterCounts['Bench']++;
        } else {
            $position=strtoupper((string)($rosterPlayer->position??''));
            if(isset($rosterCounts[$position]))$rosterCounts[$position]++;
        }
    }

    $movesLeftToday=null;
    try {
        if(\Illuminate\Support\Facades\Schema::hasTable('team_daily_moves')){
            $movesLeftToday=DB::table('team_daily_moves')
                ->where('move_date',$today)
                ->where('fantasy_team_id',(string)($rows->first()->fantasy_team_id??''))
                ->value('moves_left');
            $movesLeftToday=$movesLeftToday!==null?(int)$movesLeftToday:null;
        }
    } catch (\Throwable $e) {
        report($e);
    }

    $lineupAdvice=null;
    try {
        if(\Illuminate\Support\Facades\Schema::hasTable('lineup_advice')){
            $adviceTeamId=(string)($rows->first()->fantasy_team_id??'');
            if($adviceTeamId!==''){
                $lineupAdvice=DB::table('lineup_advice')
                    ->where('advice_date',$today)
                    ->where('fantasy_team_id',$adviceTeamId)
                    ->first();
            }
        }
    } catch (\Throwable $e) {
        report($e);
    }

    $lastUpdate=$rows->max('last_update');
    $fantasyTeamId=$rows->first()->fantasy_team_id??null;
    $fantraxTeamUrl=$fantasyTeamId?'https://www.fantrax.com/fantasy/league/092zcn40molvao69/team/roster;teamId='.$fantasyTeamId:null;
    $teamChoices=array_map(fn($name)=>['name'=>$name,'slug'=>\Illuminate\Support\Str::slug($name)],$currentNames);
    $advisorProfiles=collect([
        (object)['advisor_key'=>'mike','first_name'=>'Mike','style_text'=>'','is_conservative'=>false,'sort_order'=>10],
    ]);
    if(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles')){
        $advisorProfiles=DB::table('lineup_advisor_profiles')->orderBy('sort_order')->orderBy('id')->get();
    }
    return view('teams.current',compact('teamName','slug','date','yesterday','today','tomorrow','positions','targetGroups','lastUpdate','scoreLastUpdate','fantraxTeamUrl','teamChoices','teamTodayFpts','liveMatchup','nextWeekOpponent','nextWeekLineup','lineupAdvice','movesLeftToday','rosterCounts','advisorProfiles','futurePicks'));
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
Route::get('/players/history',function(EcfhlData $data){$q=trim((string)request('q',''));$events=$q!==''?$data->playerHistory($q):[];$counts=['overall1'=>[],'trades'=>[],'round1'=>[]];foreach($data->draftSeason('all') as $p){$name=trim((string)($p['player']??''));if($name==='')continue;$key=mb_strtolower($name);if((int)($p['overall']??0)===1){$counts['overall1'][$key]['name']=$name;$counts['overall1'][$key]['count']=($counts['overall1'][$key]['count']??0)+1;}if((int)($p['round']??0)===1){$counts['round1'][$key]['name']=$name;$counts['round1'][$key]['count']=($counts['round1'][$key]['count']??0)+1;}}foreach($data->trades() as $t){if(!empty($t['vetoed']))continue;foreach(['from_items','to_items'] as $field){foreach(($t[$field]??[]) as $item){$name=trim((string)$item);if($name===''||preg_match('/draft\s+pick|round\s*\d|\b1st\b|\b2nd\b|\b3rd\b/i',$name))continue;$name=trim((string)preg_replace('/\s*\((?:FA|MINORS?|TBD|[1-4]\s+Years?)\)\s*$/i','',$name));if($name==='')continue;$key=mb_strtolower($name);$counts['trades'][$key]['name']=$name;$counts['trades'][$key]['count']=($counts['trades'][$key]['count']??0)+1;}}}$playerLeaders=[];foreach($counts as $type=>$rows){$list=[];foreach($rows as $row)$list[]=['team'=>$row['name'],'value'=>$row['count'],'score'=>$row['count']];usort($list,fn($a,$b)=>($b['score']<=>$a['score'])?:strnatcasecmp($a['team'],$b['team']));$playerLeaders[$type]=$list;}return view('players',compact('q','events','playerLeaders'));});
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
    $v=request()->validate(['endpoint'=>'required|string|max:2048|url:https']);
    // Only known push gateways may be contacted; never send HTTP requests to user-supplied hosts.
    $host=strtolower((string)parse_url($v['endpoint'],PHP_URL_HOST));
    abort_unless($host==='fcm.googleapis.com' || $host==='updates.push.services.mozilla.com' || $host==='web.push.apple.com' || str_ends_with($host,'.notify.windows.com'),422,'Unsupported browser push gateway.');
    $token=\Illuminate\Support\Str::random(64);
    $latestId=app(\App\Support\WebPush::class)->subscribe($v['endpoint'],request()->user()->id,$token);
    request()->session()->put('push_endpoint_hash',hash('sha256',$v['endpoint']));
    return response()->json(['ok'=>true,'latestId'=>$latestId,'feedToken'=>$token])->cookie('ecfhl_push_device',hash('sha256',$v['endpoint']),525600,'/',null,true,true,false,'lax');
})->middleware(['auth','throttle:20,1,push-subscribe']);

Route::get('/push/device',function(){
 $enabled=DB::table('push_subscriptions')->where('user_id',request()->user()->id)->where('endpoint_hash',request()->cookie('ecfhl_push_device'))->where('enabled',true)->whereNotNull('feed_token_hash')->exists();
 return response()->json(['enabled'=>$enabled]);
})->middleware('auth');

Route::post('/push/team', fn()=>response()->json(['ok'=>true]))->middleware('auth');

Route::post('/push/unsubscribe', function () {
    $endpoint=(string)request('endpoint','');
    DB::table('push_subscriptions')->where('user_id',request()->user()->id)->where('endpoint_hash',hash('sha256',$endpoint))->delete();
    return response()->json(['ok'=>true]);
})->middleware('auth');

Route::get('/push/notifications', function () {
    $token=request()->bearerToken();
    $subscription=$token?DB::table('push_subscriptions')->where('feed_token_hash',hash('sha256',$token))->where('enabled',true)->first():null;
    if(!$subscription)return response()->json(['notifications'=>[],'latestId'=>0])->header('Cache-Control','no-store');
    $owner=\App\Models\User::find($subscription->user_id);
    $preferences=$owner?\App\Support\Messaging::preferences($owner):[];
    if(!$owner||!($preferences['notifications_enabled']??true))return response()->json(['notifications'=>[]])->header('Cache-Control','no-store');
    $after=max(0,(int)request('after',0));
    $rows=DB::table('push_deliveries as d')->join('push_notifications as n','n.id','=','d.notification_id')
        ->where('d.subscription_id',$subscription->id)->where('n.id','>',$after)->orderBy('n.id')->limit(100)
        ->get(['n.id','n.category','n.title','n.body','n.url','n.fantasy_team_id']);
    $rows=$rows->filter(fn($n)=>($n->category!=='private-message'||$preferences['private_message_push'])&&($n->category!=='league-message'||$preferences['league_message_push']))->values();
    return response()->json(['notifications'=>$rows])->header('Cache-Control','no-store');
})->middleware('throttle:120,1,push-feed');

Route::get('/api/player-projections', function () {
    $players=DB::table('player_projections as p')
        ->join('player_projection_baselines as b','b.player_id','=','p.player_id')
        ->select('b.*','p.as_of_date','p.window_end_date','p.fpts_7d','p.gp_7d','p.fpts_per_game_7d',
            'p.fpts_14d','p.gp_14d','p.fpts_per_game_14d','p.fpts_21d','p.gp_21d','p.fpts_per_game_21d',
            'p.season_fpts','p.season_gp','p.season_fpts_per_game','p.projected_fpts_per_game','p.refreshed_at')->orderBy('b.source_rank')->get();
    return response()->json(['formula'=>\App\Support\ProjectionSettings::description(), 'weights'=>\App\Support\ProjectionSettings::weights(),
        'timezone'=>'America/Halifax','count'=>$players->count(),'players'=>$players])
        ->header('Cache-Control','no-store');
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
    $scoresLast = DB::table('live_scoring_snapshots')->max('collected_at');
    $scoresLast = $scoresLast ? \Carbon\CarbonImmutable::parse($scoresLast, 'UTC')->setTimezone($tz)->format('Y-m-d H:i:s') : null;
    $standingsLast = DB::table('job_run_history')->where('job_name','ecfhl:refresh-current-standings')->max('completed_at');
    $advisorLast = \Illuminate\Support\Facades\Schema::hasTable('lineup_advice') ? DB::table('lineup_advice')->max('generated_at') : null;
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
        ['key'=>'projections','name'=>'Regenerate Projected FPts','schedule'=>'Daily at 4:00 a.m. Atlantic','last_update'=>$format(DB::table('player_projections')->max('refreshed_at')),'records'=>DB::table('player_projections')->count(),'next_run'=>(function()use($now){$next=$now->startOfDay()->setTime(4,0);if($next->lte($now))$next=$next->addDay();return $next->format('M j · g:i a T');})(),'state'=>$state(DB::table('player_projections')->max('refreshed_at'),1560),'description'=>'All collected players: '.\App\Support\ProjectionSettings::description().'. Missing sources are excluded and remaining weights scale to 100%. Recorded zero-game stats contribute zero.'],
        ['key'=>'players','name'=>'Fantrax Available Players','schedule'=>'Every 15 minutes (:00, :15, :30, :45)','last_update'=>$format($fantraxLast),'records'=>DB::table('active_daily_players')->count(),'next_run'=>$nextQuarterHourly(),'state'=>$state($fantraxLast,30),'description'=>'Available players playing today and tomorrow, including projected fantasy points.'],
        ['key'=>'goalies','name'=>'Daily Faceoff Goalies','schedule'=>'Every 5 minutes','last_update'=>$format($goaliesLast),'records'=>DB::table('active_starting_goalies')->count(),'next_run'=>$nextFiveMinutes(),'state'=>$state($goaliesLast,12),'description'=>'Starting-goalie status for today and tomorrow.'],
        ['key'=>'lines','name'=>'Daily Faceoff Lines','schedule'=>'Every hour at :00','last_update'=>$format($linesLast),'records'=>DB::table('active_pp_lines')->count(),'next_run'=>$nextHourly(0),'state'=>$state($linesLast,90),'description'=>'Current line combinations and PP1/PP2 assignments for all NHL teams.'],
        ['key'=>'odds','name'=>'NHL Odds','schedule'=>'Every 2 hours at :00','last_update'=>$format($oddsLast),'records'=>DB::table('todays_odds')->count(),'next_run'=>($now->hour%2===0 && $now->minute===0 ? $now->format('M j · g:i a T') : $now->addHours($now->hour%2===0?2:1)->startOfHour()->format('M j · g:i a T')),'state'=>$state($oddsLast,150),'description'=>'Consensus NHL moneyline odds for today and tomorrow from The Odds API.'],
        ['key'=>'teams','name'=>'Fantasy Team Rosters','schedule'=>'Every 15 minutes (:00, :15, :30, :45)','last_update'=>$format($teamsLast),'records'=>DB::table('active_fantasy_rosters')->count(),'next_run'=>$nextQuarterHourly(),'state'=>$state($teamsLast,30),'description'=>'Current Fantrax rosters for every fantasy team, enriched with projections, opponents, injuries, line and power-play assignments.'],
        ['key'=>'scores','name'=>'Fantrax Live Scoring','schedule'=>'Every minute during games; hourly when idle','last_update'=>$format($scoresLast),'records'=>(int)DB::table('live_scoring_snapshots')->sum('player_count'),'next_run'=>'1 min live / 1 hour idle','state'=>$state($scoresLast,70),'description'=>'Independent yesterday, today and tomorrow Fantrax lineups, daily scores, period scores, projections and game states. Fantasy dates roll over at Pacific midnight.'],
        ['key'=>'standings','name'=>'Current Standings','schedule'=>'Every minute during games; hourly when idle; completed days only','last_update'=>$format($standingsLast),'records'=>DB::table('team_seasons')->where('season_id','2026-27')->count(),'next_run'=>'1 min live / 1 hour idle','state'=>$state($standingsLast,70),'description'=>'2026-27 standings use finalized scoring days. Daily points update after the last NHL game finishes; W/L/T update after the scoring period ends.'],
        ['key'=>'advisor','name'=>'Regenerate Lineup Advisor','schedule'=>'Every hour','last_update'=>$format($advisorLast),'records'=>\Illuminate\Support\Facades\Schema::hasTable('lineup_advice')?DB::table('lineup_advice')->where('advice_date',app(\App\Support\FantasyDay::class)->today()->toDateString())->count():0,'next_run'=>(function()use($now){return $now->addHour()->startOfHour()->format('M j · g:i a T');})(),'state'=>$state($advisorLast,90),'description'=>'Rebuilds lineup recommendations for every current fantasy team using moves left, roster construction, injuries, available players, projections, goalie coverage, and matchup context.'],
    ]);
    return response()
        ->view('job-status', compact('jobs'))
        ->header('Cache-Control','no-store, no-cache, must-revalidate, max-age=0')
        ->header('Pragma','no-cache');
});

Route::get('/ai-tips', function () {
    $fantasyDay = app(\App\Support\FantasyDay::class)->today();
    $today = $fantasyDay->toDateString();
    $tomorrow = $fantasyDay->addDay()->toDateString();
    $date = request('date', $today);
    abort_unless(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date), 422, 'Use a valid game date.');
    if (!in_array($date, [$today, $tomorrow], true)) $date = $today;
    $selected = $date === $tomorrow ? 'tomorrow' : 'today';
    $fantraxRows = DB::table('active_daily_players')->where('game_date',$date)->orderByRaw('projected_fpts IS NULL')->orderByDesc('projected_fpts')->orderBy('source_rank')->get();
    $norm = fn($v)=>preg_replace('/[^\pL\pN]+/u','',mb_strtolower(trim((string)$v)))??'';
    $ppRows = DB::table('active_pp_lines')->get();
    $ppByTeam=[]; foreach($ppRows as $p){$ppByTeam[strtoupper($p->team)][$norm($p->player_name)] = 'PP'.(int)$p->pp_unit;}
    $decorate=function($rows)use($ppByTeam,$norm){return $rows->map(function($p)use($ppByTeam,$norm){$p->pp_unit=$ppByTeam[strtoupper($p->team)][$norm($p->player_name)]??null;return $p;});};
    $forwards=$decorate($fantraxRows->filter(fn($p)=>strtoupper(trim((string)$p->position))==='F')->values());
    $defensemen=$decorate($fantraxRows->filter(fn($p)=>strtoupper(trim((string)$p->position))==='D')->values());
    $dfo = DB::table('active_starting_goalies')->where('game_date',$date)->get();
    $dfoByTeam=[];$confirmedByTeam=[];foreach($dfo as $g){$team=strtoupper(trim((string)$g->team));$dfoByTeam[$team][$norm($g->player_name)]=$g;if(strtolower(trim((string)$g->starting_status))==='confirmed')$confirmedByTeam[$team]=$norm($g->player_name);}
    $goalies=$fantraxRows->filter(fn($p)=>strtoupper(trim((string)$p->position))==='G')->map(function($p)use($dfoByTeam,$confirmedByTeam,$norm){$team=strtoupper(trim((string)$p->team));$name=$norm($p->player_name);$g=$dfoByTeam[$team][$name]??null;$p->starting_status=$g?ucfirst(strtolower(trim((string)$g->starting_status))):'NA';$p->not_starting=isset($confirmedByTeam[$team])&&$confirmedByTeam[$team]!==$name;if($p->not_starting)$p->starting_status='Not starting';return $p;})->values();
    $rank=['Confirmed'=>0,'Probable'=>1,'Unconfirmed'=>2,'NA'=>3,'Not starting'=>4];$goalies=$goalies->sort(function($a,$b)use($rank){$ra=$rank[$a->starting_status]??3;$rb=$rank[$b->starting_status]??3;return $ra===$rb?((float)($b->projected_fpts??-INF)<=>(float)($a->projected_fpts??-INF)):($ra<=>$rb);})->values();
    return view('ai-tips', compact('date','today','tomorrow','selected','goalies','forwards','defensemen'));
});

require __DIR__.'/ai-tips-db.php';
require __DIR__.'/jobs.php';

require __DIR__.'/accounts.php';
require __DIR__.'/projection-settings.php';

require __DIR__.'/season-players.php';

require __DIR__.'/communication.php';
