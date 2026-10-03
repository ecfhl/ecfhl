<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AiTips
{
    public static function groups(array $snapshot, string $date): array
    {
        $groups = ['G' => [], 'F' => [], 'D' => []];
        $startedTeams = self::startedTeams($date);
        $daily = DB::table('active_daily_players')->whereDate('game_date', $date)->orderBy('source_rank')->get();
        $projections = new PlayerProjections;
        $daily = $projections->decorate($daily);
        foreach ($daily as $row) {
            $position = strtoupper(trim((string) $row->position));
            if (! in_array($position, ['F', 'D'], true)) continue;
            $status = self::availability($row);
            if ($status === null || ! self::availableByGameDate($row, $date) || trim((string) $row->opponent) === '' || ! $row->team || ! $row->player_name) continue;
            if ((bool)($row->game_started ?? false) || isset($startedTeams[strtoupper(trim((string)$row->team))]) || self::gameHasStarted($row, $date)) continue;
            $groups[$position][] = self::player($row, $position, $date, $status);
        }
        foreach (['F', 'D'] as $position) {
            usort($groups[$position], fn ($a, $b) => (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0)) ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX)) ?: strcasecmp($a['name'], $b['name']));
        }

        $normalize = static fn ($v) => preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim((string) $v))) ?? '';
        $dfoRows = DB::table('active_starting_goalies')->whereDate('game_date', $date)->get();
        $dfoByPlayer = [];
        $confirmedByTeam = [];
        foreach ($dfoRows as $dfo) {
            $team = strtoupper(trim((string) $dfo->team));
            $key = $team.'|'.$normalize($dfo->player_name);
            $dfoByPlayer[$key] = $dfo;
            if (strtolower(trim((string) $dfo->starting_status)) === 'confirmed') $confirmedByTeam[$team] = $normalize($dfo->player_name);
        }

        $goalies = DB::table('active_available_goalies')->whereDate('game_date', $date)->get();
        $goalies = $projections->decorate($goalies);
        foreach ($goalies as $row) {
            if (isset($startedTeams[strtoupper(trim((string)$row->team))]) || self::gameHasStarted($row, $date)) continue;
            $status = self::availability($row);
            if ($status === null || ! self::availableByGameDate($row, $date) || ! $row->team || ! $row->player_name) continue;
            $player = self::player($row, 'G', $date, $status);
            $team = strtoupper(trim((string) $row->team));
            $name = $normalize($row->player_name);
            $dfo = $dfoByPlayer[$team.'|'.$name] ?? null;
            $player['starting_status'] = $dfo?->starting_status;
            if ($dfo) {
                $player['opponent'] = ($dfo->home_away === 'AWAY' ? '@' : '').$dfo->opponent;
            }
            $player['not_starting'] = isset($confirmedByTeam[$team]) && $confirmedByTeam[$team] !== $name;
            if ($player['not_starting']) $player['starting_status'] = 'Not starting';
            $groups['G'][] = $player;
        }

        $priority = static function ($player): int {
            if (!empty($player['not_starting'])) return 4;
            return match (strtolower(trim((string) ($player['starting_status'] ?? '')))) {
                'confirmed' => 0, 'probable' => 1, 'unconfirmed' => 2, default => 3,
            };
        };
        usort($groups['G'], fn ($a, $b) => ($priority($a) <=> $priority($b)) ?: (($b['projected_points'] ?? 0) <=> ($a['projected_points'] ?? 0)) ?: (($a['source_rank'] ?? PHP_INT_MAX) <=> ($b['source_rank'] ?? PHP_INT_MAX)) ?: strcasecmp($a['name'], $b['name']));
        return $groups;
    }

    private static function startedTeams(string $date): array
    {
        if ($date !== (new FantasyDay)->today()->toDateString()) return [];

        $teams = [];
        $rows = DB::table('active_fantasy_rosters')
            ->whereDate('game_date', $date)
            ->whereNotNull('game_time')
            ->get(['nhl_team','game_time']);

        foreach ($rows as $row) {
            $team = strtoupper(trim((string)$row->nhl_team));
            $gameTime = trim((string)$row->game_time);
            if ($team === '' || $gameTime === '') continue;
            if (!preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $gameTime, $m)) continue;

            try {
                $starts = \Carbon\CarbonImmutable::createFromFormat(
                    '!Y-m-d g:i A',
                    $date.' '.strtoupper(preg_replace('/\s+/', ' ', trim($m[1]))),
                    'America/Halifax'
                );
                if ($starts && $starts->lte(now('America/Halifax'))) $teams[$team] = true;
            } catch (\Throwable) {
            }
        }

        return $teams;
    }

    private static function gameHasStarted(object $row, string $date): bool
    {
        if ($date !== (new FantasyDay)->today()->toDateString()) return false;
        $gameTime = trim((string)($row->game_time ?? ''));
        if ($gameTime === '') return false;
        if (!preg_match('/(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $gameTime, $m)) return false;
        try {
            $starts = \Carbon\CarbonImmutable::createFromFormat(
                '!Y-m-d g:i A',
                $date.' '.strtoupper(preg_replace('/\s+/', ' ', trim($m[1]))),
                'America/Halifax'
            );
            return $starts ? $starts->lte(now('America/Halifax')) : false;
        } catch (\Throwable) {
            return false;
        }
    }

    private static function availableByGameDate(object $row, string $date): bool
    {
        $availability = strtoupper(trim((string)($row->availability ?? '')));
        if ($availability !== 'W') return true;

        $waiverDay = trim((string)($row->waiver_day ?? ''));
        if ($waiverDay === '') return true;

        // Fantrax exposes waiver availability as W (Sat), W (Sun), etc. A player
        // cannot be used for a Daily Targets date before that waiver day.
        $target = \Carbon\CarbonImmutable::parse($date, 'America/Halifax');
        $targetDow = strtolower($target->format('D'));
        $waiverDow = strtolower(substr($waiverDay, 0, 3));
        $days = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
        if (!isset($days[$waiverDow])) return true;

        // Waiver labels are weekday-only, so resolve the next occurrence of that
        // weekday from the current fantasy day. This avoids treating W (Sun) as
        // already cleared just because Sunday has a smaller numeric weekday value.
        $today=(new FantasyDay)->today();
        $daysUntil=($days[$waiverDow]-(int)$today->format('w')+7)%7;
        $clearDate=$today->addDays($daysUntil)->toDateString();

        return $date >= $clearDate;
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
        return ['player_id'=>$row->player_id??null,'name'=>$row->player_name,'team'=>$row->team,'position'=>$position,'opponent'=>$opponent,'game_time'=>$row->game_time??null,'status'=>$status,'injury_status'=>$row->injury_status,'projected_points'=>property_exists($row, 'projected_fpts_per_game') ? $row->projected_fpts_per_game : ($row->projected_fpts===null?null:(float)$row->projected_fpts),'source_rank'=>(int)($row->source_rank??PHP_INT_MAX),'game_date'=>$date,'starting_status'=>null,'not_starting'=>false];
    }
}
