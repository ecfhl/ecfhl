<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GoalieStatusChanges
{
    /** Include backups omitted from DFO so confirming a teammate produces a status change. */
    public static function snapshot(string $day, Collection $reported): array
    {
        $goalies = [];
        foreach (['active_available_goalies'=>'team', 'active_daily_players'=>'team', 'active_fantasy_rosters'=>'nhl_team'] as $table=>$teamColumn) {
            $query = DB::table($table)->where('game_date', $day);
            if ($table !== 'active_available_goalies') $query->where('position', 'G');
            foreach ($query->get() as $row) {
                $team = strtoupper(trim((string)$row->$teamColumn));
                if ($team === '' || trim((string)$row->player_name) === '') continue;
                $key = OwnerNotificationPolicy::goalieKey($team, $row->player_name);
                $goalies[$key] ??= (object)['team'=>$team, 'player_name'=>$row->player_name,
                    'starting_status'=>DailyFaceoffStartingGoalies::normalizeStatus($row->starting_status ?? null)];
            }
        }
        $confirmed = [];
        foreach ($reported as $row) {
            $key = OwnerNotificationPolicy::goalieKey($row->team, $row->player_name);
            $goalie = clone $row;
            $goalie->starting_status = DailyFaceoffStartingGoalies::normalizeStatus($row->starting_status);
            $goalies[$key] = $goalie;
            if ($goalie->starting_status === 'Confirmed') $confirmed[strtoupper(trim($row->team))] = $key;
        }
        foreach ($goalies as $key=>$goalie) {
            $starter = $confirmed[strtoupper(trim($goalie->team))] ?? null;
            if ($starter !== null && $starter !== $key) $goalie->starting_status = 'Not starting';
        }
        return $goalies;
    }
}
