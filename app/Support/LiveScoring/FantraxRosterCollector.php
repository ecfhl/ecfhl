<?php

namespace App\Support\LiveScoring;

use RuntimeException;

final class FantraxRosterCollector
{
    public function collect(array $day): array
    {
        if (!isset($day['scorerMap']) || !is_array($day['scorerMap'])) throw new RuntimeException('Missing Fantrax daily lineup.');
        $rows = [];
        foreach (['ACTIVE','BENCH'] as $status) {
            foreach (($day['scorerMap'][$status] ?? []) as $teamId=>$groups) {
                foreach ($groups as $groupId=>$players) {
                    foreach ($players as $entry) {
                        $scorer = $entry['scorer'] ?? [];
                        $id = (string)($scorer['scorerId'] ?? '');
                        if ($id === '' || empty($scorer['name'])) throw new RuntimeException('Unidentified Fantrax daily lineup player.');
                        $key = $teamId.'|'.$id;
                        if (isset($rows[$key])) throw new RuntimeException('Duplicate Fantrax daily lineup player.');
                        $icons = array_column($scorer['icons'] ?? [], 'typeId');
                        $rows[$key] = [
                            'fantasy_team_id'=>(string)$teamId, 'player_id'=>$id, 'player_name'=>$scorer['name'],
                            'nhl_team'=>$scorer['teamShortName'] ?? null, 'position'=>$scorer['posShortNames'] ?? null,
                            'roster_slot'=>(string)($entry['posId'] ?? ''), 'scoring_status'=>$status,
                            // Injury/minors badges never override Fantrax's ACTIVE scoring status.
                            'injury_status'=>in_array('2', $icons, true), 'minor_badge'=>in_array('4', $icons, true),
                            'source'=>'fantrax', 'source_date'=>$day['date'], 'fantasy_date'=>$day['date'],
                        ];
                    }
                }
            }
        }
        return array_values($rows);
    }
}
