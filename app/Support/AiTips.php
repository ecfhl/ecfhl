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
            $groups[$position][] = $player;
        }

        foreach (['F' => 10, 'D' => 5] as $position => $limit) {
            usort($groups[$position], fn($a, $b) =>
                (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
                ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
                ?: strcasecmp($a['name'], $b['name']));
            $groups[$position] = array_slice($groups[$position], 0, $limit);
        }

        // Build exactly the top 10 goalie recommendations when enough are available:
        // all available goalies listed by Daily Faceoff first, then fill the
        // remaining spots with the best available Fantrax goalies playing that day.
        $dailyFaceoff = array_values(array_filter($groups['G'], fn($p) => !empty($p['starting_status'])));
        $fantrax = array_values(array_filter($groups['G'], fn($p) => empty($p['starting_status']) && array_key_exists('projected_points', $p)));

        usort($dailyFaceoff, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($fantrax, fn($a, $b) =>
            (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
            ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
            ?: strcasecmp($a['name'], $b['name']));

        $goalies = [];
        $seen = [];
        foreach (array_merge($dailyFaceoff, $fantrax) as $player) {
            $key = strtolower(trim($player['name'])).'|'.strtolower(trim($player['team']));
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $goalies[] = $player;
            if (count($goalies) >= 10) break;
        }
        $groups['G'] = $goalies;

        return $groups;
    }
}
