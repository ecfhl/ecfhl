<?php

use App\Support\DailyFaceoffPowerPlay;
use App\Support\DailyFaceoffStartingGoalies;
use App\Support\FantraxAvailablePlayers;
use App\Support\FantraxDailyScores;
use App\Support\FantraxDailyMoves;
use App\Support\FantraxTeamRosters;
use App\Support\FantraxStandings;
use App\Support\FantraxSchedule;
use App\Support\NhlOdds;
use App\Support\NhlDailyStats;
use App\Support\WebPush;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:sync', function () {
    $sources = ['history'=>'https://ecfhl.win/data.json','drafts'=>'https://ecfhl.win/draft-picks.json'];
    foreach ($sources as $key=>$url) {
        $response=Http::timeout(30)->retry(3,1000)->get($url); $response->throw(); $decoded=$response->json();
        if ($key==='history') { $replace=function (&$v) use (&$replace) { if(is_array($v)){foreach($v as &$i)$replace($i);} elseif($v==='Janick')$v='JDPower'; }; $replace($decoded); }
        $payload=$key==='history'?json_encode($decoded,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES):$response->body();
        DB::table('source_cache')->updateOrInsert(['source_key'=>$key],['source_url'=>$url,'payload'=>$payload,'retrieved_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
        $this->info("Synced {$key}");
    }
    DB::table('franchises')->where('franchise_id','F009')->update(['franchise_name'=>'JDPower']);
});
Artisan::command('ecfhl:validate-drafts', function () { $plan=(new \Database\Seeders\DraftsOnlySeeder)->plan(); $this->info('READ ONLY: '.count($plan['draft_picks']).' picks validated; season counts: '.json_encode($plan['counts'])); });
Artisan::command('ecfhl:refresh-pp-lines {--team=}', function (DailyFaceoffPowerPlay $scraper) {
    $only=strtoupper((string)$this->option('team')); $teams=DailyFaceoffPowerPlay::TEAMS;if($only!==''){if(!isset($teams[$only])){$this->error("Unknown NHL team: {$only}");return 1;}$teams=[$only=>$teams[$only]];}
    $failed=false;
    foreach($teams as $team=>$slug){try{
        $data=$scraper->fetch($team,$slug);
        $storedPp=DB::table('active_pp_lines')->where('team',$team)->max('last_update');
        $storedLines=DB::table('active_line_combinations')->where('team',$team)->max('last_update');
        $storedGoalies=DB::table('active_line_combinations')->where('team',$team)->where('position_group','G')->orderBy('line_number')->pluck('player_name')->map(fn($name)=>mb_strtolower(trim($name)))->values()->all();
        $expectedGoalies=collect($data['lines'])->where('position_group','G')->sortBy('line_number')->pluck('player_name')->map(fn($name)=>mb_strtolower(trim($name)))->values()->all();
        $goalieDepthCurrent=count($storedGoalies)>=2&&$storedGoalies===$expectedGoalies;
        $ppCurrent=$storedPp&&$data['lastUpdate']->lessThanOrEqualTo(CarbonImmutable::parse($storedPp));
        $linesCurrent=$storedLines&&$goalieDepthCurrent&&$data['lastUpdate']->lessThanOrEqualTo(CarbonImmutable::parse($storedLines));
        if($ppCurrent&&$linesCurrent)continue;
        DB::transaction(function()use($data,$team){
            $now=now();
            DB::table('active_pp_lines')->where('team',$team)->delete();
            $ppRows=array_map(fn($p)=>['team'=>$team,'player_name'=>$p['player_name'],'pp_unit'=>$p['pp_unit'],'unit_position'=>$p['unit_position'],'source_url'=>$data['url'],'last_update'=>$data['lastUpdate'],'checked_at'=>$now,'created_at'=>$now,'updated_at'=>$now],$data['players']);
            if($ppRows)DB::table('active_pp_lines')->insert($ppRows);
            DB::table('active_line_combinations')->where('team',$team)->delete();
            $lineRows=array_map(fn($p)=>['team'=>$team,'player_name'=>$p['player_name'],'position_group'=>$p['position_group'],'line_number'=>$p['line_number'],'unit_position'=>$p['unit_position'],'source_url'=>$data['url'],'last_update'=>$data['lastUpdate'],'checked_at'=>$now,'created_at'=>$now,'updated_at'=>$now],$data['lines']);
            if($lineRows)DB::table('active_line_combinations')->insert($lineRows);
        });
        $this->info("{$team}: updated");
    }catch(\Throwable $e){$failed=true;Log::error('Daily Faceoff lines refresh failed',['team'=>$team,'error'=>$e->getMessage()]);$this->error("{$team}: {$e->getMessage()}");}}
    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-daily-players', function (FantraxAvailablePlayers $fantrax) {
    $base=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();$failed=false;
    foreach([$base,$base->addDay()] as $date){try{
        Log::info('Fantrax daily players refresh started',['date'=>$date->format('Y-m-d'),'url'=>$fantrax->url($date)]);
        $all=$fantrax->fetch($date,'ALL');
        try{$goalies=$fantrax->fetch($date,'G');}catch(\Throwable $e){Log::warning('Dedicated Fantrax goalie fetch failed',['date'=>$date->format('Y-m-d'),'error'=>$e->getMessage()]);$goalies=['rows'=>[]];}
        $merged=[];foreach(array_merge($all['rows'],$goalies['rows']) as $p){$key=mb_strtolower(trim($p['player_name'])).'|'.strtoupper(trim($p['team'])).'|'.strtoupper(trim((string)($p['position']??'')));$merged[$key]=$p;}
        $now=now();$rows=array_map(function($p)use($date,$now){$opp=trim((string)($p['opponent']??''));$away=str_starts_with($opp,'@');return ['game_date'=>$date->format('Y-m-d'),'player_name'=>$p['player_name'],'team'=>$p['team'],'position'=>$p['position'],'opponent'=>ltrim($opp,'@'),'home_away'=>$opp===''?null:($away?'AWAY':'HOME'),'game_time'=>$p['game_time']??null,'game_started'=>(bool)($p['game_started']??false),'availability'=>$p['availability'],'waiver_day'=>$p['waiver_day'],'injury_status'=>$p['injury_status'],'projected_fpts'=>$p['projected_fpts'],'fantrax_url'=>$p['fantrax_url'],'source_rank'=>$p['source_rank'],'last_update'=>$now,'created_at'=>$now,'updated_at'=>$now];},array_values($merged));
        DB::transaction(function()use($date,$rows){DB::table('active_daily_players')->whereDate('game_date',$date->format('Y-m-d'))->delete();if($rows)DB::table('active_daily_players')->insert($rows);});$count=count($rows);$gcount=count(array_filter($rows,fn($r)=>$r['position']==='G'));$this->info($date->format('Y-m-d').': '.$count.' Fantrax players refreshed ('.$gcount.' goalies)');Log::info('Fantrax daily players refresh completed',['date'=>$date->format('Y-m-d'),'rows'=>$count,'goalies'=>$gcount]);
    }catch(\Throwable $e){$failed=true;Log::error('Fantrax daily players refresh failed',['date'=>$date->format('Y-m-d'),'url'=>$fantrax->url($date),'error'=>$e->getMessage(),'exception'=>get_class($e)]);$this->error($date->format('Y-m-d').': '.$e->getMessage());}}
    if(!$failed){try{Artisan::call('ecfhl:refresh-available-goalies');$this->line(trim(Artisan::output()));}catch(\Throwable $e){$failed=true;$this->error('Available goalies: '.$e->getMessage());}}
    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-daily-scores {date?}', function (FantraxDailyScores $fantrax, NhlDailyStats $nhlStats, FantraxSchedule $fantraxSchedule, WebPush $webPush) {
    $tz='America/Halifax';
    $requested=trim((string)($this->argument('date')??''));
    if($requested!==''){
        try{$dates=[CarbonImmutable::createFromFormat('!Y-m-d',$requested,$tz)];}
        catch(\Throwable){$this->error('Use date format YYYY-MM-DD.');return 1;}
    }else{
        $today=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
        $dates=[$today];
        $yesterday=$today->subDay();
        $hasYesterday=DB::table('active_daily_scores')->whereDate('game_date',$yesterday->toDateString())->exists();
        if(!$hasYesterday)$dates=array_merge([$yesterday],$dates);
    }

    $failed=false;
    foreach($dates as $date){
        try {
            $data=$fantrax->fetch($date);
            $nhlRows=$nhlStats->fetch($date);
            $cowanNhl=collect($nhlRows)->first(fn($r)=>str_contains(mb_strtolower((string)($r['player_name']??'')),'cowan'));
            Log::info('NHL daily stat merge diagnostic',[
                'date'=>$date->toDateString(),
                'nhl_rows'=>count($nhlRows),
                'cowan'=>$cowanNhl,
            ]);
            $normName=function($v){$name=trim((string)$v);if(str_contains($name,',')){[$last,$first]=array_map('trim',explode(',',$name,2));if($first!==''&&$last!=='')$name=$first.' '.$last;}return preg_replace('/[^\\pL\\pN]+/u','',mb_strtolower($name))??'';};
            $normTeam=function($v){$t=strtoupper(trim((string)$v));return match($t){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$t};};
            $nhlByKey=[];
            foreach($nhlRows as $stat)$nhlByKey[$normTeam($stat['nhl_team']??'').'|'.$normName($stat['player_name']??'')]=$stat;

            $previousScores=DB::table('active_daily_scores')
                ->whereDate('game_date',$date->toDateString())
                ->get()
                ->keyBy(fn($r)=>$normTeam($r->nhl_team).'|'.$normName($r->player_name));

            $now=now();
            $rows=array_map(function($r)use($date,$now,$data,$nhlByKey,$normName,$normTeam,$previousScores){
                $stat=$nhlByKey[$normTeam($r['nhl_team']??'').'|'.$normName($r['player_name']??'')]??[];
                return [
                    'game_date'=>$date->toDateString(),
                    'player_name'=>$r['player_name'],
                    'nhl_team'=>$r['nhl_team'],
                    'position'=>$r['position'],
                    'fantasy_status'=>$r['fantasy_status'],
                    'opponent_display'=>$r['opponent_display']??null,
                    'today_fpts'=>$r['today_fpts'],
                    'fpts_changed'=>($previous=$previousScores[$normTeam($r['nhl_team']??'').'|'.$normName($r['player_name']??'')]??null)
                        ? abs((float)$previous->today_fpts-(float)$r['today_fpts'])>0.0001
                        : false,
                    'gp'=>$stat['gp']??0,
                    'g'=>$stat['g']??0,
                    'a'=>$stat['a']??0,
                    'ppg'=>$stat['ppg']??0,
                    'shg'=>$stat['shg']??0,
                    'gwg'=>$stat['gwg']??0,
                    'w'=>$stat['w']??0,
                    'so'=>$stat['so']??0,
                    'source_url'=>$data['url'],
                    'checked_at'=>$now,
                    'created_at'=>$now,
                    'updated_at'=>$now,
                ];
            },$data['rows']);

            $fantasyTeamByPlayer=[];
            try {
                $rosterRows=DB::table('active_fantasy_rosters')
                    ->whereDate('game_date',$date->toDateString())
                    ->get(['player_name','nhl_team','fantasy_team_id']);
                foreach($rosterRows as $rosterRow){
                    $rosterKey=$normTeam($rosterRow->nhl_team??'').'|'.$normName($rosterRow->player_name??'');
                    if($rosterKey!=='|')$fantasyTeamByPlayer[$rosterKey]=(string)$rosterRow->fantasy_team_id;
                }
            } catch (\Throwable $e) {
                Log::warning('Could not map live-score notifications to fantasy teams',['error'=>$e->getMessage()]);
            }

            $scoreNotifications=[];
            $isCurrentFantasyDay=$date->toDateString()===CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay()->toDateString();
            if($isCurrentFantasyDay){
                foreach($rows as $row){
                    $key=$normTeam($row['nhl_team']??'').'|'.$normName($row['player_name']??'');
                    $previous=$previousScores[$key]??null;
                    if(!$previous)continue;

                    // A live-scoring alert is only useful when this current roster
                    // player's fantasy score increases. Stat corrections or category-only
                    // changes that do not add fantasy points must stay silent.
                    $previousFpts=(float)($previous->today_fpts??0);
                    $currentFpts=(float)($row['today_fpts']??0);
                    if($currentFpts<=$previousFpts+0.0001)continue;

                    $name=trim((string)$row['player_name']);
                    $team=$normTeam($row['nhl_team']??'');
                    $label=$name.($team!==''?' ('.$team.')':'');
                    $delta=fn($field)=>(int)($row[$field]??0)-(int)($previous->{$field}??0);
                    $fantasyTeamId=$fantasyTeamByPlayer[$key]??null;
                    if(!$fantasyTeamId)continue;
                    $queueNotification=function(string $body)use(&$scoreNotifications,$fantasyTeamId){$scoreNotifications[]=['body'=>$body,'fantasy_team_id'=>$fantasyTeamId];};

                    $goalDelta=max(0,$delta('g'));
                    $ppgDelta=max(0,$delta('ppg'));
                    $shgDelta=max(0,$delta('shg'));
                    if($ppgDelta>0)$queueNotification('PPG by '.$label);
                    if($shgDelta>0)$queueNotification('SHG by '.$label);
                    if(max(0,$goalDelta-$ppgDelta-$shgDelta)>0)$queueNotification('Goal by '.$label);
                    if($delta('a')>0)$queueNotification('Assist by '.$label);
                    if($delta('gwg')>0)$queueNotification('GWG by '.$label);
                    if($delta('w')>0)$queueNotification('Win by '.$label);
                    if($delta('so')>0)$queueNotification('Shutout by '.$label);
                }
            }

            $matchupRows=null;
            try {
                $schedule=$fantraxSchedule->forDate($date,true);

                if(\Illuminate\Support\Facades\Schema::hasTable('fantrax_scoring_period_matchups')){
                    $caption=trim((string)($schedule['caption']??''));
                    preg_match('/(\d+)/',$caption,$periodMatch);
                    $periodNumber=(int)($periodMatch[1]??0);
                    if($periodNumber>0){
                        $normalizeMatchupName=fn($v)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',str_replace(["’","‘"],"'",(string)$v))));
                        $storedPeriodRows=DB::table('fantrax_scoring_period_matchups')
                            ->where('season_id','2026-27')
                            ->where('period_number',$periodNumber)
                            ->get();
                        foreach(($schedule['matchups']??[]) as $periodMatchup){
                            $awayName=(string)($periodMatchup['away_name']??'');
                            $homeName=(string)($periodMatchup['home_name']??'');
                            $storedPeriodRow=$storedPeriodRows->first(fn($r)=>
                                $normalizeMatchupName($r->away_team_name)===$normalizeMatchupName($awayName)
                                && $normalizeMatchupName($r->home_team_name)===$normalizeMatchupName($homeName)
                            );
                            if($storedPeriodRow){
                                DB::table('fantrax_scoring_period_matchups')
                                    ->where('id',$storedPeriodRow->id)
                                    ->update([
                                        'start_date'=>$schedule['start']??null,
                                        'end_date'=>$schedule['end']??null,
                                        'away_score'=>$periodMatchup['away_score']??0,
                                        'home_score'=>$periodMatchup['home_score']??0,
                                        'updated_at'=>now(),
                                    ]);
                            }
                        }
                    }
                }

                $previousMatchups=DB::table('active_matchup_scores')
                    ->whereDate('game_date',$date->toDateString())
                    ->get()
                    ->keyBy(fn($r)=>(string)$r->fantasy_team_id);
                $matchupRows=[];
                foreach(($schedule['matchups']??[]) as $pair){
                    foreach([
                        [(string)($pair['away_team_id']??''),$pair['away_score']??null],
                        [(string)($pair['home_team_id']??''),$pair['home_score']??null],
                    ] as [$teamId,$score]){
                        if($teamId==='')continue;
                        $previous=$previousMatchups[$teamId]??null;
                        $matchupRows[]=[
                            'game_date'=>$date->toDateString(),
                            'fantasy_team_id'=>$teamId,
                            'week_fpts'=>$score,
                            'week_fpts_changed'=>$previous && $score!==null
                                ? abs((float)$previous->week_fpts-(float)$score)>0.0001
                                : false,
                            'checked_at'=>$now,
                            'created_at'=>$now,
                            'updated_at'=>$now,
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::warning('Fantrax matchup score snapshot failed',[
                    'date'=>$date->toDateString(),
                    'error'=>$e->getMessage(),
                ]);
            }

            DB::transaction(function()use($date,$rows,$matchupRows){
                DB::table('active_daily_scores')->whereDate('game_date',$date->toDateString())->delete();
                if($rows)DB::table('active_daily_scores')->insert($rows);
                if($matchupRows!==null){
                    DB::table('active_matchup_scores')->whereDate('game_date',$date->toDateString())->delete();
                    if($matchupRows)DB::table('active_matchup_scores')->insert($matchupRows);
                }
            });

            DB::table('job_run_history')->insert([
                'job_name'=>'ecfhl:refresh-daily-scores',
                'target_date'=>$date->toDateString(),
                'rows_processed'=>count($rows),
                'completed_at'=>now(),
                'created_at'=>now(),
                'updated_at'=>now(),
            ]);

            foreach($scoreNotifications as $notification){
                try {
                    $body=$notification['body'];
                    $webPush->notify('live-score','ECFHL Live Scoring',$body,'/teams/current?date='.$date->toDateString(),$notification['fantasy_team_id']);
                } catch (\Throwable $e) {
                    Log::warning('Live scoring push notification failed',[
                        'date'=>$date->toDateString(),
                        'body'=>$body,
                        'error'=>$e->getMessage(),
                    ]);
                }
            }

            $this->info($date->toDateString().': '.count($rows).' Fantrax daily scores refreshed');
        } catch (\Throwable $e) {
            $failed=true;
            Log::error('Fantrax daily score refresh failed',['date'=>$date->toDateString(),'error'=>$e->getMessage()]);
            $this->error($date->toDateString().': '.$e->getMessage().'. Existing scores preserved.');
        }
    }

    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-lineup-advice', function (FantraxDailyMoves $dailyMoves) {
    $tz='America/Halifax';
    $day=CarbonImmutable::now($tz)->subHours(4)->startOfDay();
    $date=$day->toDateString();
    $dow=(int)$day->format('N');
    $isWeekend=$dow>=6;

    try {
        $moveRows=$dailyMoves->fetch($day);
        $now=now();
        DB::transaction(function()use($date,$moveRows,$now){
            DB::table('team_daily_moves')->whereDate('move_date',$date)->delete();
            foreach($moveRows as $move){
                DB::table('team_daily_moves')->insert([
                    'move_date'=>$date,
                    'fantasy_team_id'=>(string)$move['fantasy_team_id'],
                    'fantasy_team_name'=>(string)$move['fantasy_team_name'],
                    'moves_used'=>$move['moves_used']===null?null:(int)$move['moves_used'],
                    'moves_left'=>$move['moves_left']===null?null:(int)$move['moves_left'],
                    'checked_at'=>$now,
                    'created_at'=>$now,
                    'updated_at'=>$now,
                ]);
            }
        });
        $this->info(count($moveRows).' team move-limit rows refreshed before lineup advice.');
    } catch (\Throwable $e) {
        Log::warning('Lineup advisor move-limit refresh failed',['error'=>$e->getMessage()]);
    }

    $rosters=DB::table('active_fantasy_rosters')->whereDate('game_date',$date)->get();
    $teams=$rosters->groupBy('fantasy_team_id');
    $availableGroups=\App\Support\AiTips::groups([], $date);

    // Avoid ambiguous duplicate-name players that can be mistaken for the
    // fantasy-relevant NHL player with the same name.
    $advisorExcludedNames=[
        'eliaspettersson',
        'sebastianaho',
        'sebastienaho',
        'jackhughes',
        'ryanoreilly',
    ];
    $advisorNameKey=function($value){
        $name=trim((string)$value);
        if(str_contains($name,',')){
            [$last,$first]=array_map('trim',explode(',',$name,2));
            if($first!==''&&$last!=='')$name=$first.' '.$last;
        }
        return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';
    };
    foreach(['F','D','G'] as $positionKey){
        $availableGroups[$positionKey]=array_values(array_filter(
            $availableGroups[$positionKey]??[],
            fn($player)=>!in_array($advisorNameKey($player['name']??''),$advisorExcludedNames,true)
        ));
    }

    // Use the same Daily Targets ranking shown on the current-team page:
    // PP1 before PP2 before no PP unit, then L1-L4, then projected FPts.
    $rankName=function($value){
        $name=trim((string)$value);
        if(str_contains($name,',')){
            [$last,$first]=array_map('trim',explode(',',$name,2));
            if($first!==''&&$last!=='')$name=$first.' '.$last;
        }
        return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';
    };
    $rankTeam=fn($value)=>match(strtoupper(trim((string)$value))){
        'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>strtoupper(trim((string)$value))
    };
    $advisorPp=DB::table('active_pp_lines')->get()->keyBy(fn($r)=>$rankTeam($r->team).'|'.$rankName($r->player_name));
    $advisorLines=DB::table('active_line_combinations')->get()->keyBy(fn($r)=>$rankTeam($r->team).'|'.$rankName($r->player_name).'|'.strtoupper(trim((string)$r->position_group)));

    foreach(['F','D'] as $rankPosition){
        $decorated=collect($availableGroups[$rankPosition]??[])->map(function($player)use($rankPosition,$advisorPp,$advisorLines,$rankName,$rankTeam){
            $team=$rankTeam($player['team']??'');
            $name=$rankName($player['name']??'');
            $line=$advisorLines[$team.'|'.$name.'|'.$rankPosition]??null;
            $power=$advisorPp[$team.'|'.$name]??null;
            $player['line_number']=$line?(int)$line->line_number:null;
            $player['pp_unit']=$power?(int)$power->pp_unit:null;
            return $player;
        })->sort(function($a,$b){
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
        })->values()->all();
        $availableGroups[$rankPosition]=$decorated;
    }

    // Spread recommendations across the best Daily Targets instead of handing
    // every fantasy team the same player. Stay near the top of the ranking, but
    // prefer a target that has been recommended fewer times during this run.
    $targetUsage=['F'=>[],'D'=>[],'G'=>[]];
    $pickTarget=function(string $position,string $teamId)use(&$targetUsage,$availableGroups){
        $pool=collect($availableGroups[$position]??[])->take(5)->values();
        if($pool->isEmpty())return null;

        $best=null;
        $bestScore=null;
        foreach($pool as $rank=>$player){
            $key=mb_strtolower(trim((string)($player['name']??''))).'|'.strtoupper(trim((string)($player['team']??''))).'|'.$position;
            $used=(int)($targetUsage[$position][$key]??0);
            // Usage matters more than a small ranking difference. This keeps the
            // recommendations varied while still selecting from the top five.
            $score=($used*10)+$rank;
            if($bestScore===null || $score<$bestScore){
                $best=$player;
                $bestScore=$score;
            }
        }

        if($best){
            $key=mb_strtolower(trim((string)($best['name']??''))).'|'.strtoupper(trim((string)($best['team']??''))).'|'.$position;
            $targetUsage[$position][$key]=(int)($targetUsage[$position][$key]??0)+1;
        }
        return $best;
    };

    $scheduleScores=[];
    try {
        $schedule=app(FantraxSchedule::class)->forDate($day,true);
        foreach(($schedule['matchups']??[]) as $pair){
            $scheduleScores[(string)($pair['away_team_id']??'')]=(float)($pair['away_score']??0);
            $scheduleScores[(string)($pair['home_team_id']??'')]=(float)($pair['home_score']??0);
        }
    } catch (\Throwable $e) {
        Log::warning('Lineup advisor schedule lookup failed',['error'=>$e->getMessage()]);
    }

    $displayPlayerName=function($value){
        $name=trim((string)$value);
        if(str_contains($name,',')){
            [$last,$first]=array_map('trim',explode(',',$name,2));
            if($first!==''&&$last!=='')$name=$first.' '.$last;
        }
        return preg_replace('/\s+/u',' ',$name)??$name;
    };

    $normContract=fn($v)=>strtoupper(trim(preg_replace('/\s+/',' ',(string)$v)));
    $contractDropEligible=function($p)use($normContract){
        $contract=$normContract($p->contract??'');
        return in_array($contract,['FA','1 YEAR','1 YEAR(S)','1 YR'],true)
            && strtoupper((string)($p->roster_status??''))!=='MINORS';
    };
    $dropEligible=function($p)use($normContract){
        $contract=$normContract($p->contract??'');
        if(!in_array($contract,['FA','1 YEAR','1 YEAR(S)','1 YR'],true))return false;
        if(strtoupper((string)($p->roster_status??''))==='MINORS')return false;
        $proj=(float)($p->projected_fpts_per_game??0);
        return match(strtoupper((string)$p->position)){
            'F'=>$proj<=1.0,
            'D'=>$proj<=0.6,
            'G'=>$proj<=1.2,
            default=>false,
        };
    };

    $advisorProfiles=collect();
    if(\Illuminate\Support\Facades\Schema::hasTable('lineup_advisor_profiles')){
        $advisorProfiles=DB::table('lineup_advisor_profiles')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }
    if($advisorProfiles->isEmpty()){
        $advisorProfiles=collect([(object)[
            'advisor_key'=>'mike',
            'first_name'=>'Mike',
            'style_text'=>'Here is the thing. {advice}',
            'recommendation_style'=>'neutral',
            'is_conservative'=>false,
            'sort_order'=>10,
        ]]);
    }

    foreach($teams as $teamId=>$teamRows){
        $selectedAdvisor=$advisorProfiles->random();
        $advisorKey=(string)$selectedAdvisor->advisor_key;
        $advisorName=(string)$selectedAdvisor->first_name;
        $teamName=(string)($teamRows->first()->fantasy_team_name??$teamId);
        $teamScore=$scheduleScores[(string)$teamId]??null;
        $opponentScore=null;

        try {
            $schedule=$schedule??app(FantraxSchedule::class)->forDate($day);
            foreach(($schedule['matchups']??[]) as $pair){
                if((string)($pair['away_team_id']??'')===(string)$teamId){
                    $opponentScore=(float)($pair['home_score']??0);
                    break;
                }
                if((string)($pair['home_team_id']??'')===(string)$teamId){
                    $opponentScore=(float)($pair['away_score']??0);
                    break;
                }
            }
        } catch (\Throwable) {}

        $trailing=$teamScore!==null&&$opponentScore!==null&&$teamScore<$opponentScore;
        $lateWeek=$dow>=4;
        $movesLeft=DB::table('team_daily_moves')
            ->whereDate('move_date',$date)
            ->where('fantasy_team_id',(string)$teamId)
            ->value('moves_left');
        $movesLeft=$movesLeft!==null?(int)$movesLeft:null;

        $activePlayers=$teamRows->filter(fn($p)=>
            (bool)($p->is_playing??false)
            && !(bool)($p->is_ir??false)
            && strtoupper((string)($p->roster_status??''))!=='MINORS'
        );

        $playingCounts=[
            'F'=>$activePlayers->filter(fn($p)=>strtoupper((string)$p->position)==='F')->count(),
            'D'=>$activePlayers->filter(fn($p)=>strtoupper((string)$p->position)==='D')->count(),
            'G'=>$activePlayers->filter(fn($p)=>strtoupper((string)$p->position)==='G')->count(),
        ];

        // Goalie coverage uses both Fantrax's datePlaying flag and Daily Faceoff.
        // A Confirmed/Likely/Unconfirmed DFO goalie counts as scheduled tonight,
        // even if Fantrax's roster flag is stale. Bench status never removes coverage.
        $normalizeGoalieName=function($value){
            $name=trim((string)$value);
            if(str_contains($name,',')){
                [$last,$first]=array_map('trim',explode(',',$name,2));
                if($first!==''&&$last!=='')$name=$first.' '.$last;
            }
            return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';
        };
        $normalizeGoalieTeam=fn($value)=>match(strtoupper(trim((string)$value))){
            'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>strtoupper(trim((string)$value))
        };
        $dfoGoalies=DB::table('active_starting_goalies')
            ->whereDate('game_date',$date)
            ->get()
            ->keyBy(fn($g)=>$normalizeGoalieTeam($g->team).'|'.$normalizeGoalieName($g->player_name));

        $goaliePlayingTonight=function($p)use($dfoGoalies,$normalizeGoalieName,$normalizeGoalieTeam){
            if(strtoupper((string)$p->position)!=='G')return false;
            if((bool)($p->is_ir??false) || strtoupper((string)($p->roster_status??''))==='MINORS')return false;

            if((bool)($p->is_playing??false))return true;

            $key=$normalizeGoalieTeam($p->nhl_team??'').'|'.$normalizeGoalieName($p->player_name??'');
            $goalie=$dfoGoalies[$key]??null;
            $status=strtolower(trim((string)($goalie->starting_status??'')));
            return in_array($status,['confirmed','likely','probable','unconfirmed'],true);
        };

        $hasGoaliePlayingTonight=$teamRows->contains($goaliePlayingTonight);

        $slotLimits=['F'=>8,'D'=>4,'G'=>1];
        $nonIrMinorDefenseCount=$teamRows->filter(fn($p)=>
            strtoupper((string)$p->position)==='D'
            && !(bool)($p->is_ir??false)
            && strtoupper((string)($p->roster_status??''))!=='MINORS'
        )->count();

        $eligibleDrops=$teamRows
            ->filter($dropEligible)
            ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
            ->values();

        $hasMoveAvailable=$movesLeft!==null&&$movesLeft>0;
        $suggestions=[];

        // IR opportunity: an injured player who is not already in an IR roster slot
        // can be moved to IR to create a roster spot without sacrificing another player.
        if($hasMoveAvailable){
            $irCandidate=$teamRows
                ->filter(fn($p)=>
                    !empty($p->injury_status)
                    && strtoupper((string)($p->roster_status??''))!=='INJURED_RESERVE'
                    && strtoupper((string)($p->roster_status??''))!=='MINORS'
                )
                ->sortByDesc(fn($p)=>(float)($p->projected_fpts_per_game??0))
                ->first();

            if($irCandidate){
                $pos=strtoupper((string)$irCandidate->position);
                $target=$pickTarget($pos,(string)$teamId);
                if($target){
                    $suggestions[]='Move '.$displayPlayerName($irCandidate->player_name).' to IR. Add '.$displayPlayerName($target['name']).' ('.$target['team'].')'
                        .(!empty($target['projected_points'])?', '.$target['projected_points'].' projected FPts':'').'.';
                }
            }
        }

        // If tonight's goalie slot is already covered but another eligible goalie
        // is not playing, convert that expendable goalie roster spot into an open
        // skater slot when a F/D starting slot is still available.
        if($hasMoveAvailable && $hasGoaliePlayingTonight){
            // A goalie who is playing tonight is protected. If the team carries a
            // second eligible goalie who is not playing, that goalie is the surplus
            // roster spot to convert into an open skater slot.
            $surplusGoalie=$teamRows
                ->filter(fn($p)=>
                    strtoupper((string)$p->position)==='G'
                    && !$goaliePlayingTonight($p)
                    && !(bool)($p->is_ir??false)
                    && strtoupper((string)($p->roster_status??''))!=='MINORS'
                )
                ->filter($dropEligible)
                ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
                ->first();

            if($surplusGoalie){
                $targetPos=null;
                if($playingCounts['F']<$slotLimits['F'])$targetPos='F';
                elseif($playingCounts['D']<$slotLimits['D'])$targetPos='D';

                if($targetPos){
                    $target=$pickTarget($targetPos,(string)$teamId);
                    if($target){
                        $suggestions[]='Goalie is covered tonight. You have an open '.$targetPos.' spot. Drop '
                            .$displayPlayerName($surplusGoalie->player_name).'. Add '.$displayPlayerName($target['name']).' ('.$target['team'].')'
                            .(!empty($target['projected_points'])?', '.$target['projected_points'].' projected FPts':'').'.';
                    }
                }
            }
        }

        // Prioritize forward scoring. First look for a weak forward-for-forward
        // upgrade. A defenseman can be converted into a forward only when the team
        // carries at least five non-IR/non-minors defensemen.
        if($hasMoveAvailable && empty($suggestions) && $eligibleDrops->isNotEmpty() && ($trailing || $lateWeek || $isWeekend)){
            $drop=$eligibleDrops
                ->filter(fn($p)=>strtoupper((string)$p->position)==='F')
                ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
                ->first();
            $targetPos='F';

            if(!$drop && $nonIrMinorDefenseCount>=5){
                $drop=$eligibleDrops
                    ->filter(fn($p)=>strtoupper((string)$p->position)==='D')
                    ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
                    ->first();
                $targetPos='F';
            }

            // If there is no forward upgrade and the team is not overloaded on
            // defense, a defense-for-defense cleanup is still allowed.
            if(!$drop){
                $drop=$eligibleDrops
                    ->filter(fn($p)=>strtoupper((string)$p->position)==='D')
                    ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
                    ->first();
                $targetPos='D';
            }

            if($drop){
                $target=$pickTarget($targetPos,(string)$teamId);
                if($target){
                    $reason=$trailing
                        ? 'You are trailing. Make a move'
                        : ($isWeekend ? 'Use the weekend. Add another game' : 'This roster spot is not giving you enough');
                    $suggestions[]='Also consider: Add '.$displayPlayerName($target['name']).' ('.$target['team'].') and drop '.$displayPlayerName($drop->player_name).' to improve your forward scoring potential.';
                }
            }
        }

        // Goalie streaming remains a priority when no active goalie is playing.
        if($hasMoveAvailable && !$hasGoaliePlayingTonight){
            $goalieEligible=collect($availableGroups['G']??[])
                ->filter(fn($g)=>!in_array(strtolower(trim((string)($g['starting_status']??''))),['not starting',''],true))
                ->values();
            $goaliePool=$goalieEligible->isNotEmpty()?$goalieEligible:collect($availableGroups['G']??[]);
            $goalieCandidates=$goaliePool->take(5)->all();
            $savedGoalies=$availableGroups['G']??[];
            $availableGroupsForGoalie=$availableGroups;
            $availableGroupsForGoalie['G']=$goalieCandidates;
            $goalieTarget=(function()use(&$targetUsage,$availableGroupsForGoalie,$teamId){
                $pool=collect($availableGroupsForGoalie['G']??[])->values();
                if($pool->isEmpty())return null;
                $best=null;$bestScore=null;
                foreach($pool as $rank=>$player){
                    $key=mb_strtolower(trim((string)($player['name']??''))).'|'.strtoupper(trim((string)($player['team']??''))).'|G';
                    $used=(int)($targetUsage['G'][$key]??0);
                    $score=($used*10)+$rank;
                    if($bestScore===null||$score<$bestScore){$best=$player;$bestScore=$score;}
                }
                if($best){
                    $key=mb_strtolower(trim((string)($best['name']??''))).'|'.strtoupper(trim((string)($best['team']??''))).'|G';
                    $targetUsage['G'][$key]=(int)($targetUsage['G'][$key]??0)+1;
                }
                return $best;
            })();

            $goaliesOnRoster=$teamRows
                ->filter(fn($p)=>
                    strtoupper((string)$p->position)==='G'
                    && strtoupper((string)($p->roster_status??''))!=='MINORS'
                    && !(bool)($p->is_ir??false)
                )
                ->values();

            $drop=null;
            if($goaliesOnRoster->count()>=2){
                // When replacing a goalie on a roster that already carries two,
                // keep the transaction goalie-for-goalie. The normal 1.2 Proj/G
                // protection is intentionally waived here, but contract eligibility
                // still applies.
                $drop=$goaliesOnRoster
                    ->filter(fn($p)=>!$goaliePlayingTonight($p))
                    ->filter($contractDropEligible)
                    ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
                    ->first();
            }

            if(!$drop && $goaliesOnRoster->count()<2){
                $drop=$eligibleDrops->first(fn($p)=>
                    strtoupper((string)$p->position)==='G'
                    && !$goaliePlayingTonight($p)
                ) ?? $eligibleDrops->first(fn($p)=>
                    strtoupper((string)$p->position)!=='G'
                );
            }

            if($goalieTarget && $drop && ($trailing || $lateWeek || $isWeekend)){
                $goalieReason=$goaliesOnRoster->count()>=2 && strtoupper((string)$drop->position)==='G'
                    ? 'You already carry 2 goalies. '
                    : '';
                $goalieStatus=strtolower(trim((string)($goalieTarget['starting_status']??'')));
                if($goalieStatus==='unconfirmed'){
                    array_unshift($suggestions,
                        $goalieReason.'No goalie tonight. '.$displayPlayerName($goalieTarget['name']).' ('.$goalieTarget['team'].')'
                        .' [GOALIE_STATUS:'.$goalieTarget['starting_status'].']. Wait for him to be Confirmed before making a move. If confirmed, add '.$displayPlayerName($goalieTarget['name'])
                        .' and drop '.$displayPlayerName($drop->player_name).'.'
                    );
                } else {
                    array_unshift($suggestions,
                        $goalieReason.'No goalie tonight. Add '.$displayPlayerName($goalieTarget['name']).' ('.$goalieTarget['team'].')'
                        .(!empty($goalieTarget['starting_status'])?' [GOALIE_STATUS:'.$goalieTarget['starting_status'].']':'')
                        .'. Drop '.$displayPlayerName($drop->player_name).'.'
                    );
                }
            }
        }

        // Do not spend more moves in the advice than the team actually has left.
        $suggestions=array_slice(array_values(array_unique($suggestions)),0,max(0,min($movesLeft,2)));

        if($movesLeft===null){
            $baseAdvice='Claims remaining unavailable from Fantrax.';
        } elseif(!$hasMoveAvailable){
            $baseAdvice='No moves left.';
        } elseif(empty($suggestions)){
            $baseAdvice=$eligibleDrops->isEmpty()
                ? 'Stand pat. No eligible FA or 1-year player falls below your drop thresholds.'
                : 'Stand pat. No move improves the lineup enough right now.';
        } else {
            $baseAdvice=implode(' ', $suggestions);
        }

        // At most one advisor may recommend saving the move.
        // Conservative advisors get most of those chances. Neutral advisors
        // get an occasional chance. Aggressive advisors never stand pat.
        $conservativeAdvisors=$advisorProfiles->filter(fn($p)=>
            strtolower((string)($p->recommendation_style??((bool)($p->is_conservative??false)?'conservative':'neutral')))==='conservative'
        )->values();
        $neutralAdvisors=$advisorProfiles->filter(fn($p)=>
            strtolower((string)($p->recommendation_style??((bool)($p->is_conservative??false)?'conservative':'neutral')))==='neutral'
        )->values();

        $standPatAdvisorKey=null;
        $roll=random_int(1,100);
        if($conservativeAdvisors->isNotEmpty() && $roll<=70){
            $standPatAdvisorKey=(string)$conservativeAdvisors->random()->advisor_key;
        } elseif($neutralAdvisors->isNotEmpty() && $roll<=85){
            $standPatAdvisorKey=(string)$neutralAdvisors->random()->advisor_key;
        } elseif($conservativeAdvisors->isEmpty() && $neutralAdvisors->isNotEmpty() && $roll<=20){
            $standPatAdvisorKey=(string)$neutralAdvisors->random()->advisor_key;
        }

        $usedAdvisorTargets=[];
        $distinctAdvisorAdvice=function(string $raw,string $advisorKey,string $recommendationStyle,bool $allowStandPat=false)use(
            &$usedAdvisorTargets,$availableGroups,$advisorNameKey,$rankTeam,$displayPlayerName,$eligibleDrops
        ){
            $standPatText=function()use($advisorKey){
                if($advisorKey==='john'){
                    return "Stand pat. I'm done doing the work for you. Save the move and figure out what your roster actually needs.";
                }
                return 'Stand pat. Save the move. There is not another distinct option strong enough to justify a transaction right now.';
            };

            $aggressive=$recommendationStyle==='aggressive';

            $pickFallbackMove=function()use(
                &$usedAdvisorTargets,$availableGroups,$advisorNameKey,$rankTeam,$displayPlayerName,$eligibleDrops,$allowStandPat,$standPatText
            ){
                $drop=$eligibleDrops
                    ->sortBy(fn($p)=>(float)($p->projected_fpts_per_game??0))
                    ->first();
                if(!$drop)return $standPatText();

                foreach(['F','D','G'] as $position){
                    $pool=array_values($availableGroups[$position]??[]);
                    if(!$pool)continue;
                    $bestProjected=max(array_map(fn($p)=>(float)($p['projected_points']??0),$pool));
                    foreach($pool as $player){
                        $key=$advisorNameKey($player['name']??'').'|'.$rankTeam($player['team']??'').'|'.$position;
                        if(isset($usedAdvisorTargets[$key]))continue;

                        if($allowStandPat){
                            $projected=(float)($player['projected_points']??0);
                            if($bestProjected>0 && $projected<($bestProjected*0.70))continue;
                            if($position==='G'){
                                $status=strtolower(trim((string)($player['starting_status']??'')));
                                if(!in_array($status,['confirmed','likely','probable'],true))continue;
                            }
                        }

                        $usedAdvisorTargets[$key]=true;
                        return 'Add '.$displayPlayerName($player['name']??'').' ('.strtoupper((string)($player['team']??'')).') and drop '.$displayPlayerName($drop->player_name).'.';
                    }
                }
                return $standPatText();
            };

            if($raw==='No moves left.'){
                if($aggressive){
                    return "No moves left? You already burned through every move you had. Great. Now you're stuck with it. Next time, manage your moves.";
                }
                return $raw;
            }

            if(!preg_match('/\\badd\\s+/i',$raw)){
                if(str_starts_with($raw,'Stand pat.')){
                    if($aggressive)return $pickFallbackMove();
                    return $allowStandPat?$standPatText():$pickFallbackMove();
                }
                return $raw;
            }

            $replacementMap=[];
            $failed=false;
            $result=preg_replace_callback(
                '/\\b(Add|add)\\s+(.+?)\\s+\\(([A-Z]{2,3})\\)(?:,\\s*([0-9.]+)\\s+projected FPts)?/u',
                function($m)use(
                    $allowStandPat,&$usedAdvisorTargets,&$replacementMap,&$failed,
                    $availableGroups,$advisorNameKey,$rankTeam,$displayPlayerName
                ){
                    $oldName=trim((string)$m[2]);
                    $oldTeam=$rankTeam($m[3]??'');
                    $originalKey=$advisorNameKey($oldName).'|'.$oldTeam;

                    if(isset($replacementMap[$originalKey])){
                        $candidate=$replacementMap[$originalKey];
                    } else {
                        $position=null;
                        foreach(['F','D','G'] as $pos){
                            foreach(($availableGroups[$pos]??[]) as $player){
                                if(
                                    $advisorNameKey($player['name']??'')===$advisorNameKey($oldName)
                                    && $rankTeam($player['team']??'')===$oldTeam
                                ){
                                    $position=$pos;
                                    break 2;
                                }
                            }
                        }
                        if(!$position){$failed=true;return $m[0];}

                        $pool=array_values($availableGroups[$position]??[]);
                        $bestProjected=0.0;
                        foreach($pool as $player){
                            $bestProjected=max($bestProjected,(float)($player['projected_points']??0));
                        }

                        $candidate=null;
                        foreach($pool as $player){
                            $key=$advisorNameKey($player['name']??'').'|'.$rankTeam($player['team']??'').'|'.$position;
                            if(isset($usedAdvisorTargets[$key]))continue;

                            if($allowStandPat){
                                $projected=(float)($player['projected_points']??0);
                                if($bestProjected>0 && $projected<($bestProjected*0.70))continue;
                                if($position==='G'){
                                    $status=strtolower(trim((string)($player['starting_status']??'')));
                                    if(!in_array($status,['confirmed','likely','probable'],true))continue;
                                }
                            }

                            $candidate=$player;
                            $usedAdvisorTargets[$key]=true;
                            break;
                        }

                        if(!$candidate){$failed=true;return $m[0];}
                        $replacementMap[$originalKey]=$candidate;
                    }

                    $newName=$displayPlayerName($candidate['name']??$oldName);
                    $newTeam=strtoupper((string)($candidate['team']??$oldTeam));
                    $replacement=$m[1].' '.$newName.' ('.$newTeam.')';
                    if(isset($m[4]) && $m[4]!==''){
                        $projected=$candidate['projected_points']??null;
                        if($projected!==null && $projected!=='')$replacement.=', '.$projected.' projected FPts';
                    }
                    return $replacement;
                },
                $raw
            ) ?? $raw;

            if($failed){
                if($aggressive)return $pickFallbackMove();
                return $allowStandPat?$standPatText():$pickFallbackMove();
            }

            foreach($replacementMap as $originalKey=>$candidate){
                [$oldKey,$oldTeam]=array_pad(explode('|',$originalKey,2),2,'');
                foreach(['F','D','G'] as $pos){
                    foreach(($availableGroups[$pos]??[]) as $player){
                        if($advisorNameKey($player['name']??'')===$oldKey && $rankTeam($player['team']??'')===$oldTeam){
                            $oldDisplay=$displayPlayerName($player['name']??'');
                            $newDisplay=$displayPlayerName($candidate['name']??'');
                            $result=preg_replace('/\\badd\\s+'.preg_quote($oldDisplay,'/').'\\b/iu','add '.$newDisplay,$result)??$result;
                            break 2;
                        }
                    }
                }
            }
            return $result;
        };

        $applyAdvisorStyle=function($profile,string $raw)use($rosters,$displayPlayerName){
            // Pierre occasionally adds an explicitly fictional ECFHL trade-rumour gag.
            // It uses a real rostered player and fantasy-team pairing from the league.
            if((string)($profile->advisor_key??'')==='pierre' && random_int(1,100)<=35 && $rosters->isNotEmpty()){
                $rumourPlayer=$rosters->random();
                $rumourName=$displayPlayerName($rumourPlayer->player_name??'');
                $rumourTeam=trim((string)($rumourPlayer->fantasy_team_name??''));
                if($rumourName!=='' && $rumourTeam!==''){
                    $rumours=[
                        "I'm hearing {player} from {team} could be on the market. Nothing concrete yet, but there has been some chatter.",
                        "A couple of people around the league have mentioned {player} from {team} as a name to watch on the trade market.",
                        "Don't be surprised if {team} starts taking calls on {player}. That's a situation worth monitoring.",
                        "There's some buzz around {player}. I'm told {team} may be willing to listen to offers.",
                        "One name quietly making the rounds: {player} from {team}. We'll see if anything develops.",
                        "Hearing {team} has had some conversations involving {player}. No indication anything is imminent.",
                    ];
                    $rumour=$rumours[array_rand($rumours)];
                    return str_replace(['{player}','{team}'],[$rumourName,$rumourTeam],$rumour);
                }
            }

            $style=trim((string)($profile->style_text??''));
            if($style==='')return $raw;
            $templates=array_values(array_filter(array_map('trim',preg_split('/\\R/u',$style)?:[])));
            if(!$templates)return $raw;
            $template=$templates[array_rand($templates)];

            $clean=$raw;
            if(str_starts_with($clean,'Also consider: ')){
                $clean=substr($clean,strlen('Also consider: '));
            }
            $lower=lcfirst($clean);

            if(str_contains($template,'{advice}')||str_contains($template,'{advice_lower}')){
                return str_replace(['{advice_lower}','{advice}'],[$lower,$clean],$template);
            }
            return rtrim($template).' '.$clean;
        };

        $advisorAdvice=[];
        foreach($advisorProfiles as $profile){
            $key=(string)$profile->advisor_key;
            $recommendationStyle=strtolower((string)($profile->recommendation_style??((bool)($profile->is_conservative??false)?'conservative':'neutral')));
            if(!in_array($recommendationStyle,['conservative','neutral','aggressive'],true))$recommendationStyle='neutral';
            $allowStandPat=$standPatAdvisorKey!==null && $key===$standPatAdvisorKey && $recommendationStyle!=='aggressive';
            $raw=$distinctAdvisorAdvice($baseAdvice,$key,$recommendationStyle,$allowStandPat);
            $advisorAdvice[$key]=[
                'name'=>(string)$profile->first_name,
                'advice'=>$applyAdvisorStyle($profile,$raw),
                'recommendation_style'=>$recommendationStyle,
                'conservative'=>$recommendationStyle==='conservative',
                'stand_pat_voice'=>$allowStandPat,
            ];
        }

        if(!isset($advisorAdvice[$advisorKey])){
            $selectedAdvisor=$advisorProfiles->first();
            $advisorKey=(string)$selectedAdvisor->advisor_key;
            $advisorName=(string)$selectedAdvisor->first_name;
        }
        $advice=$advisorAdvice[$advisorKey]['advice']??$baseAdvice;

        DB::table('lineup_advice')
            ->whereDate('advice_date',$date)
            ->where('fantasy_team_id',(string)$teamId)
            ->delete();
        DB::table('lineup_advice')->insert([
            'advice_date'=>$date,
            'fantasy_team_id'=>(string)$teamId,
            'fantasy_team_name'=>$teamName,
            'moves_left'=>$movesLeft,
            'advisor_name'=>$advisorKey,
            'advice_text'=>$advice,
            'advisor_advice_json'=>json_encode($advisorAdvice,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),
            'mike_advice_text'=>$advisorAdvice['mike']['advice']??null,
            'pierre_advice_text'=>$advisorAdvice['pierre']['advice']??null,
            'john_advice_text'=>$advisorAdvice['john']['advice']??null,
            'generated_at'=>now(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);
    }

    $this->info($teams->count().' lineup advisor rows refreshed.');
    return 0;
});

Artisan::command('ecfhl:refresh-scoring-period-matchups', function (FantraxSchedule $fantraxSchedule) {
    $seasonId='2026-27';
    try {
        $periods=$fantraxSchedule->periods(true);
        $updated=0;
        $normalize=fn($v)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',str_replace(["’","‘"],"'",(string)$v))));

        foreach($periods as $period){
            $caption=trim((string)($period['caption']??''));
            preg_match('/(\d+)/',$caption,$m);
            $periodNumber=(int)($m[1]??0);
            if($periodNumber<1)continue;

            $stored=DB::table('fantrax_scoring_period_matchups')
                ->where('season_id',$seasonId)
                ->where('period_number',$periodNumber)
                ->get();

            foreach(($period['matchups']??[]) as $matchup){
                $awayName=(string)($matchup['away_name']??'');
                $homeName=(string)($matchup['home_name']??'');
                $row=$stored->first(fn($r)=>
                    $normalize($r->away_team_name)===$normalize($awayName)
                    && $normalize($r->home_team_name)===$normalize($homeName)
                );
                if(!$row)continue;

                DB::table('fantrax_scoring_period_matchups')
                    ->where('id',$row->id)
                    ->update([
                        'start_date'=>$period['start'],
                        'end_date'=>$period['end'],
                        'away_score'=>$matchup['away_score']??0,
                        'home_score'=>$matchup['home_score']??0,
                        'updated_at'=>now(),
                    ]);
                $updated++;
            }
        }

        $this->info($updated.' scoring-period matchup rows refreshed.');
        return 0;
    } catch (\Throwable $e) {
        Log::error('Scoring period matchup refresh failed',['error'=>$e->getMessage()]);
        $this->error($e->getMessage());
        return 1;
    }
});

Artisan::command('ecfhl:refresh-current-standings', function (FantraxStandings $fantrax, FantraxSchedule $fantraxSchedule) {
    $seasonId='2026-27';
    $source='https://www.fantrax.com/fantasy/league/'.FantraxStandings::LEAGUE_ID.'/standings';

    try {
        $data=$fantrax->fetch();
        $seasonRows=DB::table('team_seasons')->where('season_id',$seasonId)->get();
        if($seasonRows->isEmpty())throw new \RuntimeException('No 2026-27 team_seasons rows exist.');

        $normalize=fn($s)=>mb_strtolower(trim(preg_replace('/\s+/u',' ',str_replace(["’","‘"],"'",(string)$s))));
        $byName=$seasonRows->keyBy(fn($r)=>$normalize($r->original_name));

        $rosterNameByTeamId=DB::table('active_fantasy_rosters')
            ->select('fantasy_team_id','fantasy_team_name')
            ->whereNotNull('fantasy_team_id')
            ->whereNotNull('fantasy_team_name')
            ->get()
            ->unique('fantasy_team_id')
            ->keyBy(fn($r)=>(string)$r->fantasy_team_id);

        $updates=[];
        foreach($data['rows'] as $standing){
            $name=(string)($standing['team_name']??'');
            $teamId=(string)($standing['team_id']??'');
            $target=$byName[$normalize($name)]??null;

            if(!$target && $teamId!=='' && isset($rosterNameByTeamId[$teamId])){
                $rosterName=(string)$rosterNameByTeamId[$teamId]->fantasy_team_name;
                $target=$byName[$normalize($rosterName)]??null;
            }

            if(!$target)continue;

            $updates[$target->team_season_id]=[
                'rank'=>$standing['rank'],
                'w'=>$standing['w'],
                'l'=>$standing['l'],
                't'=>$standing['t'],
                'standings_points'=>$standing['standings_points'],
                'fantasy_points_for'=>$standing['fantasy_points_for'],
                'source'=>$source,
                'source_id'=>'fantrax-'.FantraxStandings::LEAGUE_ID,
            ];
        }

        $expected=$seasonRows->count();
        if(count($updates)!==$expected){
            throw new \RuntimeException('Fantrax standings matched '.count($updates).' of '.$expected.' current teams. Existing standings preserved.');
        }

        DB::transaction(function()use($updates){
            foreach($updates as $teamSeasonId=>$values){
                DB::table('team_seasons')->where('team_season_id',$teamSeasonId)->update($values);
            }
        });

        try {
            $periods=$fantraxSchedule->periods(true);
            foreach($periods as $period){
                $caption=trim((string)($period['caption']??''));
                preg_match('/(\d+)/',$caption,$m);
                $periodNumber=(int)($m[1]??0);
                if($periodNumber<1)continue;

                foreach(($period['matchups']??[]) as $matchup){
                    DB::table('scoring_period_matchups')->updateOrInsert(
                        [
                            'season_id'=>$seasonId,
                            'period_number'=>$periodNumber,
                            'away_team_name'=>(string)($matchup['away_name']??''),
                            'home_team_name'=>(string)($matchup['home_name']??''),
                        ],
                        [
                            'start_date'=>$period['start'],
                            'end_date'=>$period['end'],
                            'away_team_id'=>(string)($matchup['away_team_id']??''),
                            'away_score'=>$matchup['away_score'],
                            'home_team_id'=>(string)($matchup['home_team_id']??''),
                            'home_score'=>$matchup['home_score'],
                            'created_at'=>now(),
                            'updated_at'=>now(),
                        ]
                    );
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Scoring period matchup history refresh failed',['error'=>$e->getMessage()]);
        }

        DB::table('job_run_history')->insert([
            'job_name'=>'ecfhl:refresh-current-standings',
            'target_date'=>CarbonImmutable::now('America/Halifax')->toDateString(),
            'rows_processed'=>count($updates),
            'completed_at'=>now(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $this->info(count($updates).' Fantrax standings rows refreshed for '.$seasonId);
        return 0;
    } catch (\Throwable $e) {
        Log::error('Fantrax current standings refresh failed',['error'=>$e->getMessage()]);
        $this->error($e->getMessage());
        return 1;
    }
});

Artisan::command('ecfhl:refresh-fantasy-rosters', function (FantraxTeamRosters $fantrax, FantraxDailyMoves $dailyMoves) {
    $base=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
    $failed=false;

    $todayFrozen=false;
    try {
        $day=$base->toDateString();
        $response=Http::timeout(12)->retry(1,500)->get('https://api-web.nhle.com/v1/score/'.$day);
        $response->throw();
        $starts=collect($response->json('games')??[])
            ->map(function($game){
                $utc=$game['startTimeUTC']??null;
                if(!$utc)return null;
                try{return CarbonImmutable::parse($utc)->utc();}catch(\Throwable){return null;}
            })
            ->filter();

        if($starts->isNotEmpty()){
            $lastStart=$starts->sortDesc()->first();
            $todayFrozen=CarbonImmutable::now('UTC')->gt($lastStart->addHours(4));
        }
    } catch (\Throwable $e) {
        // If the NHL schedule check fails, preserve the current-day snapshot rather
        // than risk overwriting a completed historical lineup.
        $todayFrozen=true;
        Log::warning('Fantasy roster freeze-window check failed',[
            'date'=>$base->toDateString(),
            'error'=>$e->getMessage(),
        ]);
    }

    foreach ([$base,$base->addDay()] as $date) {
        $isToday=$date->isSameDay($base);
        if($isToday && $todayFrozen){
            $this->info($date->toDateString().': roster snapshot frozen after live scoring window');
            continue;
        }

        try {
            $data=$fantrax->fetch($date);
            $now=now();
            $rows=array_map(function($r) use ($date,$now) {
                return array_merge($r,[
                    'game_date'=>$date->toDateString(),
                    'last_update'=>$now,
                    'created_at'=>$now,
                    'updated_at'=>$now,
                ]);
            },$data['rows']);
            DB::transaction(function() use ($date,$rows) {
                DB::table('active_fantasy_rosters')->whereDate('game_date',$date->toDateString())->delete();
                if($rows) DB::table('active_fantasy_rosters')->insert($rows);
            });
            $teams=count(array_unique(array_column($rows,'fantasy_team_id')));
            $this->info($date->toDateString().': '.count($rows).' roster players refreshed across '.$teams.' fantasy teams');
        } catch (\Throwable $e) {
            $failed=true;
            Log::error('Fantrax fantasy roster refresh failed',['date'=>$date->toDateString(),'error'=>$e->getMessage()]);
            $this->error($date->toDateString().': '.$e->getMessage().'. Existing roster data preserved.');
        }
    }

    try {
        $moveRows=$dailyMoves->fetch($base);
        $now=now();
        DB::transaction(function()use($base,$moveRows,$now){
            DB::table('team_daily_moves')->whereDate('move_date',$base->toDateString())->delete();
            foreach($moveRows as $move){
                DB::table('team_daily_moves')->insert([
                    'move_date'=>$base->toDateString(),
                    'fantasy_team_id'=>(string)$move['fantasy_team_id'],
                    'fantasy_team_name'=>(string)$move['fantasy_team_name'],
                    'moves_used'=>$move['moves_used']===null?null:(int)$move['moves_used'],
                    'moves_left'=>$move['moves_left']===null?null:(int)$move['moves_left'],
                    'checked_at'=>$now,
                    'created_at'=>$now,
                    'updated_at'=>$now,
                ]);
            }
        });
        $this->info(count($moveRows).' team move-limit rows refreshed.');

        try {
            Artisan::call('ecfhl:refresh-lineup-advice');
            $output=trim(Artisan::output());
            if($output!=='')$this->line($output);
        } catch (\Throwable $e) {
            Log::warning('Lineup advisor refresh after roster update failed',['error'=>$e->getMessage()]);
        }
    } catch (\Throwable $e) {
        $failed=true;
        Log::error('Fantrax daily move refresh failed',['date'=>$base->toDateString(),'error'=>$e->getMessage()]);
        $this->error('Daily moves: '.$e->getMessage());
    }

    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-starting-goalies', function (DailyFaceoffStartingGoalies $dfo, WebPush $webPush) {
    $abbr=['Anaheim Ducks'=>'ANA','Boston Bruins'=>'BOS','Buffalo Sabres'=>'BUF','Calgary Flames'=>'CGY','Carolina Hurricanes'=>'CAR','Chicago Blackhawks'=>'CHI','Colorado Avalanche'=>'COL','Columbus Blue Jackets'=>'CBJ','Dallas Stars'=>'DAL','Detroit Red Wings'=>'DET','Edmonton Oilers'=>'EDM','Florida Panthers'=>'FLA','Los Angeles Kings'=>'LAK','Minnesota Wild'=>'MIN','Montreal Canadiens'=>'MTL','Nashville Predators'=>'NSH','New Jersey Devils'=>'NJD','New York Islanders'=>'NYI','New York Rangers'=>'NYR','Ottawa Senators'=>'OTT','Philadelphia Flyers'=>'PHI','Pittsburgh Penguins'=>'PIT','San Jose Sharks'=>'SJS','Seattle Kraken'=>'SEA','St. Louis Blues'=>'STL','Tampa Bay Lightning'=>'TBL','Toronto Maple Leafs'=>'TOR','Utah Mammoth'=>'UTA','Vancouver Canucks'=>'VAN','Vegas Golden Knights'=>'VGK','Washington Capitals'=>'WSH','Winnipeg Jets'=>'WPG'];$base=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();$failed=false;
    foreach ([$base, $base->addDay()] as $date) {
        $day = $date->format('Y-m-d');
        try {
            $previousGoalies=DB::table('active_starting_goalies')
                ->whereDate('game_date',$day)
                ->get()
                ->keyBy(fn($r)=>strtoupper(trim((string)$r->team)).'|'.mb_strtolower(trim((string)$r->player_name)));

            $data = $dfo->fetch($date);
            $now = now();
            $rows = [];
            $normalizeTeamName=static fn($value)=>preg_replace('/[^a-z0-9]+/','',strtolower(trim((string)$value)))??'';
            $abbrNormalized=[];
            foreach($abbr as $teamName=>$teamAbbr)$abbrNormalized[$normalizeTeamName($teamName)]=$teamAbbr;

            $skippedRows=0;
            foreach ($data['rows'] as $g) {
                $team = $abbr[$g['team_name']] ?? ($abbrNormalized[$normalizeTeamName($g['team_name']??'')] ?? null);
                $opponent = $abbr[$g['opponent_name']] ?? ($abbrNormalized[$normalizeTeamName($g['opponent_name']??'')] ?? null);
                if (! $team || ! $opponent) {
                    $skippedRows++;
                    Log::warning('Skipping unrecognized Daily Faceoff goalie matchup row',[
                        'date'=>$day,
                        'team_name'=>$g['team_name']??null,
                        'opponent_name'=>$g['opponent_name']??null,
                        'player_name'=>$g['player_name']??null,
                    ]);
                    continue;
                }

                $playerName=trim((string)($g['player_name']??''));
                if($playerName===''){
                    $skippedRows++;
                    continue;
                }

                $status=trim((string)($g['starting_status']??'Unconfirmed'));
                if(!in_array($status,['Confirmed','Likely','Unconfirmed'],true))$status='Unconfirmed';

                $rows[] = [
                    'game_date'=>$day, 'team'=>$team, 'opponent'=>$opponent,
                    'home_away'=>$g['home_away'], 'player_name'=>$playerName,
                    'starting_status'=>$status, 'source_url'=>$data['url'],
                    'source_updated_at'=>$g['source_updated_at'], 'checked_at'=>$now,
                    'created_at'=>$now, 'updated_at'=>$now,
                ];
            }

            // Partial Daily Faceoff data is common, especially for tomorrow.
            // Never erase good existing rows just because one matchup is not ready yet.
            DB::transaction(function () use ($day, $rows) {
                foreach($rows as $row){
                    DB::table('active_starting_goalies')->updateOrInsert(
                        [
                            'game_date'=>$day,
                            'team'=>$row['team'],
                            'player_name'=>$row['player_name'],
                        ],
                        $row
                    );
                }
            });

            if($skippedRows>0){
                Log::warning('Daily Faceoff goalie refresh completed with skipped rows',[
                    'date'=>$day,
                    'skipped'=>$skippedRows,
                    'accepted'=>count($rows),
                ]);
            }
            $stored = DB::table('active_starting_goalies')->whereDate('game_date', $day)
                ->get(['player_name','team','opponent','home_away','starting_status']);
            foreach($stored as $goalie){
                $key=strtoupper(trim((string)$goalie->team)).'|'.mb_strtolower(trim((string)$goalie->player_name));
                $previous=$previousGoalies[$key]??null;
                if(!$previous)continue;
                $oldStatus=trim((string)($previous->starting_status??''));
                $newStatus=trim((string)($goalie->starting_status??''));
                if($newStatus==='' || strcasecmp($oldStatus,$newStatus)===0)continue;
                if(!in_array(strtolower($newStatus),['confirmed','likely'],true))continue;

                $body=$goalie->player_name.' ('.$goalie->team.') is now '.$newStatus.'.';
                $fantraxGoalieUrl='https://www.fantrax.com/fantasy/league/0s9n0t98ly3jpry7/players;searchName='.rawurlencode((string)$goalie->player_name).';positionOrGroup=ALL;';
                try {
                    $webPush->notify('goalie-status','Goalie Status',$body,$fantraxGoalieUrl);
                } catch (\Throwable $e) {
                    Log::warning('Goalie status push notification failed',[
                        'date'=>$day,
                        'goalie'=>$goalie->player_name,
                        'team'=>$goalie->team,
                        'status'=>$newStatus,
                        'error'=>$e->getMessage(),
                    ]);
                }
            }

            $this->info($day.': '.$stored->count().' DFO goalies refreshed ('.$data['source'].')');
            Log::info('Daily Faceoff goalie refresh completed', [
                'date'=>$day, 'source'=>$data['source'], 'rows'=>$stored->count(), 'goalies'=>$stored->all(),
            ]);
        } catch (\Throwable $e) {
            $failed = true;
            Log::error('Daily Faceoff goalie refresh failed', ['date'=>$day,'error'=>$e->getMessage()]);
            $this->error($day.': '.$e->getMessage());
        }
    }
    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-odds', function (NhlOdds $odds) {
    $base = CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
    $wanted = [$base->toDateString(), $base->addDay()->toDateString()];
    try {
        $data = $odds->fetch();
        $rows = array_values(array_filter($data['rows'], fn($r) => in_array($r['game_date'], $wanted, true)));
        $now = now();
        $sourceUpdated = $data['source_updated_at'] ? CarbonImmutable::parse($data['source_updated_at']) : null;
        foreach ($rows as &$row) {
            $row['source_updated_at'] = $sourceUpdated;
            $row['checked_at'] = $now;
            $row['created_at'] = $now;
            $row['updated_at'] = $now;
        }
        unset($row);
        DB::transaction(function () use ($wanted, $rows) {
            DB::table('todays_odds')->whereIn('game_date', $wanted)->delete();
            if ($rows) DB::table('todays_odds')->insert($rows);
        });
        foreach ($wanted as $day) {
            $count = count(array_filter($rows, fn($r) => $r['game_date'] === $day));
            $this->info($day.': '.$count.' NHL team odds refreshed');
        }
        return 0;
    } catch (\Throwable $e) {
        Log::error('NHL odds refresh failed', ['error'=>$e->getMessage()]);
        $this->error($e->getMessage().'. Existing odds data preserved.');
        return 1;
    }
});

Schedule::command('ecfhl:refresh-daily-scores')
    ->cron('* * * * *')
    ->withoutOverlapping(2)
    ->runInBackground()
    ->when(function () {
        $fantasyDay=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
        $day=$fantasyDay->toDateString();

        try {
            $response=Http::timeout(12)->retry(1,500)->get('https://api-web.nhle.com/v1/score/'.$day);
            $response->throw();
            $starts=collect($response->json('games')??[])
                ->map(function($game){
                    $utc=$game['startTimeUTC']??null;
                    if(!$utc)return null;
                    try{return CarbonImmutable::parse($utc)->utc();}catch(\Throwable){return null;}
                })
                ->filter();

            if($starts->isEmpty())return false;

            $now=CarbonImmutable::now('UTC');
            $first=$starts->sort()->first();
            $last=$starts->sortDesc()->first();

            return $now->betweenIncluded($first,$last->addHours(4));
        } catch (\Throwable $e) {
            Log::warning('Live scoring window check failed',[
                'date'=>$day,
                'error'=>$e->getMessage(),
            ]);
            return false;
        }
    });

Schedule::command('ecfhl:refresh-lineup-advice')
    ->hourly()
    ->timezone('America/Halifax')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('ecfhl:refresh-scoring-period-matchups')
    ->weeklyOn(1,'08:00')
    ->timezone('America/Halifax')
    ->withoutOverlapping(30)
    ->runInBackground();

Schedule::command('ecfhl:refresh-current-standings')
    ->cron('*/5 * * * *')
    ->withoutOverlapping(4)
    ->runInBackground()
    ->when(function () {
        $fantasyDay=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
        $day=$fantasyDay->toDateString();

        try {
            $response=Http::timeout(12)->retry(1,500)->get('https://api-web.nhle.com/v1/score/'.$day);
            $response->throw();
            $starts=collect($response->json('games')??[])
                ->map(function($game){
                    $utc=$game['startTimeUTC']??null;
                    if(!$utc)return null;
                    try{return CarbonImmutable::parse($utc)->utc();}catch(\Throwable){return null;}
                })
                ->filter();

            if($starts->isEmpty())return false;

            $now=CarbonImmutable::now('UTC');
            $first=$starts->sort()->first();
            $last=$starts->sortDesc()->first();

            return $now->betweenIncluded($first,$last->addHours(4));
        } catch (\Throwable $e) {
            Log::warning('Current standings game-window check failed',[
                'date'=>$day,
                'error'=>$e->getMessage(),
            ]);
            return false;
        }
    });

Schedule::command('ecfhl:refresh-daily-players')->cron('*/15 * * * *')->withoutOverlapping(14);
Schedule::command('ecfhl:refresh-fantasy-rosters')->cron('*/15 * * * *')->withoutOverlapping(14)->runInBackground();
Schedule::command('ecfhl:refresh-starting-goalies')->cron('*/5 * * * *')->withoutOverlapping(4)->runInBackground();
Schedule::command('ecfhl:refresh-pp-lines')->cron('0 * * * *')->withoutOverlapping(55)->runInBackground();
Schedule::command('ecfhl:refresh-odds')->cron('0 */2 * * *')->withoutOverlapping(110)->runInBackground();

require __DIR__.'/available-goalies.php';