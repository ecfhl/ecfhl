<?php

namespace App\Support\LiveScoring;

final class PlayerStatLine
{
    public static function text(object $player, bool $gameStarted): string
    {
        if ($gameStarted && ($player->today_gp ?? 0) == 0) return 'Not Playing';
        $labels = strtoupper((string)$player->position)==='G'
            ? ['w'=>'win','ol'=>'OTL','so'=>'SO','g'=>'goal','a'=>'assist']
            : ['g'=>'goal','a'=>'assist','ppg'=>'PPG','shg'=>'SHG','gwg'=>'GWG'];
        $parts=[];
        foreach ($labels as $field=>$label) {
            $value=(int)($player->{'today_'.$field} ?? 0);
            if ($value===0) continue;
            if ($field==='gwg') { $parts[]='GWG'; continue; }
            $parts[]=$value.' '.$label.(in_array($label,['goal','assist','win']) && $value!==1 ? 's' : '');
        }
        return implode(' - ', $parts);
    }
}
