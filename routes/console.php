<?php

use App\Support\DailyFaceoffPowerPlay;
use App\Support\DailyFaceoffStartingGoalies;
use App\Support\FantraxAvailablePlayers;
use App\Support\FantraxTeamRosters;
use App\Support\NhlOdds;
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
    $base=CarbonImmutable::now('America/Halifax')->startOfDay();$failed=false;
    foreach([$base,$base->addDay()] as $date){try{
        Log::info('Fantrax daily players refresh started',['date'=>$date->format('Y-m-d'),'url'=>$fantrax->url($date)]);
        $all=$fantrax->fetch($date,'ALL');
        try{$goalies=$fantrax->fetch($date,'G');}catch(\Throwable $e){Log::warning('Dedicated Fantrax goalie fetch failed',['date'=>$date->format('Y-m-d'),'error'=>$e->getMessage()]);$goalies=['rows'=>[]];}
        $merged=[];foreach(array_merge($all['rows'],$goalies['rows']) as $p){$key=mb_strtolower(trim($p['player_name'])).'|'.strtoupper(trim($p['team']));$merged[$key]=$p;}
        $now=now();$rows=array_map(function($p)use($date,$now){$opp=trim((string)($p['opponent']??''));$away=str_starts_with($opp,'@');return ['game_date'=>$date->format('Y-m-d'),'player_name'=>$p['player_name'],'team'=>$p['team'],'position'=>$p['position'],'opponent'=>ltrim($opp,'@'),'home_away'=>$opp===''?null:($away?'AWAY':'HOME'),'availability'=>$p['availability'],'waiver_day'=>$p['waiver_day'],'injury_status'=>$p['injury_status'],'projected_fpts'=>$p['projected_fpts'],'fantrax_url'=>$p['fantrax_url'],'source_rank'=>$p['source_rank'],'last_update'=>$now,'created_at'=>$now,'updated_at'=>$now];},array_values($merged));
        DB::transaction(function()use($date,$rows){DB::table('active_daily_players')->whereDate('game_date',$date->format('Y-m-d'))->delete();if($rows)DB::table('active_daily_players')->insert($rows);});$count=count($rows);$gcount=count(array_filter($rows,fn($r)=>$r['position']==='G'));$this->info($date->format('Y-m-d').': '.$count.' Fantrax players refreshed ('.$gcount.' goalies)');Log::info('Fantrax daily players refresh completed',['date'=>$date->format('Y-m-d'),'rows'=>$count,'goalies'=>$gcount]);
    }catch(\Throwable $e){$failed=true;Log::error('Fantrax daily players refresh failed',['date'=>$date->format('Y-m-d'),'url'=>$fantrax->url($date),'error'=>$e->getMessage(),'exception'=>get_class($e)]);$this->error($date->format('Y-m-d').': '.$e->getMessage());}}
    if(!$failed){try{Artisan::call('ecfhl:refresh-available-goalies');$this->line(trim(Artisan::output()));}catch(\Throwable $e){$failed=true;$this->error('Available goalies: '.$e->getMessage());}}
    return $failed?1:0;
});

Artisan::command('ecfhl:refresh-fantasy-rosters', function (FantraxTeamRosters $fantrax) {
    $base=CarbonImmutable::now('America/Halifax')->startOfDay();
    $failed=false;
    foreach ([$base,$base->addDay()] as $date) {
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

Artisan::command('ecfhl:refresh-starting-goalies', function (DailyFaceoffStartingGoalies $dfo) {
    $abbr=['Anaheim Ducks'=>'ANA','Boston Bruins'=>'BOS','Buffalo Sabres'=>'BUF','Calgary Flames'=>'CGY','Carolina Hurricanes'=>'CAR','Chicago Blackhawks'=>'CHI','Colorado Avalanche'=>'COL','Columbus Blue Jackets'=>'CBJ','Dallas Stars'=>'DAL','Detroit Red Wings'=>'DET','Edmonton Oilers'=>'EDM','Florida Panthers'=>'FLA','Los Angeles Kings'=>'LAK','Minnesota Wild'=>'MIN','Montreal Canadiens'=>'MTL','Nashville Predators'=>'NSH','New Jersey Devils'=>'NJD','New York Islanders'=>'NYI','New York Rangers'=>'NYR','Ottawa Senators'=>'OTT','Philadelphia Flyers'=>'PHI','Pittsburgh Penguins'=>'PIT','San Jose Sharks'=>'SJS','Seattle Kraken'=>'SEA','St. Louis Blues'=>'STL','Tampa Bay Lightning'=>'TBL','Toronto Maple Leafs'=>'TOR','Utah Mammoth'=>'UTA','Vancouver Canucks'=>'VAN','Vegas Golden Knights'=>'VGK','Washington Capitals'=>'WSH','Winnipeg Jets'=>'WPG'];$base=CarbonImmutable::now('America/Halifax')->startOfDay();$failed=false;
    foreach ([$base, $base->addDay()] as $date) {
        $day = $date->format('Y-m-d');
        try {
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
    $base = CarbonImmutable::now('America/Halifax')->startOfDay();
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

Schedule::command('ecfhl:refresh-daily-players')->hourlyAt(0)->withoutOverlapping(55);
Schedule::command('ecfhl:refresh-fantasy-rosters')->hourlyAt(10)->withoutOverlapping(45)->runInBackground();
Schedule::command('ecfhl:refresh-starting-goalies')->everyThirtyMinutes()->withoutOverlapping(25)->runInBackground();
Schedule::command('ecfhl:refresh-pp-lines')->cron('2 */4 * * *')->withoutOverlapping(240)->runInBackground();
Schedule::command('ecfhl:refresh-odds')->cron('6 */4 * * *')->withoutOverlapping(30)->runInBackground();

require __DIR__.'/available-goalies.php';