<?php
namespace App\Support\LiveScoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class NhlUpdates
{
    public static function events(string $date): ?array
    {
        try {
            return Cache::remember('nhl-scoring-updates:'.$date, 45, function () use ($date) {
                $data = Http::timeout(8)->get('https://api-web.nhle.com/v1/score/'.$date)->throw()->json();
                if (!is_array($data['games'] ?? null)) throw new \RuntimeException('Missing NHL games');
                $events = [];
                foreach ($data['games'] as $game) {
                    foreach ($game['goals'] ?? [] as $index => $goal) {
                        $assists = array_map(fn($assist) => ($assist['name']['default'] ?? 'Unknown').' assist', $goal['assists'] ?? []);
                        $events[] = [
                            'key'=>'nhl:'.$game['id'].':'.($goal['awayScore'] ?? '').':'.($goal['homeScore'] ?? '').':'.$index,
                            'team'=>$goal['teamAbbrev'] ?? 'NHL',
                            'player'=>$goal['name']['default'] ?? 'Goal scored',
                            'stats'=>implode(' · ', array_merge(['Goal · '.($goal['strength'] ?? 'EV'), ($goal['timeInPeriod'] ?? '').' P'.($goal['periodDescriptor']['number'] ?? $goal['period'] ?? '')], $assists)),
                            'points'=>0,
                        ];
                    }
                }
                return $events;
            });
        } catch (\Throwable $error) {
            report($error);
            return null;
        }
    }
}
