<?php

\Illuminate\Support\Facades\Artisan::command('ecfhl:warm-images', function () {
    $count = \App\Support\TeamImages::warm();
    $this->info('Generated WebP thumbnails and cached originals for '.$count.' uploaded images.');
});

use App\Support\DailyFaceoffPowerPlay;
use App\Support\DailyFaceoffStartingGoalies;
use App\Support\FantraxAvailablePlayers;
use App\Support\FantraxDailyMoves;
use App\Support\FantraxTeamRosters;
use App\Support\FantraxStandings;
use App\Support\FantraxSchedule;
use App\Support\FantasyDay;
use App\Support\NhlOdds;
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
    $base=app(\App\Support\FantasyDay::class)->today();$failed=false;
    foreach([$base,$base->addDay()] as $date){try{
        Log::info('Fantrax daily players refresh started',['date'=>$date->format('Y-m-d'),'url'=>$fantrax->url($date)]);
        $all=$fantrax->fetch($date,'ALL');
        try{$goalies=$fantrax->fetch($date,'G');}catch(\Throwable $e){Log::warning('Dedicated Fantrax goalie fetch failed',['date'=>$date->format('Y-m-d'),'error'=>$e->getMessage()]);$goalies=['rows'=>[]];}
        $merged=[];foreach(array_merge($all['rows'],$goalies['rows']) as $p){$key=mb_strtolower(trim($p['player_name'])).'|'.strtoupper(trim($p['team'])).'|'.strtoupper(trim((string)($p['position']??'')));$merged[$key]=$p;}
        $now=now();$rows=array_map(function($p)use($date,$now){$opp=trim((string)($p['opponent']??''));$away=str_starts_with($opp,'@');return ['game_date'=>$date->format('Y-m-d'),'player_id'=>$p['player_id'],'player_name'=>$p['player_name'],'team'=>$p['team'],'position'=>$p['position'],'opponent'=>ltrim($opp,'@'),'home_away'=>$opp===''?null:($away?'AWAY':'HOME'),'game_time'=>$p['game_time']??null,'game_started'=>(bool)($p['game_started']??false),'availability'=>$p['availability'],'waiver_day'=>$p['waiver_day'],'injury_status'=>$p['injury_status'],'projected_fpts'=>$p['projected_fpts'],'fantrax_url'=>$p['fantrax_url'],'source_rank'=>$p['source_rank'],'last_update'=>$now,'created_at'=>$now,'updated_at'=>$now];},array_values($merged));
        DB::transaction(function()use($date,$rows){DB::table('active_daily_players')->where('game_date',$date->format('Y-m-d'))->delete();if($rows)DB::table('active_daily_players')->insert($rows);});$count=count($rows);$gcount=count(array_filter($rows,fn($r)=>$r['position']==='G'));$this->info($date->format('Y-m-d').': '.$count.' Fantrax players refreshed ('.$gcount.' goalies)');Log::info('Fantrax daily players refresh completed',['date'=>$date->format('Y-m-d'),'rows'=>$count,'goalies'=>$gcount]);
    }catch(\Throwable $e){$failed=true;Log::error('Fantrax daily players refresh failed',['date'=>$date->format('Y-m-d'),'url'=>$fantrax->url($date),'error'=>$e->getMessage(),'exception'=>get_class($e)]);$this->error($date->format('Y-m-d').': '.$e->getMessage());}}
    if(!$failed && \App\Support\CollectorSchedules::all()['available-goalies']['frequency']!=='off'){try{Artisan::call('ecfhl:refresh-available-goalies');$this->line(trim(Artisan::output()));}catch(\Throwable $e){$failed=true;$this->error('Available goalies: '.$e->getMessage());}}
    return $failed?1:0;
});

require __DIR__.'/live-scoring-console.php';
require __DIR__.'/projections-console.php';
require __DIR__.'/database-console.php';

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
    $path = storage_path('app/standings.lock');
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    $lock = fopen($path, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        $this->warn('Standings refresh already running.');
        if ($lock) fclose($lock);
        return 1;
    }
    $seasonId='2026-27';
    $source='https://www.fantrax.com/fantasy/league/'.FantraxStandings::LEAGUE_ID.'/standings';

    try {
        DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>'standings'], ['status'=>'running','message'=>'Collecting Fantrax regular-season standings.','ran_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
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
        \App\Support\PublicData::forget('archive:team_seasons');
        \App\Support\PublicData::forget('joined-team-seasons');
        \App\Support\PublicData::forget('current-teams');

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

        \App\Support\PublicData::forget('current-periods');
        DB::table('job_run_history')->insert([
            'job_name'=>'ecfhl:refresh-current-standings',
            'target_date'=>$data['as_of_date']??$data['completed_through'],
            'rows_processed'=>count($updates),
            'completed_at'=>now(),
            'created_at'=>now(),
            'updated_at'=>now(),
        ]);

        $message=count($updates).' official Fantrax regular-season standings rows refreshed for '.($data['as_of_date']??$data['completed_through']);
        DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>'standings'], ['status'=>'success','message'=>$message,'ran_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
        $this->info($message);
        return 0;
    } catch (\Throwable $e) {
        Log::error('Fantrax current standings refresh failed',['error'=>$e->getMessage()]);
        DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>'standings'], ['status'=>'failed','message'=>$e->getMessage(),'ran_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
        $this->error($e->getMessage());
        return 1;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
});

