<?php
namespace App\Support;
class AwardIcon
{
    public static function for(string $id): string
    {
        return match($id) {
            'champion'=>'🏆', 'second'=>'🥈', 'third'=>'🥉', 'president'=>'🏅', 'leader'=>'⭐',
            'art_ross'=>'🏒', 'norris'=>'🛡️', 'vezina'=>'🥅', 'calder'=>'🌟', 'top_pick'=>'🎯', default=>'🏅'
        };
    }
}
