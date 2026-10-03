<?php

namespace App\Support\LiveScoring;

use Carbon\CarbonImmutable;
use RuntimeException;

final class FantraxDailyScoringCollector
{
    public function collect(array $day, array $roster): array
    {
        $categories = [];
        foreach (($day['scoringCategoriesPerGroup'] ?? []) as $group) foreach ($group as $stat) $categories[$stat['id']] = $stat['shortName'];
        $rows = [];
        foreach ($roster as $player) {
            $team = $day['statsPerTeam']['allTeamsStats'][$player['fantasy_team_id']][$player['scoring_status']] ?? null;
            if (!is_array($team)) throw new RuntimeException('Missing daily scoring for a Fantrax lineup.');
            $id = $player['player_id'];
            $stat = $team['statsMap'][$id] ?? null;
            $stats = [];
            foreach (($stat['object2'] ?? []) as $cell) {
                $name = $categories[$cell['scipId']] ?? $cell['scipId'];
                $stats[$name] = ['value'=>$cell['av'] ?? $cell['sv'], 'display'=>$cell['sv'], 'fpts'=>$cell['fpts'] ?? null];
            }
            $game = $this->game((string)($team['gameStatusMap'][$id] ?? ''), (string)$player['nhl_team']);
            if ($game['game_id'] === '') throw new RuntimeException('Fantrax daily player has no dated event identity.');
            $rows[] = array_merge($player, $game, ['daily_fpts'=>(float)($stat['object1'] ?? 0), 'stats'=>$stats]);
        }
        return $rows;
    }

    private function game(string $value, string $team): array
    {
        [$text,$id,$state] = array_pad(explode('|', $value), 3, '');
        $startsAt = null;
        $opponent = null;
        $homeAway = null;
        if (str_contains($text, '~')) {
            [$opponent,$millis] = explode('~', $text, 2);
            $homeAway = str_starts_with($opponent, '@') ? 'AWAY' : 'HOME';
            $opponent = ltrim($opponent, '@');
            if (!ctype_digit($millis)) throw new RuntimeException('Invalid Fantrax game start.');
            $startsAt = CarbonImmutable::createFromTimestampUTC((int)$millis / 1000)->toIso8601String();
            $text = ($homeAway === 'AWAY' ? '@' : 'vs ').$opponent.' · '.CarbonImmutable::parse($startsAt)->setTimezone('America/Halifax')->format('g:i a T');
        } elseif (preg_match('/^([A-Z]{2,4})\s+\d+\s+@([A-Z]{2,4})\s+\d+/', $text, $m)) {
            $homeAway = $team === $m[1] ? 'AWAY' : 'HOME';
            $opponent = $homeAway === 'AWAY' ? $m[2] : $m[1];
        }
        return ['game_id'=>$id, 'game_status'=>$state, 'game_display'=>$text, 'starts_at'=>$startsAt, 'opponent'=>$opponent, 'home_away'=>$homeAway];
    }
}
