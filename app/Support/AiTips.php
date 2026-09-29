<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AiTips
{
    public static function groups(array $snapshot, string $date): array
    {
        $groups = ['G' => [], 'F' => [], 'D' => []];

        $daily = DB::table('active_daily_players')
            ->whereDate('game_date', $date)
            ->orderBy('source_rank')
            ->get();

        $goalieRows = DB::table('active_starting_goalies')
            ->whereDate('game_date', $date)
            ->get();

        $normalizeName = static function (?string $name): string {
            $name = mb_strtolower(trim((string) $name));
            $name = preg_replace('/[^\pL\pN]+/u', '', $name) ?? $name;
            return $name;
        };

        $confirmedTeams = [];
        foreach ($goalieRows as $row) {
            if (strtolower(trim((string) $row->starting_status)) === 'confirmed') {
                $confirmedTeams[strtoupper(trim((string) $row->team))] = $normalizeName($row->player_name);
            }
        }

        $fantraxGoalies = [];
        foreach ($daily as $row) {
            if (strtoupper(trim((string) $row->position)) !== 'G') {
                continue;
            }
            $team = strtoupper(trim((string) $row->team));
            $name = $normalizeName($row->player_name);
            if ($team !== '' && $name !== '') {
                $fantraxGoalies[$team.'|'.$name] = $row;
            }
        }

        foreach ($daily as $row) {
            $position = strtoupper(trim((string) $row->position));
            if (! in_array($position, ['F', 'D'], true)) {
                continue;
            }

            $status = self::availability($row);
            if ($status === null) {
                continue;
            }

            $opponent = trim((string) $row->opponent);
            if ($opponent === '' || ! $row->team || ! $row->player_name) {
                continue;
            }

            $groups[$position][] = self::playerFromFantrax($row, $position, $date, $status);
        }

        foreach (['F', 'D'] as $position) {
            usort($groups[$position], fn ($a, $b) =>
                (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
                ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
                ?: strcasecmp($a['name'], $b['name'])
            );
        }

        // Daily Faceoff drives the goalie list. A DFO goalie is shown only when the
        // matching Fantrax row confirms that goalie is available in ECFHL.
        $dailyFaceoff = [];
        $usedFantrax = [];
        foreach ($goalieRows as $dfo) {
            $team = strtoupper(trim((string) $dfo->team));
            $name = $normalizeName($dfo->player_name);
            $key = $team.'|'.$name;
            $fantrax = $fantraxGoalies[$key] ?? null;
            if (! $fantrax) {
                continue;
            }

            $status = self::availability($fantrax);
            if ($status === null) {
                continue;
            }

            if (isset($confirmedTeams[$team]) && $confirmedTeams[$team] !== $name) {
                continue;
            }

            $player = self::playerFromFantrax($fantrax, 'G', $date, $status);
            $player['starting_status'] = $dfo->starting_status;
            // DFO owns the matchup/home-away data for its goalie rows.
            if (! empty($dfo->opponent)) {
                $player['opponent'] = strtoupper((string) $dfo->home_away) === 'AWAY'
                    ? '@'.trim((string) $dfo->opponent)
                    : trim((string) $dfo->opponent);
            }
            $dailyFaceoff[] = $player;
            $usedFantrax[$key] = true;
        }

        // Fill the remainder with available Fantrax goalies playing that day.
        $fantrax = [];
        foreach ($fantraxGoalies as $key => $row) {
            if (isset($usedFantrax[$key])) {
                continue;
            }
            $status = self::availability($row);
            if ($status === null || trim((string) $row->opponent) === '') {
                continue;
            }
            $team = strtoupper(trim((string) $row->team));
            $name = $normalizeName($row->player_name);
            if (isset($confirmedTeams[$team]) && $confirmedTeams[$team] !== $name) {
                continue;
            }
            $fantrax[] = self::playerFromFantrax($row, 'G', $date, $status);
        }

        $priority = fn ($p) => match (strtolower(trim($p['starting_status'] ?? ''))) {
            'confirmed' => 0,
            'probable' => 1,
            'unconfirmed' => 2,
            default => 3,
        };
        usort($dailyFaceoff, fn ($a, $b) =>
            ($priority($a) <=> $priority($b))
            ?: (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
            ?: strcasecmp($a['name'], $b['name'])
        );
        usort($fantrax, fn ($a, $b) =>
            (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0))
            ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX))
            ?: strcasecmp($a['name'], $b['name'])
        );

        $groups['G'] = array_values(array_merge($dailyFaceoff, $fantrax));
        return $groups;
    }

    private static function availability(object $row): ?string
    {
        $status = strtoupper(trim((string) $row->availability));
        if ($status === 'W') {
            $status = 'W'.($row->waiver_day ? ' ('.$row->waiver_day.')' : '');
        }
        return preg_match('/^(FA|W(?:\s*\([^)]+\))?)$/', $status) ? $status : null;
    }

    private static function playerFromFantrax(object $row, string $position, string $date, string $status): array
    {
        $opponent = trim((string) $row->opponent);
        $opponent = strtoupper((string) $row->home_away) === 'AWAY' ? '@'.$opponent : $opponent;

        return [
            'name' => $row->player_name,
            'team' => $row->team,
            'position' => $position,
            'opponent' => $opponent,
            'status' => $status,
            'injury_status' => $row->injury_status,
            'projected_points' => $row->projected_fpts === null ? null : (float) $row->projected_fpts,
            'source_rank' => (int) $row->source_rank,
            'game_date' => $date,
            'starting_status' => null,
        ];
    }
}
