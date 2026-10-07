<?php

putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); });

use App\Support\FantraxProjectionSource;
use App\Support\PlayerProjections;
use App\Support\ProjectionMath;
use App\Support\RefreshPlayerProjections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

function checkProjection(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}
function rejectsProjection(callable $call, string $message): void
{
    try { $call(); } catch (RuntimeException $e) { return; }
    throw new RuntimeException($message);
}

foreach (['2026_09_29_000001_create_active_daily_players_table.php', '2026_09_29_000002_create_active_starting_goalies_table.php', '2026_09_29_000004_create_active_available_goalies_table.php', '2026_10_03_140000_create_player_projections.php'] as $name) (require __DIR__.'/../database/migrations/'.$name)->up();
(require __DIR__.'/../database/migrations/2026_10_03_210000_add_season_actuals_to_player_projections.php')->up();
(require __DIR__.'/../database/migrations/2026_10_04_210000_create_season_player_stats.php')->up();


// The migration must cover every chunk, preserving unknown rates as null.
for ($i=1; $i<=250; $i++) DB::table('season_player_stats')->insert(['player_id'=>'legacy'.sprintf('%04d', $i), 'season_id'=>'2026-27', 'player_name'=>'Legacy '.$i,
    'nhl_team'=>'EDM', 'position'=>'F', 'rookie'=>false, 'season_fpts'=>2, 'season_gp'=>3, 'season_fpts_per_game'=>2/3,
    'stats_json'=>'{}', 'stats_through'=>'2026-11-03', 'refreshed_at'=>now()]);
(require __DIR__.'/../database/migrations/2026_10_05_010000_expand_player_projection_coverage.php')->up();
checkProjection(DB::table('player_projections')->count()===250, 'Backfill all existing season players across multiple chunks.');
checkProjection(DB::table('player_projections')->whereNotNull('fpts_per_game_7d')->count()===0, 'Do not invent rolling inputs during migration.');
checkProjection(DB::table('player_projections')->whereNotNull('projected_fpts_per_game')->count()===0, 'Disabled season input must not become an invented fallback.');
DB::table('player_projections')->delete(); DB::table('season_player_stats')->delete();

class TestProjectionSource extends FantraxProjectionSource
{
    public int $captures = 0;
    public array $calls = [];
    public bool $broken = false;
    public bool $missing = false;
    public bool $extra = false;
    public function baseline(): array
    {
        $this->captures++;
        $rows = [];
        for ($i=1; $i<=1000; $i++) $rows[] = ['player_id'=>'p'.$i, 'season_id'=>self::SEASON_ID,
            'player_name'=>$i<3 ? 'Same Name' : 'Player '.$i, 'nhl_team'=>'MTL', 'position'=>'F', 'source_rank'=>$i,
            'fantrax_fpts_per_game'=>2, 'fantrax_season_fpts'=>164, 'season_start'=>'2026-09-29'];
        return $rows;
    }
    public function actual(string $start, string $end): array
    {
        $this->calls[] = [$start, $end];
        if ($this->broken) throw new RuntimeException('Simulated source failure');
        $rows = [];
        for ($i=1; $i<=1000; $i++) $rows['p'.$i] = ['player_id'=>'p'.$i, 'fpts'=>12, 'gp'=>3];
        // Zero-game and negative-point rates are intentionally meaningful inputs.
        $rows['p3'] = ['fpts'=>0, 'gp'=>0];
        $rows['p5'] += ['player_name'=>'Player 5', 'position'=>'F', 'nhl_team'=>'MTL', 'rookie'=>true, 'stats'=>['G'=>'3','TOI'=>'71:11'], 'stat_columns'=>['G'=>'Goals','TOI'=>'Time on ice']];
        $rows['p4'] = ['fpts'=>-4, 'gp'=>2];
        if ($this->extra) {
            $rows['formenton'] = ['player_name'=>'Alex Formenton', 'position'=>'F', 'nhl_team'=>'EDM', 'fpts'=>2, 'gp'=>3];
            $rows['partial'] = ['player_name'=>'Partial Inputs', 'position'=>'D', 'nhl_team'=>'EDM', 'fpts'=>20, 'gp'=>2];
            if ($start !== '2026-09-29') unset($rows['partial']);
        }
        if ($this->missing) unset($rows['p1000']);
        return $rows;
    }
}

