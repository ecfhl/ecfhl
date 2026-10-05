<?php

namespace App\Support;

/** Shared ordering for Daily Targets and the Players logo toggle. */
final class DailyTargetsOrder
{
    public static function skaters(array $a, array $b): int
    {
        return (self::skaterPriority($a) <=> self::skaterPriority($b)) ?: self::projection($a, $b);
    }

    public static function skaterPriority(array $player): array
    {
        return [
            match ((int)($player['pp_unit'] ?? 0)) { 1 => 1, 2 => 2, default => 3 },
        ];
    }

    public static function goalies(array $a, array $b): int
    {
        return (self::goaliePriority($a) <=> self::goaliePriority($b)) ?: self::projection($a, $b);
    }

    public static function goaliePriority(array $player): int
    {
        return match (strtolower(trim($player['starting_status'] ?? ''))) {
            'starting', 'confirmed' => 1,
            'likely', 'probable' => 2,
            default => match ((int)($player['line_number'] ?? 0)) { 1 => 3, 2 => 4, default => 5 },
        };
    }

    private static function projection(array $a, array $b): int
    {
        return (($b['projected_points'] ?? -PHP_FLOAT_MAX) <=> ($a['projected_points'] ?? -PHP_FLOAT_MAX))
            ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
            ?: strcasecmp($a['name'] ?? '', $b['name'] ?? '');
    }
}
