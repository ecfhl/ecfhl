<?php

// Run only against an isolated in-memory database.
putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';

function verifyTips(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); });

use App\Support\AiTips;
use Illuminate\Support\Facades\DB;

config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
foreach (glob(__DIR__.'/../database/migrations/*create_active*table.php') as $file) (require $file)->up();
$date = '2026-09-29';

DB::table('active_daily_players')->delete();
DB::table('active_starting_goalies')->delete();

$now = now();
$rows = [];
foreach (['F', 'D', 'G'] as $position) {
    for ($i = 1; $i <= 12; $i++) {
        $rows[] = [
            'game_date' => $date,
            'player_name' => "Player $position $i",
            'team' => 'MTL',
            'position' => $position,
            'opponent' => 'TOR',
            'home_away' => 'AWAY',
            'availability' => 'FA',
            'waiver_day' => null,
            'injury_status' => null,
            'projected_fpts' => $i,
            'source_rank' => $i,
            'fantrax_url' => null,
            'last_update' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ];
    }
}
$rows[] = array_replace($rows[0], ['player_name'=>'Waiver player', 'availability'=>'W', 'waiver_day'=>'Tue', 'projected_fpts'=>50, 'source_rank'=>99]);
$rows[] = array_replace($rows[0], ['player_name'=>'Rostered', 'availability'=>'Orcas', 'projected_fpts'=>999, 'source_rank'=>100]);
$rows[] = array_replace($rows[0], ['player_name'=>'Wrong day', 'game_date'=>'2026-09-30', 'opponent'=>'', 'projected_fpts'=>999, 'source_rank'=>101]);
$rows[] = array_replace($rows[0], ['player_name'=>'No game', 'opponent'=>'', 'projected_fpts'=>999, 'source_rank'=>102]);
DB::table('active_daily_players')->insert($rows);
foreach ($rows as $row) {
    if ($row['position'] !== 'G') continue;
    unset($row['position']);
    DB::table('active_available_goalies')->insert($row);
}

DB::table('active_starting_goalies')->insert([
    'game_date'=>$date,
    'player_name'=>'Player G 12',
    'team'=>'MTL',
    'opponent'=>'TOR',
    'home_away'=>'AWAY',
    'starting_status'=>'Confirmed',
    'source_updated_at'=>$now,
    'checked_at'=>$now,
    'source_url'=>'https://www.dailyfaceoff.com/starting-goalies/'.$date,
    'created_at'=>$now,
    'updated_at'=>$now,
]);

$groups = AiTips::groups([], $date);
verifyTips(count($groups['F']) === 13, 'All available forwards should be returned for incremental display.');
verifyTips(count($groups['D']) === 12, 'All available defensemen should be returned for incremental display.');
verifyTips(count($groups['G']) === 12, 'Confirmed goalie teammates must remain visible.');
verifyTips(count(array_filter($groups['G'], fn($g)=>$g['not_starting'])) === 11, 'Confirmed goalie teammates must be disabled.');
verifyTips($groups['F'][0]['name'] === 'Waiver player', 'Include waivers and rank by projected points.');
verifyTips($groups['D'][0]['projected_points'] === 12.0, 'Sort defensemen descending.');
verifyTips(! array_intersect(['Rostered','Wrong day','No game'], array_column($groups['F'], 'name')), 'Exclude unavailable players, other dates and players without games.');
verifyTips(AiTips::groups([], '2026-09-30')['F'] === [], 'Do not reuse another date.');

$html = view('ai-tips', [
    'date'=>$date,
    'today'=>'2026-09-28',
    'tomorrow'=>$date,
    'selectedDate'=>Carbon\CarbonImmutable::parse($date),
    'availableDates'=>[$date],
    'snapshot'=>['date'=>$date],
    'groups'=>$groups,
])->render();
verifyTips(str_contains($html, 'Top 10 available forwards') && str_contains($html, 'Top 5 available defensemen') && str_contains($html, 'Top 10 available goalies'), 'Render database-backed AI Tips sections.');

echo "AI Tips checks passed.\n";
