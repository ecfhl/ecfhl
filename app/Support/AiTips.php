<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AiTips
{
    public static function groups(array $snapshot, string $date): array
    {
        $groups = ['G' => [], 'F' => [], 'D' => []];

        $daily = DB::table('active_daily_players')->whereDate('game_date', $date)->orderBy('source_rank')->get();
        foreach ($daily as $row) {
            $position = strtoupper(trim((string) $row->position));
            if (! in_array($position, ['F', 'D'], true)) continue;
            $status = self::availability($row);
            if ($status === null || trim((string) $row->opponent) === '' || ! $row->team || ! $row->player_name) continue;
            $groups[$position][] = self::player($row, $position, $date, $status);
        }

        foreach (['F', 'D'] as $position) {
            usort($groups[$position], fn ($a, $b) =>
                (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
                ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
                ?: strcasecmp($a['name'], $b['name'])
            );
        }

        // Always join the latest Daily Faceoff table at page render time. This avoids
        // waiting for the separate available-goalie collector to copy a newer DFO status.
        $normalize = static fn ($v) => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim((string) $v))) ?? '';
        $dfoRows = DB::table('active_starting_goalies')->whereDate('game_date', $date)->get();
        $dfoByPlayer = [];
        $confirmedByTeam = [];
        foreach ($dfoRows as $dfo) {
            $team = strtoupper(trim((string) $dfo->team));
            $key = $team.'|'.$normalize($dfo->player_name);
            $dfoByPlayer[$key] = $dfo;
            if (strtolower(trim((string) $dfo->starting_status)) === 'confirmed') {
                $confirmedByTeam[$team] = $normalize($dfo->player_name);
            }
        }

        $goalies = DB::table('active_available_goalies')->whereDate('game_date', $date)->get();
        foreach ($goalies as $row) {
            $status = self::availability($row);
            if ($status === null || ! $row->team || ! $row->player_name) continue;
            $player = self::player($row, 'G', $date, $status);
            $team = strtoupper(trim((string) $row->team));
            $name = $normalize($row->player_name);
            $dfo = $dfoByPlayer[$team.'|'.$name] ?? null;
            $player['starting_status'] = $dfo?->starting_status ?? $row->starting_status;
            $player['not_starting'] = isset($confirmedByTeam[$team]) && $confirmedByTeam[$team] !== $name;
            $groups['G'][] = $player;
        }

        $priority = static function ($player): int {
            if (!empty($player['not_starting'])) return 4;
            return match (strtolower(trim((string) ($player['starting_status'] ?? '')))) {
                'confirmed' => 0,
                'probable' => 1,
                'unconfirmed' => 2,
                '', 'na', 'n/a' => 3,
                default => 3,
            };
        };

        usort($groups['G'], fn ($a, $b) =>
            ($priority($a) <=> $priority($b))
            ?: (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
            ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
            ?: strcasecmp($a['name'], $b['name'])
        );

        return $groups;
    }

    private static function availability(object $row): ?string
    {
        $status = strtoupper(trim((string) $row->availability));
        if ($status === 'W') $status = 'W'.($row->waiver_day ? ' ('.$row->waiver_day.')' : '');
        return preg_match('/^(FA|W(?:\s*\([^)]+\))?)$/', $status) ? $status : null;
    }

    private static function player(object $row, string $position, string $date, string $status): array
    {
        $opponent = trim((string) ($row->opponent ?? ''));
        $opponent = strtoupper((string) ($row->home_away ?? '')) === 'AWAY' && $opponent !== '' ? '@'.$opponent : $opponent;
        return [
            'name' => $row->player_name,
            'team' => $row->team,
            'position' => $position,
            'opponent' => $opponent,
            'status' => $status,
            'injury_status' => $row->injury_status,
            'projected_points' => $row->projected_fpts === null ? null : (float) $row->projected_fpts,
            'source_rank' => (int) ($row->source_rank ?? PHP_INT_MAX),
            'game_date' => $date,
            'starting_status' => null,
            'not_starting' => false,
        ];
    }
}
