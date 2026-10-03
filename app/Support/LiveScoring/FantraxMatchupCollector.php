<?php

namespace App\Support\LiveScoring;

use RuntimeException;

final class FantraxMatchupCollector
{
    public function collect(array $day, array $period, array $players): array
    {
        $dayPairs = $day['matchups'] ?? [];
        $periodPairs = $period['matchups'] ?? [];
        sort($dayPairs);
        sort($periodPairs);
        if (($day['displayedSelections']['period'] ?? null) !== ($period['displayedSelections']['period'] ?? null)
            || $dayPairs !== $periodPairs) throw new RuntimeException('Inconsistent Fantrax matchup period.');
        $teams = [];
        foreach (($day['fantasyTeams'] ?? []) as $fantasyTeam) {
            if (!empty($fantasyTeam['pseudo'])) continue;
            $id = (string)$fantasyTeam['id'];
            $daily = $day['statsPerTeam']['allTeamsStats'][$id]['ACTIVE'] ?? null;
            $weekly = $period['statsPerTeam']['allTeamsStats'][$id]['ACTIVE'] ?? null;
            if (!is_array($daily) || !is_array($weekly) || !is_numeric($daily['totalFpts'] ?? null) || !is_numeric($weekly['totalFpts'] ?? null)) throw new RuntimeException('Incomplete fantasy team scores.');
            $active = array_filter($players, fn($p)=>$p['fantasy_team_id'] === $id && $p['scoring_status'] === 'ACTIVE');
            $sum = array_sum(array_column($active, 'daily_fpts'));
            if (abs($sum - (float)$daily['totalFpts']) > 0.02) throw new RuntimeException('Fantrax daily team/player totals disagree for '.$fantasyTeam['name']);
            $teams[$id] = [
                'id'=>$id, 'name'=>$fantasyTeam['name'], 'daily_fpts'=>(float)$daily['totalFpts'] + (float)($daily['pointsAdjustment'] ?? 0),
                'daily_points_adjustment'=>(float)($daily['pointsAdjustment'] ?? 0),
                'period_fpts'=>(float)$weekly['totalFpts'] + (float)($weekly['pointsAdjustment'] ?? 0),
                'daily_projected_fpts'=>round(array_sum(array_column($active, 'daily_projection_calculated')), 2),
            ];
        }
        if (!$teams || !is_array($day['matchups'] ?? null)) throw new RuntimeException('Missing Fantrax matchups.');
        $matchups = [];
        foreach ($day['matchups'] as $pair) {
            $ids = explode('_', $pair);
            if (count($ids) !== 2 || !isset($teams[$ids[0]], $teams[$ids[1]])) throw new RuntimeException('Unresolved Fantrax matchup.');
            $matchups[] = ['id'=>$pair, 'away_team_id'=>$ids[0], 'home_team_id'=>$ids[1]];
        }
        return ['teams'=>$teams, 'matchups'=>$matchups, 'period'=>(string)$day['displayedSelections']['period'], 'period_label'=>$day['displayPeriod'] ?? 'Scoring period '.$day['displayedSelections']['period']];
    }
}
