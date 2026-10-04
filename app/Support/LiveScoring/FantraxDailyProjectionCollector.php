<?php

namespace App\Support\LiveScoring;

final class FantraxDailyProjectionCollector
{
    public function collect(array $day, array $players): array
    {
        foreach ($players as &$player) {
            $team = $day['statsPerTeam']['allTeamsStats'][$player['fantasy_team_id']][$player['scoring_status']];
            $id = $player['player_id'];
            $original = $team['projectedTotalsMap'][$id] ?? null;
            $original = is_numeric($original) ? $original : null;
            $calculated = $team['calculatedProjectedTotalsMap'][$id] ?? $original;
            $calculated = is_numeric($calculated) ? $calculated : $original;
            // Fantrax can publish a valid lineup and calculated estimate before
            // its original estimate exists. These optional source estimates must
            // not block actual scoring; the UI uses our stored EC Proj rates.
            // Keep unavailable estimates null rather than inventing zero points.
            $finished = !empty($day['allEventsFinished']) || ($team['remainingEventPercent'][$id] ?? null) === 0.0 || ($team['remainingEventPercent'][$id] ?? null) === 0;
            $player['daily_projection_original'] = $original;
            $player['daily_projection_calculated'] = $calculated;
            // This is the same rule Fantrax uses for each player's displayed daily projection.
            $player['daily_projected_fpts'] = $finished ? $original : $calculated;
        }
        unset($player);
        return $players;
    }
}
