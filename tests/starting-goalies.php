<?php
/** Isolated SQLite regressions; never connects to or modifies production. */
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x', 32)));
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $e) { fwrite(STDERR, $e->getMessage()."\n"); exit(1); });

use App\Support\DailyFaceoffStartingGoalies;
use App\Support\AiTips;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

config(['database.connections.sqlite' => ['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
foreach (glob(__DIR__.'/../database/migrations/*create_active*table.php') as $file) {
    (require $file)->up();
}
function goalieCheck($condition, $message) {
    if (! $condition) throw new RuntimeException($message);
}
function goalieHtml(array $payload): string {
    return '<html><body><script type="application/json" id="__NEXT_DATA__">'.json_encode($payload).'</script></body></html>';
}
$fixtures = [];
foreach (['2026-09-28','2026-09-29','2026-09-30'] as $day) {
    $fixtures[$day] = json_decode(file_get_contents(__DIR__.'/fixtures/dfo-'.$day.'.json'), true, 512, JSON_THROW_ON_ERROR);
}
$collector = new DailyFaceoffStartingGoalies;
$rows = $collector->parse(goalieHtml($fixtures['2026-09-29']), '2026-09-29');
goalieCheck(count($rows) === 10, 'Expected five complete matchups');
$byName = array_column($rows, null, 'player_name');
goalieCheck($byName['Tristan Jarry']['starting_status'] === 'Confirmed' && $byName['Tristan Jarry']['team_name'] === 'Edmonton Oilers' && $byName['Tristan Jarry']['home_away'] === 'HOME' && $byName['Tristan Jarry']['opponent_name'] === 'Vancouver Canucks', 'Jarry must match the verified DFO page');
goalieCheck($byName['Kevin Lankinen']['starting_status'] === 'Unconfirmed' && $byName['Kevin Lankinen']['home_away'] === 'AWAY' && $byName['Kevin Lankinen']['opponent_name'] === 'Edmonton Oilers', 'Lankinen must match the verified DFO page');
goalieCheck($collector->parse(goalieHtml($fixtures['2026-09-28']), '2026-09-28') === [], 'Explicit empty date is valid');
$probable = $fixtures['2026-09-29'];
$probable['props']['pageProps']['data'][0]['awayNewsStrengthName'] = 'Probable';
goalieCheck($collector->parse(goalieHtml($probable), '2026-09-29')[0]['starting_status'] === 'Probable', 'Preserve Probable status');
$invalid = ['<html>app shell</html>', '<script id="__NEXT_DATA__">{bad json</script>', goalieHtml($fixtures['2026-09-30'])];
foreach (['missing-data','missing-goalie','missing-status','unknown-status','unknown-team','wrong-game-date'] as $case) {
    $payload = $fixtures['2026-09-29'];
    switch ($case) {
        case 'missing-data': unset($payload['props']['pageProps']['data']); break;
        case 'missing-goalie': $payload['props']['pageProps']['data'][0]['awayGoalieName'] = null; break;
        case 'missing-status': unset($payload['props']['pageProps']['data'][0]['awayNewsStrengthName']); break;
        case 'unknown-status': $payload['props']['pageProps']['data'][0]['awayNewsStrengthName'] = 'Unexpected'; break;
        case 'unknown-team': $payload['props']['pageProps']['data'][0]['awayTeamName'] = 'Unknown'; break;
        case 'wrong-game-date': $payload['props']['pageProps']['data'][0]['date'] = '2026-09-30'; break;
    }
    $invalid[] = goalieHtml($payload);
}
foreach ($invalid as $html) {
    $thrown = false;
    try { $collector->parse($html, '2026-09-29'); } catch (Throwable) { $thrown = true; }
    goalieCheck($thrown, 'Malformed/incomplete payload must fail');
}
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-09-29 12:00:00', 'America/Halifax'));
Http::preventStrayRequests();
Http::swap(new \Illuminate\Http\Client\Factory);
Http::preventStrayRequests();
Http::fake(fn ($request) => Http::response(goalieHtml($fixtures[basename($request->url())]), 200));
goalieCheck(Artisan::call('ecfhl:refresh-starting-goalies') === 0, 'Both dates must refresh successfully');
goalieCheck(DB::table('active_starting_goalies')->whereDate('game_date','2026-09-29')->count() === 10, 'Today database count');
goalieCheck(DB::table('active_starting_goalies')->whereDate('game_date','2026-09-30')->count() === 6, 'Tomorrow database count');
$before = DB::table('active_starting_goalies')->orderBy('id')->get()->toJson();
Http::swap(new \Illuminate\Http\Client\Factory);
Http::preventStrayRequests();
Http::fake(fn () => Http::response('<html>app shell</html>', 200));
goalieCheck(Artisan::call('ecfhl:refresh-starting-goalies') !== 0, 'Parse failure must return nonzero');
goalieCheck(DB::table('active_starting_goalies')->orderBy('id')->get()->toJson() === $before, 'Parse failure must preserve records');
Http::swap(new \Illuminate\Http\Client\Factory);
Http::preventStrayRequests();
Http::fake(fn () => Http::response('Unavailable', 503));
goalieCheck(Artisan::call('ecfhl:refresh-starting-goalies') !== 0, 'HTTP failure must return nonzero');
goalieCheck(DB::table('active_starting_goalies')->orderBy('id')->get()->toJson() === $before, 'HTTP failure must preserve records');
Http::swap(new \Illuminate\Http\Client\Factory);
Http::preventStrayRequests();
Http::fake(fn ($request) => Http::response(goalieHtml($fixtures[basename($request->url())]), 200));
DB::statement("CREATE TRIGGER reject_goalie BEFORE INSERT ON active_starting_goalies BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
goalieCheck(Artisan::call('ecfhl:refresh-starting-goalies') !== 0, 'Insert failure must return nonzero');
goalieCheck(DB::table('active_starting_goalies')->orderBy('id')->get()->toJson() === $before, 'Insert failure must roll back deletion');
DB::statement('DROP TRIGGER reject_goalie');

// Synthetic Fantrax availability checks, independent of who is owned in the live league.
foreach ([['Tristan Jarry','EDM'],['Kevin Lankinen','VAN'],['Test backup','EDM']] as [$name,$team]) {
    DB::table('active_available_goalies')->insert(['game_date'=>'2026-09-29','player_name'=>$name,'team'=>$team,'availability'=>'W','waiver_day'=>'Tue','injury_status'=>'IR','projected_fpts'=>100,'starting_status'=>'Probable','created_at'=>now(),'updated_at'=>now()]);
}
$groups = AiTips::groups([], '2026-09-29');
$joined = array_column($groups['G'], null, 'name');
goalieCheck(count($joined) === 3, 'Keep the backup visible');
goalieCheck($joined['Tristan Jarry']['starting_status'] === 'Confirmed' && $joined['Tristan Jarry']['opponent'] === 'VAN', 'Join confirmed home goalie');
goalieCheck($joined['Kevin Lankinen']['starting_status'] === 'Unconfirmed' && $joined['Kevin Lankinen']['opponent'] === '@EDM', 'Join unconfirmed away goalie');
goalieCheck($joined['Test backup']['not_starting'] && $joined['Test backup']['starting_status'] === 'Not starting', 'Disable confirmed starter teammate');
goalieCheck($joined['Tristan Jarry']['status'] === 'W (Tue)' && $joined['Tristan Jarry']['injury_status'] === 'IR', 'Preserve waiver and injury information');
$html = view('ai-tips', ['date'=>'2026-09-29','today'=>'2026-09-29','tomorrow'=>'2026-09-30','selectedDate'=>CarbonImmutable::parse('2026-09-29'),'groups'=>$groups])->render();
$dom = new DOMDocument;
@$dom->loadHTML($html);
$xpath = new DOMXPath($dom);
goalieCheck($xpath->query('//tr[contains(@class,"tips-not-starting")]')->length === 1, 'Greyed backup row rendered');
goalieCheck($xpath->query('//tr[contains(@class,"tips-not-starting")]//a')->length === 0, 'Disabled backup has no actionable link');
goalieCheck($xpath->query('//tr[contains(@class,"tips-not-starting")]//*[@aria-disabled="true"]')->length === 1, 'Disabled Add semantics');
CarbonImmutable::setTestNow();
echo "Starting-goalie parser, safe replacement, exit-code and AI Tips join checks passed.\n";