$source = new TestProjectionSource;
$refresh = new RefreshPlayerProjections($source);
$date = CarbonImmutable::parse('2026-11-03 04:00', 'America/Halifax');
$refresh->refresh($date);
checkProjection(DB::table('player_projections')->count() === 1000, 'Store exactly 1,000 projections.');
checkProjection($source->calls === [['2026-09-29','2026-11-03'], ['2026-10-28','2026-11-03'], ['2026-10-21','2026-11-03'], ['2026-10-14','2026-11-03']], 'Use season totals and inclusive 7/14/21 calendar days ending today, including DST.');
checkProjection((float)DB::table('player_projections')->where('player_id','p5')->value('projected_fpts_per_game') === 3.0, 'Apply the 50/25/15/10 weights.');
checkProjection((float)DB::table('player_projections')->where('player_id','p3')->value('projected_fpts_per_game') === 1.0, 'Zero-game windows contribute zero while the baseline keeps its 50% weight.');
checkProjection((float)DB::table('player_projections')->where('player_id','p4')->value('projected_fpts_per_game') === 0.0, 'Preserve negative points.');
checkProjection(DB::table('season_player_stats')->count()===999 && (bool)DB::table('season_player_stats')->where('player_id','p5')->value('rookie'), 'Store full season stat rows and rookie flags atomically.');
checkProjection(json_decode(DB::table('season_player_stats')->where('player_id','p5')->value('stats_json'),true)['TOI']==='71:11', 'Preserve source stat formatting.');
$frozen = DB::table('player_projection_baselines')->orderBy('source_rank')->get()->toJson();
$before = DB::table('player_projections')->orderBy('player_id')->get()->toJson();
$refresh->refresh($date);
checkProjection(count($source->calls) === 4 && $source->captures === 1, 'Skip repeated routine refreshes on the same date.');
$refresh->refresh($date, true);
checkProjection(count($source->calls) === 8 && $source->captures === 1, 'Manual regeneration only refreshes actual windows.');
checkProjection(DB::table('player_projection_baselines')->orderBy('source_rank')->get()->toJson() === $frozen, 'Frozen Fantrax baseline remains byte-for-byte unchanged.');
$before = DB::table('player_projections')->orderBy('player_id')->get()->toJson();
$seasonBefore = DB::table('season_player_stats')->orderBy('player_id')->get()->toJson();
$source->broken = true;
rejectsProjection(fn()=>$refresh->refresh($date->addDay()), 'Propagate a source failure.');
checkProjection(DB::table('player_projections')->orderBy('player_id')->get()->toJson() === $before, 'A failed source must preserve all stored projections.');
checkProjection(DB::table('season_player_stats')->orderBy('player_id')->get()->toJson()===$seasonBefore, 'Source failures must preserve full season stats.');
$source->broken = false; $source->missing = true;
$refresh->refresh($date->addDay());
checkProjection(DB::table('player_projections')->where('player_id','p1000')->value('season_fpts_per_game') === null, 'Absent player stats remain unavailable rather than zero.');
checkProjection((float)DB::table('player_projections')->where('player_id','p1000')->value('projected_fpts_per_game') === 2.0, 'Use the remaining baseline at 100% when actual sources are absent.');
$source->missing = false;
$refresh->refresh($date, true);
$map = new PlayerProjections;
$players = $map->decorate(collect([(object)['player_id'=>'p3','player_name'=>'Wrong Name'], (object)['player_id'=>'outside','projected_fpts_per_game'=>9]]));
checkProjection($players[0]->projected_fpts_per_game === 1.0, 'Use Fantrax ID over names.');
checkProjection($players[1]->projected_fpts_per_game === null, 'Do not mix Fantrax projections into the custom model for untracked players.');
checkProjection($map->find((object)['player_name'=>'Same Name','team'=>'MTL']) === null, 'Avoid ambiguous names in legacy rows.');
checkProjection($map->find((object)['player_name'=>'5, Player'])?->player_id === 'p5', 'Normalize legacy last-name-first names.');
checkProjection(abs(ProjectionMath::average(2, 4, 6, 8) - 3.7) < 0.000001, 'Apply distinct weights to all four different inputs.');
checkProjection(ProjectionMath::rate(10, 4) === 2.5, 'Divide by GP, not days.');

