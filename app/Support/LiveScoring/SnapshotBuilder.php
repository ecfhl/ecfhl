<?php

namespace App\Support\LiveScoring;

use App\Support\FantasyDay;
use RuntimeException;

final class SnapshotBuilder
{
    public function build(string $date, array $day, array $period, array $details): array
    {
        (new FantasyDay)->parse($date);
        foreach ([[$day,'1'],[$period,'2']] as [$source,$view]) {
            if (($source['date'] ?? null) !== $date || (string)($source['displayedSelections']['viewTypeId'] ?? '') !== $view) throw new RuntimeException('Snapshot source date/timeframe mismatch.');
        }
        $roster = (new FantraxRosterCollector)->collect($day);
        $players = (new FantraxDailyScoringCollector)->collect($day, $roster);
        $players = (new FantraxDailyProjectionCollector)->collect($day, $players);
        $metadata = (new FantraxDailyDetailsCollector)->collect($details, $date);
        foreach ($players as &$player) {
            $extra = $metadata[$player['player_id']] ?? [];
            if (!array_key_exists('gp', $extra)) throw new RuntimeException('Missing dated Fantrax GP for '.$player['player_id']);
            // Supplement GP/contract by Fantrax ID; never replace membership or scoring categories.
            $player['gp'] = $extra['gp'] ?? null;
            $player['contract'] = $extra['contract'] ?? null;
            $player['goalie_stats'] = $extra['goalie_stats'] ?? [];
        }
        unset($player);
        $matchups = (new FantraxMatchupCollector)->collect($day, $period, $players);
        return array_merge($matchups, ['fantasy_date'=>$date, 'source_date'=>$date, 'source'=>'fantrax', 'players'=>$players]);
    }
}
