<?php

namespace App\Support;

class AiTips
{
    public static function groups(array $snapshot, string $date): array
    {
        $groups = ['G' => [], 'F' => [], 'D' => []];
        if (($snapshot['date'] ?? null) !== $date) return $groups;
        foreach ($snapshot['players'] ?? [] as $player) {
            $position = $player['position'] ?? '';
            $status = trim($player['status'] ?? '');
            if (!isset($groups[$position]) || ($player['game_date'] ?? '') !== $date) continue;
            if (!preg_match('/^(FA|W(?:\s*\([^)]+\))?)$/', $status)) continue;
            if (empty($player['opponent']) || empty($player['team']) || empty($player['name'])) continue;
            if ($position === 'G' && empty($player['starting_status'])) continue;
            $groups[$position][] = $player;
        }
        foreach (['F' => 10, 'D' => 5] as $position => $limit) {
            usort($groups[$position], fn($a, $b) =>
                (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
                ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
                ?: strcasecmp($a['name'], $b['name']));
            $groups[$position] = array_slice($groups[$position], 0, $limit);
        }
        return $groups;
    }
}
