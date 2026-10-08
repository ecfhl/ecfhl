<?php
namespace App\Support;
final class CollectorCadence
{
    public const LABELS = [
        'players'=>'15 min active; 3 hours outside active hours', 'teams'=>'15 min active; 3 hours outside active hours',
        'goalies'=>'5 min within 6 hours of puck drop; 3 hours otherwise',
        'lines'=>'Every 3 hours', 'odds'=>'Every 4 hours while today has upcoming games',
        'scores'=>'Every minute during NHL games; hourly idle; final reconciliation',
        'standings'=>'Every 5 min during NHL games; final reconciliation',
        'advisor'=>'Every 3 hours; every 30 min within 6 hours of puck drop',
        'projections'=>'Daily at 4:00 a.m. Atlantic', 'birthdates'=>'Monday at 3:50 a.m. Atlantic', 'matchups'=>'Monday at 8:00 a.m. Atlantic',
    ];
}
