<?php

namespace App\Support\LiveScoring;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Presentation badges and stored custom per-game projections. Fantrax membership,
// fantasy dates, opponents, actual points and game state stay intact.
final class PlayerDisplayEnrichment
{
    public function decorate($players, string $date)
    {
        $players = (new \App\Support\PlayerProjections)->decorate($players);
        $name = static function ($v) {
            $v = trim((string)$v);
            if (str_contains($v, ',')) { [$last,$first] = array_map('trim',explode(',',$v,2)); $v = $first.' '.$last; }
            return preg_replace('/[^\pL\pN]+/u','',mb_strtolower($v));
        };
        $team = static fn($v)=>match(strtoupper(trim((string)$v))) { 'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>strtoupper(trim((string)$v)) };
        $rows = static function ($table, $dated = false) use ($date) {
            if (!Schema::hasTable($table)) return collect();
            $query = DB::table($table);
            return ($dated ? $query->whereDate('game_date',$date) : $query)->get();
        };
        $pp = $rows('active_pp_lines')->keyBy(fn($r)=>$team($r->team).'|'.$name($r->player_name));
        $lines = $rows('active_line_combinations')->keyBy(fn($r)=>$team($r->team).'|'.$name($r->player_name).'|'.$r->position_group);
        $odds = $rows('todays_odds',true)->keyBy(fn($r)=>$team($r->team));
        $goalies = $rows('active_starting_goalies',true)->keyBy(fn($r)=>$team($r->team).'|'.$name($r->player_name));
        return $players->map(function ($p) use ($pp,$lines,$odds,$goalies,$team,$name) {
            $t = $team($p->nhl_team);
            $key = $t.'|'.$name($p->player_name);
            $p->pp_unit = isset($pp[$key]) ? (int)$pp[$key]->pp_unit : null;
            $p->line_number = isset($lines[$key.'|'.$p->position]) ? (int)$lines[$key.'|'.$p->position]->line_number : null;
            if ($p->position === 'G') {
                $status = ucfirst(strtolower(trim((string)($goalies[$key]->starting_status ?? ''))));
                if ($status === 'Probable') $status = 'Likely';
                $p->starting_status = $status ?: null;
                $p->starting_status_class = match(strtolower($status)) {
                    'confirmed'=>'goalie-status-confirmed','likely'=>'goalie-status-likely',
                    'unconfirmed'=>'goalie-status-unconfirmed','not starting'=>'goalie-status-not-starting',default=>'goalie-status-na',
                };
                if (isset($odds[$t]) && $odds[$t]->american_odds !== null) {
                    $p->vegas_odds = (int)$odds[$t]->american_odds;
                    $p->vegas_odds_class = $p->vegas_odds <= -130 ? 'vegas-odds-good' : ($p->vegas_odds >= 130 ? 'vegas-odds-bad' : 'vegas-odds-even');
                }
            }
            return $p;
        });
    }
}
