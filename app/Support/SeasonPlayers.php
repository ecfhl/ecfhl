<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SeasonPlayers
{
    public function data(Request $request): array
    {
        $positions = array_values(array_intersect(['F', 'D', 'G'], explode(',', (string)$request->query('positions', 'F,D'))));
        $rookies = $request->query('rookies') === '1';
        $search = mb_substr(trim((string)$request->query('q', '')), 0, 100);
        // Use only the latest roster snapshot; an old ownership record must not
        // make a released player appear to belong to their former team.
        $roster = DB::table('active_fantasy_rosters')->select('player_id')->selectRaw('MAX(id) as roster_id')
            ->where('game_date', fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))->groupBy('player_id');
        $query = DB::table('season_player_stats as s')
            ->leftJoin('player_projections as p', 'p.player_id', '=', 's.player_id')
            ->leftJoinSub($roster, 'latest_roster', 'latest_roster.player_id', '=', 's.player_id')
            ->leftJoin('active_fantasy_rosters as r', 'r.id', '=', 'latest_roster.roster_id')
            ->whereIn('s.position', $positions)
            ->select('s.*', 'p.projected_fpts_per_game', 'r.fantasy_team_name');
        if ($rookies) $query->where('s.rookie', true);
        if ($search !== '') $query->where('s.player_name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        $players = $query->orderByDesc('s.season_fpts')->orderBy('s.player_name')->orderBy('s.player_id')->paginate(25, ['*'], 'page', max(1, (int)$request->query('page', 1)))->appends([
            'positions'=>implode(',', $positions), 'rookies'=>$rookies ? '1' : '0', 'q'=>$search,
        ]);
        $players->getCollection()->transform(function ($row) { $row->stats = json_decode($row->stats_json, true) ?: []; return $row; });
        $groups = PublicData::remember('season-player-columns', 60, fn()=>DB::table('season_player_stat_columns')->pluck('columns_json', 'group')->all());
        $columns = [];
        if (array_intersect(['F', 'D'], $positions)) $columns = json_decode($groups['skater'] ?? '{}', true) ?: [];
        if (in_array('G', $positions, true)) $columns = array_merge($columns, json_decode($groups['goalie'] ?? '{}', true) ?: []);
        unset($columns['GP']);
        return compact('players', 'positions', 'rookies', 'search', 'columns') + ['statsThrough'=>DB::table('season_player_stats')->max('stats_through')];
    }
}
