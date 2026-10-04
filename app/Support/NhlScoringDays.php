<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class NhlScoringDays
{
    public function latestCompletedDate(): CarbonImmutable
    {
        $today = (new FantasyDay)->today();
        // Check yesterday as well: a game can still be running after midnight.
        for ($offset = 0; $offset < 7; $offset++) {
            $date = $today->subDays($offset);
            $response = Http::timeout(12)->retry(1, 500)
                ->get('https://api-web.nhle.com/v1/score/'.$date->toDateString());
            $response->throw();
            $data = $response->json();
            if (!is_array($data) || ($data['currentDate'] ?? null) !== $date->toDateString()
                || !is_array($data['games'] ?? null)) {
                throw new RuntimeException('NHL returned no dated game-completion data. Existing standings preserved.');
            }
            $games = $data['games'];
            // An empty current-day schedule is not evidence that a day has ended.
            if (!$games && $offset === 0) continue;
            $finished = true;
            foreach ($games as $game) {
                if (!in_array($game['gameState'] ?? null, ['FINAL', 'OFF'], true)) {
                    $finished = false;
                    break;
                }
            }
            if ($finished) return $date;
        }
        throw new RuntimeException('No completed NHL scoring day found. Existing standings preserved.');
    }
}