Artisan::command('ecfhl:refresh-fantasy-rosters', function (FantraxTeamRosters $fantrax, FantraxDailyMoves $dailyMoves) {
    $base=app(\App\Support\FantasyDay::class)->today();
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
                DB::table('active_fantasy_rosters')->where('game_date',$date->toDateString())->delete();
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
            DB::table('team_daily_moves')->where('move_date',$base->toDateString())->delete();
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
        \App\Support\PublicData::forget('standings-awards');
        $this->info(count($moveRows).' team move-limit rows refreshed.');

    } catch (\Throwable $e) {
        $failed=true;
        Log::error('Fantrax daily move refresh failed',['date'=>$base->toDateString(),'error'=>$e->getMessage()]);
        $this->error('Daily moves: '.$e->getMessage());
    }

    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-starting-goalies', function (DailyFaceoffStartingGoalies $dfo, WebPush $webPush) {
    $abbr=['Anaheim Ducks'=>'ANA','Boston Bruins'=>'BOS','Buffalo Sabres'=>'BUF','Calgary Flames'=>'CGY','Carolina Hurricanes'=>'CAR','Chicago Blackhawks'=>'CHI','Colorado Avalanche'=>'COL','Columbus Blue Jackets'=>'CBJ','Dallas Stars'=>'DAL','Detroit Red Wings'=>'DET','Edmonton Oilers'=>'EDM','Florida Panthers'=>'FLA','Los Angeles Kings'=>'LAK','Minnesota Wild'=>'MIN','Montreal Canadiens'=>'MTL','Nashville Predators'=>'NSH','New Jersey Devils'=>'NJD','New York Islanders'=>'NYI','New York Rangers'=>'NYR','Ottawa Senators'=>'OTT','Philadelphia Flyers'=>'PHI','Pittsburgh Penguins'=>'PIT','San Jose Sharks'=>'SJS','Seattle Kraken'=>'SEA','St. Louis Blues'=>'STL','Tampa Bay Lightning'=>'TBL','Toronto Maple Leafs'=>'TOR','Utah Mammoth'=>'UTA','Vancouver Canucks'=>'VAN','Vegas Golden Knights'=>'VGK','Washington Capitals'=>'WSH','Winnipeg Jets'=>'WPG'];$base=app(\App\Support\FantasyDay::class)->today();$failed=false;
    foreach ([$base, $base->addDay()] as $date) {
        $day = $date->format('Y-m-d');
        try {
            $previousGoalies=DB::table('active_starting_goalies')
                ->where('game_date',$day)
                ->get()
                ->keyBy(fn($r)=>strtoupper(trim((string)$r->team)).'|'.mb_strtolower(trim((string)$r->player_name)));

            $previousStatuses=\App\Support\GoalieStatusChanges::snapshot($day,$previousGoalies);
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
                $status=DailyFaceoffStartingGoalies::normalizeStatus($status);

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
                    if($row['starting_status']==='Confirmed'){
                        DB::table('active_starting_goalies')->where('game_date',$day)->where('team',$row['team'])
                            ->where('player_name','!=',$row['player_name'])->update(['starting_status'=>'Not starting','updated_at'=>$row['updated_at']]);
                    }
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
            $stored = DB::table('active_starting_goalies')->where('game_date', $day)
                ->get(['player_name','team','opponent','home_away','starting_status']);
            foreach(\App\Support\GoalieStatusChanges::snapshot($day,$stored) as $key=>$goalie){
                $previous=$previousStatuses[$key]??null;
                // A new named starter on an established day has no old row; do not lose its announcement.
                // The first snapshot of a day remains a baseline, avoiding a flood of old statuses.
                if(!$previous && $previousGoalies->isEmpty())continue;
                $oldStatus=trim((string)($previous->starting_status??'Unconfirmed'));
                $newStatus=trim((string)($goalie->starting_status??''));
                if($newStatus==='' || strcasecmp($newStatus,'Not starting')===0 || strcasecmp($oldStatus,$newStatus)===0)continue;

                $body=\App\Support\PlayerName::display($goalie->player_name).' ('.$goalie->team.') is now '.($newStatus==='Not starting'?'Not Starting':$newStatus).'.';
                $fantraxGoalieUrl='https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName='.rawurlencode(strtolower((string)$goalie->player_name)).';miscDisplayType=1;statusOrTeamFilter=ALL;positionOrGroup=ALL;pageNumber=1';
                try {
                    $available=app(\App\Support\OwnerGoalies::class)->available($day)->contains(fn($g)=>\App\Support\OwnerNotificationPolicy::goalieKey($g->team,$g->player_name)===\App\Support\OwnerNotificationPolicy::goalieKey($goalie->team,$goalie->player_name));
                    $webPush->notify('goalie-status','Goalie Status',$body,$fantraxGoalieUrl,null,[
                        'game_date'=>$day,'available'=>$available,
                        'goalie_key'=>\App\Support\OwnerNotificationPolicy::goalieKey($goalie->team,$goalie->player_name),
                    ]);
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
    $base = app(\App\Support\FantasyDay::class)->today();
    $wanted = [$base->toDateString(), $base->addDay()->toDateString()];
    try {
        $data = $odds->fetch();
        $counts = $odds->publish($data, $wanted);
        foreach ($wanted as $day) {
            $count = $counts[$day];
            $this->info($day.': '.$count.' NHL team odds refreshed');
        }
        return 0;
    } catch (\Throwable $e) {
        Log::error('NHL odds refresh failed', ['error'=>$e->getMessage()]);
        $this->error($e->getMessage().'. Existing odds data preserved.');
        return 1;
    }
});

require __DIR__.'/available-goalies.php';

require __DIR__.'/collectors.php';
