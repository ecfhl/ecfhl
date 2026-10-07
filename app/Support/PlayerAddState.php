<?php

namespace App\Support;

final class PlayerAddState
{
    public static function forPlayer(object $player): string
    {
        if ($player->position === 'G') {
            $statuses = array_values(array_filter([
                $player->today_goalie_status ?? null,
                $player->tomorrow_goalie_status ?? null,
            ]));
            if (in_array('Confirmed', $statuses, true)) return 'green';
            if (in_array('Likely', $statuses, true)) return 'yellow';
            if ($statuses && !array_diff($statuses, ['Not starting'])) return 'grey';
            return 'blue';
        }

        if (!empty($player->injury_status)) return 'red';
        if (empty($player->line_number)) return 'orange';
        $score = $player->projected_fpts_per_game ?? null;
        if ((int)($player->pp_unit ?? 0) === 1 || ($score !== null && $score >= 1.0)) return 'green';
        if ((int)($player->pp_unit ?? 0) === 2 || ($score !== null && $score > 0.75)) return 'yellow';
        return 'blue';
    }
}