DB::table('active_daily_players')->insert(['game_date'=>'2026-11-03','player_id'=>'p5','player_name'=>'Player 5','team'=>'MTL','position'=>'F',
    'opponent'=>'TOR','availability'=>'FA','projected_fpts'=>999,'source_rank'=>5,'last_update'=>now(),'created_at'=>now(),'updated_at'=>now()]);
$tips = App\Support\AiTips::groups([], '2026-11-03');
checkProjection($tips['F'][0]['projected_points'] === 3.0, 'Daily Targets use the stored custom per-game value, not season totals.');
checkProjection($tips['F'][0]['player_id'] === 'p5', 'Daily Targets retain Fantrax IDs for duplicate-name players.');
DB::table('active_daily_players')->insert(['game_date'=>'2026-11-03','player_id'=>'outside','player_name'=>'Untracked','team'=>'MTL','position'=>'F',
    'opponent'=>'TOR','availability'=>'FA','projected_fpts'=>999,'source_rank'=>1001,'last_update'=>now(),'created_at'=>now(),'updated_at'=>now()]);
$untracked = collect(App\Support\AiTips::groups([], '2026-11-03')['F'])->first(fn($p)=>$p['player_id']==='outside');
checkProjection($untracked['projected_points'] === null, 'An untracked target has no custom estimate, rather than a false zero or a Fantrax season total.');
$participant = ['player_id'=>'p5','fantasy_team_id'=>'t1','player_name'=>'Player 5','nhl_team'=>'MTL','position'=>'F',
    'roster_status'=>'ACTIVE','scoring_status'=>'ACTIVE','daily_fpts'=>8,'daily_projected_fpts'=>99,'game_display'=>'vs TOR',
    'game_status'=>'1','game_id'=>'g1','gp'=>1,'stats'=>[]];
$bench = array_replace($participant, ['player_id'=>'p3','scoring_status'=>'BENCH','daily_fpts'=>0]);
$view = (new App\Support\LiveScoring\ViewData)->teams(['fantasy_date'=>'2026-11-03','players'=>[$participant,$bench],
    'teams'=>['t1'=>['name'=>'Example','daily_fpts'=>8,'period_fpts'=>20,'daily_projected_fpts'=>999]]]);
checkProjection($view['t1']['daily_projected_fpts'] === 3.0, 'Matchup projection totals sum custom active-player rates and exclude the bench.');
checkProjection($view['t1']['today_fpts'] === 8 && $view['t1']['week_fpts'] === 20, 'Custom projections never change actual or period scores.');
checkProjection($view['t1']['positions']['F']['rows'][0]->projected_fpts_per_game === 3.0, 'Live matchup player values use the custom model.');

$source->calls = [];
$refresh->refresh(CarbonImmutable::parse('2026-10-03 04:00', 'America/Halifax'), true);
checkProjection($source->calls === [['2026-09-29','2026-10-03']], 'Clamp windows to the current season and reuse identical early-season ranges.');
$source->calls = [];
$refresh->refresh(CarbonImmutable::parse('2026-09-28 04:00', 'America/Halifax'), true);
checkProjection($source->calls === [], 'Before any completed season games, use zero actual rates without querying future dates.');
checkProjection((float)DB::table('player_projections')->where('player_id','p5')->value('projected_fpts_per_game') === 1.0, 'Keep the 50% baseline weight before the first game.');

