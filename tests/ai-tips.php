<?php

require __DIR__.'/../vendor/autoload.php';

use App\Support\AiTips;

$date = '2026-09-29';
$rows = [];
foreach (['F', 'D', 'G'] as $position) {
    for ($i = 1; $i <= 12; $i++) {
        $rows[] = ['name'=>"Player $position $i", 'team'=>'MTL', 'opponent'=>'@TOR',
            'position'=>$position, 'game_date'=>$date, 'status'=>'FA',
            'projected_points'=>$i, 'ir'=>true];
    }
}
$rows[] = array_replace($rows[0], ['name'=>'Rostered', 'status'=>'Orcas', 'projected_points'=>999]);
$rows[] = array_replace($rows[0], ['name'=>'Wrong day', 'game_date'=>'2026-09-30', 'projected_points'=>999]);
$rows[] = array_replace($rows[0], ['name'=>'No game', 'opponent'=>'', 'projected_points'=>999]);
$rows[] = array_replace($rows[0], ['name'=>'Waiver player', 'status'=>'W (Tue)', 'projected_points'=>50]);
$groups = AiTips::groups(['date'=>$date, 'players'=>$rows], $date);
function verifyTips(bool $condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
verifyTips(count($groups['F']) === 10 && count($groups['D']) === 5, 'Skater limits must apply.');
verifyTips(count($groups['G']) === 12, 'Goalies must not be capped.');
verifyTips($groups['F'][0]['name'] === 'Waiver player', 'Include waivers and rank by projected points.');
verifyTips($groups['D'][0]['projected_points'] === 12, 'Sort defensemen descending.');
verifyTips(!array_intersect(['Rostered','Wrong day','No game'], array_column($groups['F'], 'name')), 'Exclude unavailable players and other dates.');
verifyTips(AiTips::groups(['date'=>$date,'players'=>$rows], '2026-09-30') === ['G'=>[],'F'=>[],'D'=>[]], 'Never reuse another date.');

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$snapshot = json_decode(file_get_contents(__DIR__.'/../database/data/ai-tips/'.$date.'.json'), true, 512, JSON_THROW_ON_ERROR);
$groups = AiTips::groups($snapshot, $date);
$html = view('ai-tips', ['date'=>$date, 'today'=>'2026-09-28', 'tomorrow'=>$date,
    'selectedDate'=>Carbon\CarbonImmutable::parse($date), 'availableDates'=>[$date], 'snapshot'=>$snapshot, 'groups'=>$groups])->render();
verifyTips(str_contains($html, 'tips-ir') && str_contains($html, 'Frederik Andersen (EDM)'), 'Render injured reserve badges and other goalies.');
verifyTips(str_contains($html, 'Kevin Lankinen (VAN)') && str_contains($html, 'Top 10 available forwards'), 'Render populated page.');
$html = view('ai-tips', ['date'=>$date, 'today'=>'2026-09-28', 'tomorrow'=>$date,
    'selectedDate'=>Carbon\CarbonImmutable::parse($date), 'availableDates'=>[], 'snapshot'=>null, 'groups'=>[]])->render();
verifyTips(str_contains($html, 'No tips published'), 'Render missing-date page.');
echo "AI Tips checks passed.\n";
