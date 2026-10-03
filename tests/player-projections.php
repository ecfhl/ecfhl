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

class TestProjectionSource extends FantraxProjectionSource
{
    public int $captures = 0;
    public array $calls = [];
    public bool $broken = false;
    public bool $missing = false;
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
        $rows['p4'] = ['fpts'=>-4, 'gp'=>2];
        if ($this->missing) unset($rows['p1000']);
        return $rows;
    }
}

$source = new TestProjectionSource;
$refresh = new RefreshPlayerProjections($source);
$date = CarbonImmutable::parse('2026-11-03 04:00', 'America/Halifax');
$refresh->refresh($date);
checkProjection(DB::table('player_projections')->count() === 1000, 'Store exactly 1,000 projections.');
checkProjection($source->calls === [['2026-10-27','2026-11-02'], ['2026-10-20','2026-11-02'], ['2026-10-13','2026-11-02']], 'Use inclusive 7/14/21 calendar days ending yesterday, including DST.');
checkProjection((float)DB::table('player_projections')->where('player_id','p5')->value('projected_fpts_per_game') === 3.5, 'Average four FPts/GP components with equal weights.');
checkProjection((float)DB::table('player_projections')->where('player_id','p3')->value('projected_fpts_per_game') === 0.5, 'Zero-game windows contribute zero and still divide by four.');
checkProjection((float)DB::table('player_projections')->where('player_id','p4')->value('projected_fpts_per_game') === -1.0, 'Preserve negative points.');
$frozen = DB::table('player_projection_baselines')->orderBy('source_rank')->get()->toJson();
$before = DB::table('player_projections')->orderBy('player_id')->get()->toJson();
$refresh->refresh($date);
checkProjection(count($source->calls) === 3 && $source->captures === 1, 'Skip repeated routine refreshes on the same date.');
$refresh->refresh($date, true);
checkProjection(count($source->calls) === 6 && $source->captures === 1, 'Manual regeneration only refreshes actual windows.');
checkProjection(DB::table('player_projection_baselines')->orderBy('source_rank')->get()->toJson() === $frozen, 'Frozen Fantrax baseline remains byte-for-byte unchanged.');
$before = DB::table('player_projections')->orderBy('player_id')->get()->toJson();
$source->broken = true;
rejectsProjection(fn()=>$refresh->refresh($date->addDay()), 'Propagate a source failure.');
checkProjection(DB::table('player_projections')->orderBy('player_id')->get()->toJson() === $before, 'A failed source must preserve all stored projections.');
$source->broken = false; $source->missing = true;
rejectsProjection(fn()=>$refresh->refresh($date->addDay()), 'Reject a missing baseline player instead of silently zeroing it.');
checkProjection(DB::table('player_projections')->orderBy('player_id')->get()->toJson() === $before, 'An incomplete dataset must preserve all stored projections.');
$source->missing = false;
$map = new PlayerProjections;
$players = $map->decorate(collect([(object)['player_id'=>'p3','player_name'=>'Wrong Name'], (object)['player_id'=>'outside','projected_fpts_per_game'=>9]]));
checkProjection($players[0]->projected_fpts_per_game === 0.5, 'Use Fantrax ID over names.');
checkProjection($players[1]->projected_fpts_per_game === null, 'Do not mix Fantrax projections into the custom model for untracked players.');
checkProjection($map->find((object)['player_name'=>'Same Name','team'=>'MTL']) === null, 'Avoid ambiguous names in legacy rows.');
checkProjection($map->find((object)['player_name'=>'5, Player'])?->player_id === 'p5', 'Normalize legacy last-name-first names.');
checkProjection(ProjectionMath::rate(10, 4) === 2.5, 'Divide by GP, not days.');

DB::table('active_daily_players')->insert(['game_date'=>'2026-11-03','player_id'=>'p5','player_name'=>'Player 5','team'=>'MTL','position'=>'F',
    'opponent'=>'TOR','availability'=>'FA','projected_fpts'=>999,'source_rank'=>5,'last_update'=>now(),'created_at'=>now(),'updated_at'=>now()]);