// Admin weight changes reuse stored inputs and must also survive the next collector run.
(require __DIR__.'/../database/migrations/2026_10_04_192000_create_projection_settings.php')->up();
checkProjection(App\Support\ProjectionSettings::weights() === ProjectionMath::DEFAULT_WEIGHTS, 'Preserve existing weights on migration.');
DB::table('player_projections')->where('player_id', 'p5')->update(['season_fpts_per_game'=>10, 'fpts_per_game_7d'=>4, 'fpts_per_game_14d'=>6, 'fpts_per_game_21d'=>8]);
$statsBefore = DB::table('player_projections')->where('player_id', 'p5')->first();
$sourceCalls = count($source->calls);
$customWeights = ['fantrax'=>20, 'season'=>30, '7d'=>25, '14d'=>15, '21d'=>10];
DB::table('player_projections')->where('player_id', 'p6')->update(['season_fpts_per_game'=>100]);
$previewBefore = DB::table('player_projections')->orderBy('player_id')->get()->toJson();
$preview = App\Support\ProjectionSettings::preview($customWeights);
checkProjection(count($preview['players']) === 10 && $preview['count'] === 1000 && $preview['players'][0]['player_id'] === 'p6', 'Preview the top 10 ranked by the proposed custom formula.');
checkProjection(abs($preview['players'][0]['myproj'] - 30.4) < 0.000001 && $preview['players'][0]['season_fpts_per_game'] === 100.0, 'Preview compares proposed MyProj with actual season FPts/GP.');
checkProjection(DB::table('player_projections')->orderBy('player_id')->get()->toJson() === $previewBefore && App\Support\ProjectionSettings::weights() === ProjectionMath::DEFAULT_WEIGHTS, 'Preview must not change saved projections or weights.');
checkProjection(App\Support\ProjectionSettings::save($customWeights) === 1000, 'Recalculate all 1,000 stored projections.');
checkProjection(abs((float)DB::table('player_projections')->where('player_id', 'p6')->value('projected_fpts_per_game') - $preview['players'][0]['myproj']) < 0.000001, 'Saving and previewing must use the same calculation.');
checkProjection(abs((float)DB::table('player_projections')->where('player_id', 'p5')->value('projected_fpts_per_game') - 6.1) < 0.000001, 'Apply distinct weights including season stats.');
checkProjection(count($source->calls) === $sourceCalls, 'Changing weights must not fetch Fantrax.');
checkProjection(DB::table('player_projections')->where('player_id', 'p5')->value('refreshed_at') === $statsBefore->refreshed_at, 'Changing weights must not disguise old collected stats as fresh.');
checkProjection(abs((new PlayerProjections)->rate((object)['player_id'=>'p5']) - 6.1) < 0.000001, 'New requests immediately use recalculated values.');
$savedBefore = DB::table('player_projections')->orderBy('player_id')->get()->toJson();
foreach ([['fantrax'=>21]+$customWeights, ['season'=>-1]+$customWeights, ['fantrax'=>20.001]+$customWeights, ['fantrax'=>101]+$customWeights] as $invalid) {
    try { App\Support\ProjectionSettings::save($invalid); throw new RuntimeException('Invalid weights accepted.'); }
    catch (Illuminate\Validation\ValidationException $e) {}
}
checkProjection(DB::table('player_projections')->orderBy('player_id')->get()->toJson() === $savedBefore, 'Invalid weights must not mutate stored projections.');
$lock=fopen(storage_path('app/player-projections.lock'), 'c');flock($lock, LOCK_EX);
try { App\Support\ProjectionSettings::save($customWeights); throw new RuntimeException('Concurrent collector overwrite allowed.'); }
catch (Illuminate\Validation\ValidationException $e) {} finally { flock($lock, LOCK_UN);fclose($lock); }
$refresh->refresh($date, true);
checkProjection(abs((float)DB::table('player_projections')->where('player_id', 'p5')->value('projected_fpts_per_game') - 3.6) < 0.000001, 'Daily refresh uses the saved five-source weights.');
checkProjection(DB::table('player_projection_baselines')->orderBy('source_rank')->get()->toJson() === $frozen, 'Weight changes preserve the frozen baseline.');
App\Support\ProjectionSettings::save(['fantrax'=>0,'season'=>100,'7d'=>0,'14d'=>0,'21d'=>0]);
checkProjection((float)DB::table('player_projections')->where('player_id', 'p5')->value('projected_fpts_per_game') === 4.0, 'Support 100% season with all other sources disabled.');
App\Support\ProjectionSettings::save(ProjectionMath::DEFAULT_WEIGHTS);

