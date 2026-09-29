<?php

use App\Support\DailyFaceoffPowerPlay;
use App\Support\DailyFaceoffStartingGoalies;
use App\Support\FantraxAvailablePlayers;
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
    foreach($teams as $team=>$slug){try{$data=$scraper->fetch($team,$slug);$stored=DB::table('active_pp_lines')->where('team',$team)->max('last_update');if($stored&&$data['lastUpdate']->lessThanOrEqualTo(CarbonImmutable::parse($stored))){$this->line("{$team}: unchanged");continue;}DB::transaction(function()use($data,$team){DB::table('active_pp_lines')->where('team',$team)->delete();$now=now();$rows=array_map(fn($p)=>['team'=>$team,'player_name'=>$p['player_name'],'pp_unit'=>$p['pp_unit'],'unit_position'=>$p['unit_position'],'source_url'=>$data['url'],'last_update'=>$data['lastUpdate'],'checked_at'=>$now,'created_at'=>$now,'updated_at'=>$now],$data['players']);DB::table('active_pp_lines')->insert($rows);});$this->info("{$team}: refreshed PP1/PP2");}catch(\Throwable $e){Log::error('Daily Faceoff PP refresh failed',['team'=>$team,'error'=>$e->getMessage()]);$this->error("{$team}: {$e->getMessage()}");}}return 0;
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
Schedule::command('ecfhl:refresh-daily-players')->hourlyAt(0)->withoutOverlapping(55);
Schedule::command('ecfhl:refresh-starting-goalies')->hourlyAt(1)->withoutOverlapping(55)->runInBackground();
Schedule::command('ecfhl:refresh-pp-lines')->cron('2 */4 * * *')->withoutOverlapping(240)->runInBackground();

require __DIR__.'/available-goalies.php';