$tips = App\Support\AiTips::groups([], '2026-11-03');
checkProjection($tips['F'][0]['projected_points'] === 3.5, 'Daily Targets use the stored custom per-game value, not season totals.');
$participant = ['player_id'=>'p5','fantasy_team_id'=>'t1','player_name'=>'Player 5','nhl_team'=>'MTL','position'=>'F',
    'roster_status'=>'ACTIVE','scoring_status'=>'ACTIVE','daily_fpts'=>8,'daily_projected_fpts'=>99,'game_display'=>'vs TOR',
    'game_status'=>'1','game_id'=>'g1','gp'=>1,'stats'=>[]];
$bench = array_replace($participant, ['player_id'=>'p3','scoring_status'=>'BENCH','daily_fpts'=>0]);
$view = (new App\Support\LiveScoring\ViewData)->teams(['fantasy_date'=>'2026-11-03','players'=>[$participant,$bench],
    'teams'=>['t1'=>['name'=>'Example','daily_fpts'=>8,'period_fpts'=>20,'daily_projected_fpts'=>999]]]);
checkProjection($view['t1']['daily_projected_fpts'] === 3.5, 'Matchup projection totals sum custom active-player rates and exclude the bench.');
checkProjection($view['t1']['today_fpts'] === 8 && $view['t1']['week_fpts'] === 20, 'Custom projections never change actual or period scores.');
checkProjection($view['t1']['positions']['F']['rows'][0]->projected_fpts_per_game === 3.5, 'Live matchup player values use the custom model.');

$source->calls = [];
$refresh->refresh(CarbonImmutable::parse('2026-10-03 04:00', 'America/Halifax'), true);
checkProjection($source->calls === [['2026-09-29','2026-10-02']], 'Clamp windows to the current season and reuse identical early-season ranges.');
$source->calls = [];
$refresh->refresh(CarbonImmutable::parse('2026-09-29 04:00', 'America/Halifax'), true);
checkProjection($source->calls === [], 'Before any completed season games, use zero actual rates without querying future dates.');
checkProjection((float)DB::table('player_projections')->where('player_id','p5')->value('projected_fpts_per_game') === 0.5, 'Keep equal four-way weights before the first game.');

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
        $start = $this->mode === 'date' ? '2026-10-26' : $args['startDate'];
        $headers = [['key'=>'fpts'], ['shortName'=>$this->mode === 'gp' ? 'OTHER' : 'GP']];
        $data = ['displayedStatusOrTeam'=>'ALL','displayedPosOrGroup'=>$args['positionOrGroup'], 'displayedSelections'=>['datePlaying'=>'ALL','searchName'=>'', 'displayedSeasonOrProjection'=>['code'=>self::ACTUAL],
            'displayedStartDate'=>CarbonImmutable::parse($start, 'America/New_York')->getTimestampMs(),
            'displayedEndDate'=>CarbonImmutable::parse($args['endDate'], 'America/New_York')->getTimestampMs()],
            'paginatedResultSet'=>['totalNumPages'=>$this->pageCount,'pageNumber'=>1,'totalNumResults'=>$this->mode === 'partial' ? $this->pageCount + 1 : $this->pageCount],
            'tableHeader'=>['cells'=>$headers], 'statsTable'=>[['scorer'=>['scorerId'=>$goalie?'goalie':'skater'], 'cells'=>[['content'=>'-1'],['content'=>'2']]]]];
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
$parsed = $parser->actual('2026-10-27','2026-11-02');
checkProjection(count($parsed) === 2 && $parsed['goalie']['gp'] === 2, 'Collect skater and goalie GP separately.');
foreach (['date','gp','partial'] as $mode) {
    $parser->mode = $mode;
    rejectsProjection(fn()=>$parser->actual('2026-10-27','2026-11-02'), 'Reject '.$mode.' source corruption.');
}
$parser->mode = ''; $parser->pageCount = 10; $parser->batches = [];
checkProjection(count($parser->actual('2026-10-27','2026-11-02')) === 20, 'Parse every page across both groups.');
checkProjection(max(array_map('count', $parser->batches)) <= 3, 'Keep actual-stat collection batches within production memory limits.');
echo "Player projection checks passed.\n";