// A missing Fantrax baseline must not exclude players with actual inputs.
$source->extra = true;
$weights = ['fantrax'=>60,'season'=>20,'7d'=>20,'14d'=>0,'21d'=>0];
App\Support\ProjectionSettings::save($weights);
$refresh->refresh($date, true);
checkProjection(DB::table('player_projections')->count()===1002, 'Collect projections beyond the frozen top 1,000.');
checkProjection(abs((new PlayerProjections)->rate((object)['player_id'=>'formenton']) - 2/3)<0.00001, 'Missing Fantrax input rescales season and 7-day weights from 20/20 to 50/50.');
checkProjection((new PlayerProjections)->find((object)['player_name'=>'Alex Formenton','team'=>'EDM'])?->player_id==='formenton', 'Other pages can resolve players outside the frozen baseline.');
checkProjection(DB::table('player_projections')->where('player_id','partial')->value('fpts_per_game_7d')===null && (new PlayerProjections)->rate((object)['player_id'=>'partial'])===10.0, 'Missing rolling input rescales the available season input to 100%.');
$preview = App\Support\ProjectionSettings::preview($weights);
$partial = collect($preview['players'])->firstWhere('player_id','partial');
checkProjection($partial && $partial['myproj']===10.0 && $partial['name']==='Inputs, Partial', 'Preview includes players beyond the frozen baseline with normalized rates.');
App\Support\ProjectionSettings::save($weights);
checkProjection((new PlayerProjections)->rate((object)['player_id'=>'partial'])===$partial['myproj'], 'Preview, SQL save and refresh use the same missing-input calculation.');
checkProjection(abs(ProjectionMath::weighted(['fantrax'=>null,'season'=>4,'7d'=>0],$weights)-2)<0.000001, 'A true zero remains a valid input in the denominator.');
App\Support\ProjectionSettings::save(['fantrax'=>100,'season'=>0,'7d'=>0,'14d'=>0,'21d'=>0]);
checkProjection((new PlayerProjections)->rate((object)['player_id'=>'formenton'])===null, 'All enabled inputs missing leaves EC Proj null across pages.');
checkProjection((new PlayerProjections)->decorate(collect([(object)['player_id'=>'formenton']]))[0]->projected_fpts_per_game===null, 'Decoration never converts missing projections to zero.');
App\Support\ProjectionSettings::save($weights);
$source->calls=[]; $refresh->refresh($date);
checkProjection($source->calls===[], 'Expanded coverage still skips duplicate daily collections.');

