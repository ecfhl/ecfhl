<?php

use App\Support\DailyFaceoffPowerPlay;
use App\Support\DailyFaceoffStartingGoalies;
use App\Support\FantraxAvailablePlayers;
use App\Support\FantraxDailyScores;
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
        $merged=[];foreach(array_merge($all['rows'],$goalies['rows']) as $p){$key=mb_strtolower(trim($p['player_name'])).'|'.strtoupper(trim($p['team']));$merged[$key]=$p;}
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

            $scoreNotifications=[];
            $isCurrentFantasyDay=$date->toDateString()===CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay()->toDateString();
            if($isCurrentFantasyDay){
                foreach($rows as $row){
                    $key=$normTeam($row['nhl_team']??'').'|'.$normName($row['player_name']??'');
                    $previous=$previousScores[$key]??null;
                    if(!$previous)continue;

                    $name=trim((string)$row['player_name']);
                    $team=$normTeam($row['nhl_team']??'');
                    $label=$name.($team!==''?' ('.$team.')':'');
                    $delta=fn($field)=>(int)($row[$field]??0)-(int)($previous->{$field}??0);

                    $goalDelta=max(0,$delta('g'));
                    $ppgDelta=max(0,$delta('ppg'));
                    $shgDelta=max(0,$delta('shg'));
                    if($ppgDelta>0)$scoreNotifications[]='PPG by '.$label;
                    if($shgDelta>0)$scoreNotifications[]='SHG by '.$label;
                    if(max(0,$goalDelta-$ppgDelta-$shgDelta)>0)$scoreNotifications[]='Goal by '.$label;
                    if($delta('a')>0)$scoreNotifications[]='Assist by '.$label;
                    if($delta('gwg')>0)$scoreNotifications[]='GWG by '.$label;
                    if($delta('w')>0)$scoreNotifications[]='Win by '.$label;
                    if($delta('so')>0)$scoreNotifications[]='Shutout by '.$label;
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

            foreach($scoreNotifications as $body){
                try {
                    $webPush->notify('live-score','ECFHL Live Scoring',$body,'/teams/current?date='.$date->toDateString());
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

Artisan::command('ecfhl:refresh-fantasy-rosters', function (FantraxTeamRosters $fantrax) {
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
            foreach ($data['rows'] as $g) {
                $team = $abbr[$g['team_name']] ?? null;
                $opponent = $abbr[$g['opponent_name']] ?? null;
                if (! $team || ! $opponent) throw new \RuntimeException('Unknown Daily Faceoff team. Existing data preserved.');
                $rows[] = [
                    'game_date'=>$day, 'team'=>$team, 'opponent'=>$opponent,
                    'home_away'=>$g['home_away'], 'player_name'=>$g['player_name'],
                    'starting_status'=>$g['starting_status'], 'source_url'=>$data['url'],
                    'source_updated_at'=>$g['source_updated_at'], 'checked_at'=>$now,
                    'created_at'=>$now, 'updated_at'=>$now,
                ];
            }
            DB::transaction(function () use ($day, $rows) {
                DB::table('active_starting_goalies')->whereDate('game_date', $day)->delete();
                if ($rows) DB::table('active_starting_goalies')->insert($rows);
            });
            $stored = DB::table('active_starting_goalies')->whereDate('game_date', $day)
                ->get(['player_name','team','opponent','home_away','starting_status']);
            foreach($stored as $goalie){
                $key=strtoupper(trim((string)$goalie->team)).'|'.mb_strtolower(trim((string)$goalie->player_name));
                $previous=$previousGoalies[$key]??null;
                if(!$previous)continue;
                $oldStatus=trim((string)($previous->starting_status??''));
                $newStatus=trim((string)($goalie->starting_status??''));
                if($newStatus==='' || strcasecmp($oldStatus,$newStatus)===0)continue;

                $body=$goalie->player_name.' ('.$goalie->team.') is now '.$newStatus.'.';
                try {
                    $webPush->notify('goalie-status','Goalie Status',$body,'/daily-targets?date='.$day);
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