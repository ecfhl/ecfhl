<?php
namespace App\Support\LiveScoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

final class NhlUpdates
{
    public static function events(string $date, array $snapshot = []): ?array
    {
        try {
            $events = Cache::remember('nhl-scoring-updates:v3:'.$date, 45, function () use ($date) {
                $data = Http::timeout(8)->get('https://api-web.nhle.com/v1/score/'.$date)->throw()->json();
                if (!is_array($data['games'] ?? null)) throw new \RuntimeException('Missing NHL games');
                return self::fromGames($data['games']);
            });
            return self::withFantasyTeams($events, $snapshot);
        } catch (\Throwable $error) {
            report($error);
            return null;
        }
    }
    public static function fromGames(array $games): array
    {
        $events = [];
        foreach ($games as $game) {
            $home = $game['homeTeam']['abbrev'] ?? '';
            $away = $game['awayTeam']['abbrev'] ?? '';
            $final = in_array($game['gameState'] ?? '', ['FINAL', 'OFF'], true);
            foreach ($game['goals'] ?? [] as $index => $goal) {
                $team = $goal['teamAbbrev'] ?? 'NHL';
                $isHome = $team === $home;
                $assists = array_map(fn($assist) => $assist['name']['default'] ?? 'Unknown', $goal['assists'] ?? []);
                $kind = match(strtolower($goal['strength'] ?? 'ev')) {
                    'pp', 'ppg' => 'Power play goal',
                    'sh', 'shg' => 'Short handed goal',
                    default => 'Goal',
                };
                $ownScore = $goal[$isHome ? 'homeScore' : 'awayScore'] ?? 0;
                $opponentScore = $game[$isHome ? 'awayTeam' : 'homeTeam']['score'] ?? 0;
                $teamScore = $game[$isHome ? 'homeTeam' : 'awayTeam']['score'] ?? 0;
                $winner = $final && $teamScore > $opponentScore && $ownScore === $opponentScore + 1;
                if ($winner) $kind .= ' · Game winning goal';
                $description = $assists ? $kind.' assisted by '.implode(' and ', $assists) : ($kind === 'Goal' ? 'Unassisted goal' : $kind.' · Unassisted goal');
                $events[] = [
                    'key'=>'nhl:'.$game['id'].':'.($goal['awayScore'] ?? '').':'.($goal['homeScore'] ?? '').':'.$index,
                    'team'=>$team.' '.($goal[$isHome ? 'homeScore' : 'awayScore'] ?? 0).($isHome ? ' vs ' : ' @ ').($isHome ? $away : $home).' '.($goal[$isHome ? 'awayScore' : 'homeScore'] ?? 0),
                    'game'=>['team'=>$team, 'score'=>$goal[$isHome ? 'homeScore' : 'awayScore'] ?? 0, 'opponent'=>$isHome ? $away : $home, 'opponentScore'=>$goal[$isHome ? 'awayScore' : 'homeScore'] ?? 0, 'home'=>$isHome, 'scoringTeam'=>$team],
                    'nhlTeam'=>$team,
                    'player'=>$goal['name']['default'] ?? 'Goal scored',
                    'stats'=>$description.' · '.($goal['timeInPeriod'] ?? '').' P'.($goal['periodDescriptor']['number'] ?? $goal['period'] ?? ''),
                    'points'=>0,
                ];
            }
        }
        return $events;
    }

    private static function withFantasyTeams(array $events, array $snapshot): array
    {
        foreach ($events as &$event) {
            $matches = [];
            foreach ($snapshot['players'] ?? [] as $player) {
                if (strtoupper($player['nhl_team'] ?? '') !== strtoupper($event['nhlTeam'])) continue;
                $name = \App\Support\PlayerName::display($player['player_name']);
                [$last, $first] = array_pad(array_map('trim', explode(',', $name, 2)), 2, '');
                $short = mb_substr($first, 0, 1).'. '.$last;
                if (strcasecmp($event['player'], $short) !== 0 && strcasecmp($event['player'], $first.' '.$last) !== 0) continue;
                $teamName = $snapshot['teams'][$player['fantasy_team_id']]['name'] ?? null;
                if ($teamName) $matches[$player['fantasy_team_id']] = $teamName;
            }
            // Avoid assigning a scorer when an abbreviated name has multiple roster matches.
            if (count($matches) === 1) $event['player'] .= ' — '.reset($matches);
        }
        unset($event);
        return $events;
    }

}