// The source parser must reject changed dates, missing GP and truncated pages.
class FixtureProjectionSource extends FantraxProjectionSource
{
    public string $mode = '';
    public int $pageCount = 1;
    public array $batches = [];
    protected function pages(array $args, array $pages): array
    {
        $this->batches[] = $pages;
        $goalie = $args['positionOrGroup'] === 'POS_201';
        $tracked = $args['scoringCategoryType'] === '5';
        $start = $this->mode === 'date' ? '2026-10-26' : $args['startDate'];
        $headers = [['key'=>'fpts'], ['shortName'=>$this->mode === 'gp' ? 'OTHER' : 'GP', 'scipId'=>'gp', 'name'=>'Games Played'], ($tracked ? ['shortName'=>$this->mode === 'shg' ? 'OTHER' : 'SHG','scipId'=>'shg','name'=>'Short-handed goals'] : ['shortName'=>'TOI','scipId'=>'toi','name'=>'Time on ice'])];
        $data = ['displayedStatusOrTeam'=>'ALL','displayedPosOrGroup'=>$args['positionOrGroup'], 'displayedSelections'=>['datePlaying'=>'ALL','searchName'=>'', 'displayedSeasonOrProjection'=>['code'=>self::ACTUAL],
            'displayedStartDate'=>CarbonImmutable::parse($start, 'America/New_York')->getTimestampMs(),
            'displayedEndDate'=>CarbonImmutable::parse($args['endDate'], 'America/New_York')->getTimestampMs()],
            'paginatedResultSet'=>['totalNumPages'=>$this->pageCount,'pageNumber'=>1,'totalNumResults'=>$this->mode === 'partial' ? $this->pageCount + 1 : $this->pageCount],
            'tableHeader'=>['cells'=>$headers], 'statsTable'=>[['scorer'=>['scorerId'=>$goalie?'goalie':'skater', 'name'=>'Test Player', 'teamShortName'=>'MTL', 'posShortNames'=>$goalie?'G':'F', 'rookie'=>true], 'cells'=>[['content'=>'-1'],['content'=>'2'],['content'=>$tracked?'3':'71:11']]]]];
        $result = [];
        foreach ($pages as $page) {
            $row = $data;
            $row['paginatedResultSet']['pageNumber'] = $page;
            if ($this->pageCount > 1) $row['statsTable'][0]['scorer']['scorerId'] .= '-'.$page;
            $result[$page] = $row;
        }
        return $result;
    }
}
$parser = new FixtureProjectionSource;
$parsed = $parser->actual('2026-10-27','2026-11-03');
checkProjection(count($parsed) === 2 && $parsed['goalie']['gp'] === 2, 'Collect skater and goalie GP separately.');
$details=$parser->seasonActual('2026-10-27','2026-11-03');
checkProjection($details['skater']['stats']['SHG']==='3' && $details['skater']['rookie']===true && $details['skater']['stats']['TOI']==='71:11' && $details['goalie']['position']==='G', 'Capture Fantrax metadata, official rookie flags and complete stat columns.');
$parser->mode = 'shg';
rejectsProjection(fn()=>$parser->seasonActual('2026-10-27','2026-11-03'), 'Reject a missing tracked SHG column rather than publish false zeroes.');
$parser->mode = '';
checkProjection(!isset($parser->actual('2026-10-27','2026-11-03')['skater']['stats']), 'Keep rolling window payloads small after season collection.');
foreach (['date','gp','partial'] as $mode) {
    $parser->mode = $mode;
    rejectsProjection(fn()=>$parser->actual('2026-10-27','2026-11-03'), 'Reject '.$mode.' source corruption.');
}
$parser->mode = ''; $parser->pageCount = 10; $parser->batches = [];
checkProjection(count($parser->actual('2026-10-27','2026-11-03')) === 20, 'Parse every page across both groups.');
checkProjection(max(array_map('count', $parser->batches)) <= 3, 'Keep actual-stat collection batches within production memory limits.');
// Zero-game players survive only when they appear on Daily Faceoff.
checkProjection(!DB::table('season_player_stats')->where('player_id','p3')->exists(),'Do not persist an unlisted zero-game player');
(require __DIR__.'/../database/migrations/2026_09_29_000005_create_active_line_combinations_table.php')->up();
DB::table('active_line_combinations')->insert(['team'=>'MTL','player_name'=>'3, Player','position_group'=>'F','line_number'=>1,'source_url'=>'https://www.dailyfaceoff.com','last_update'=>now()]);
$refresh->refresh($date,true);
checkProjection(DB::table('season_player_stats')->where('player_id','p3')->value('season_gp')===0,'Daily Faceoff zero-game rookie must retain a stat line');
class ScopedProjectionSource extends FixtureProjectionSource
{
 public bool $sortBroken=false;
 public bool $truncated=false;
 protected function pages(array $args,array $pages): array {
  $data=parent::pages($args,$pages);
  foreach($data as $page=>&$row){
   $row['displayedSortType']=$this->sortBroken?'SCORE':'SCORING_CATEGORY';
   $row['displayedScipId']=$args['scipId'];$row['displayedSortReversed']=false;
   $row['paginatedResultSet']['maxResultsPerPage']=1;
   $row['statsTable'][0]['cells'][1]['content']=$page===1?'2':'0';
   $row['statsTable'][0]['cells'][0]['content']=$page===1?'-1':'0';
   if($this->truncated)$row['statsTable']=[];
  }
  return $data;
 }
}
$scoped=new ScopedProjectionSource;$scoped->pageCount=10;
$scope=new App\Support\StatsPlayerScope;
$scoped->scopeToActiveStats([['player_id'=>'listed','player_name'=>'Player 3','nhl_team'=>'MTL','position'=>'F']],$scope);
$actual=$scoped->seasonActual('2026-10-27','2026-11-03');
checkProjection(count($scoped->batches)===6,'Stop each of three sorted datasets after the first zero-game page, skipping 24 prospect pages');
checkProjection($actual['skater-1']['gp']===2 && $actual['skater-1']['fpts']===-1.0 && $actual['listed']['gp']===0,'Keep negative scoring participants and synthesize known zero-game exceptions');
checkProjection(!isset($actual['skater-2']) && !isset($actual['goalie-2']),'Exclude untracked zero-game rows');
$scoped->sortBroken=true;
rejectsProjection(fn()=>$scoped->actual('2026-10-27','2026-11-03'),'Reject lost GP sorting instead of skipping real players');
$scoped->sortBroken=false;$scoped->truncated=true;
rejectsProjection(fn()=>$scoped->actual('2026-10-27','2026-11-03'),'Reject a truncated sorted page');
echo "Player projection checks passed.\n";
