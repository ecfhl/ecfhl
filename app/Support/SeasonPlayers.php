<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SeasonPlayers
{
    private const SKATER_COLUMNS = ['A'=>'Assists', 'G'=>'Goals', 'Pts'=>'Points', 'PPG'=>'Power-play goals',
        'SHG'=>'Short-handed goals', 'GWG'=>'Game-winning goals', 'SOG'=>'Shots on goal', 'TOI'=>'Time on ice'];
    private const BASE_HEADERS = ['player'=>'Player', 'team'=>'Team', 'gp'=>'GP', 'fpts'=>'FPts', 'fpts_gp'=>'FPts/gp', 'ec_proj'=>'EC Proj'];

    public function data(Request $request): array
    {
        $positions = array_values(array_intersect(['F', 'D', 'G'], explode(',', (string)$request->query('positions', 'F,D'))));
        $rookies = $request->query('rookies') === '1';
        $search = mb_substr(trim((string)$request->query('q', '')), 0, 100);
        $groups = PublicData::remember('season-player-columns', 60, fn()=>DB::table('season_player_stat_columns')->pluck('columns_json', 'group')->all());
        $columns = array_intersect(['F', 'D'], $positions) || !$positions ? self::SKATER_COLUMNS : [];
        if (in_array('G', $positions, true)) {
            $goalieColumns = json_decode($groups['goalie'] ?? '{}', true) ?: [];
            foreach (['Min','W','L','OL','GAA','SV%','SHO','GA','SOGA','SV'] as $label) if (isset($goalieColumns[$label])) $columns[$label] = $goalieColumns[$label];
        }
        $headers = self::BASE_HEADERS + array_combine(array_keys($columns), array_keys($columns));
        $sort = (string)$request->query('sort', 'fpts');
        if (!isset($headers[$sort])) $sort = 'fpts';
        $direction = $request->query('direction', in_array($sort, ['player','team'], true) ? 'asc' : 'desc') === 'asc' ? 'asc' : 'desc';
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
        [$expression, $bindings] = $this->sortExpression($sort);
        // Sort in SQL before pagination, with unavailable values last in either
        // direction. Player ID resolves ties so Show More has a stable order.
        $players = $query->selectRaw($expression.' as season_sort_value', $bindings)
            ->orderByRaw('season_sort_value IS NULL')->orderBy('season_sort_value', $direction)
            ->orderBy('s.player_name')->orderBy('s.player_id')
            ->paginate(25, ['*'], 'page', max(1, (int)$request->query('page', 1)))->appends([
                'positions'=>implode(',', $positions), 'rookies'=>$rookies ? '1' : '0', 'q'=>$search, 'sort'=>$sort, 'direction'=>$direction,
            ]);
        $players->getCollection()->transform(function ($row) {
            $row->stats = json_decode($row->stats_json, true) ?: [];
            $row->stats['Pts'] = $row->stats['Pt'] ?? $row->stats['Pts'] ?? '';
            return $row;
        });
        return compact('players', 'positions', 'rookies', 'search', 'columns', 'headers', 'sort', 'direction')
            + ['statsThrough'=>DB::table('season_player_stats')->max('stats_through')];
    }

    private function sortExpression(string $sort): array
    {
        $fields = ['player'=>'LOWER(s.player_name)', 'team'=>"LOWER(COALESCE(r.fantasy_team_name, 'Free Agent'))",
            'gp'=>'s.season_gp', 'fpts'=>'s.season_fpts', 'fpts_gp'=>'s.season_fpts_per_game', 'ec_proj'=>'p.projected_fpts_per_game'];
        if (isset($fields[$sort])) return [$fields[$sort], []];
        $extract = DB::connection()->getDriverName() === 'sqlite' ? 'json_extract(s.stats_json, ?)' : 'JSON_UNQUOTE(JSON_EXTRACT(s.stats_json, ?))';
        $paths = ['$."'.$sort.'"'];
        // Fantrax calls points "Pt"; the page labels it "Pts".
        if ($sort === 'Pts') { $extract = 'COALESCE('.$extract.', '.$extract.')'; $paths = ['$."Pt"', '$."Pts"']; }
        $value = "NULLIF(NULLIF(NULLIF(REPLACE($extract, ',', ''), ''), '-'), '—')";
        $expression = "CAST($value AS DECIMAL(18,6))";
        if ($sort === 'TOI') {
            // TOI is minutes:seconds, not a decimal or lexicographic string.
            $expression = "CASE WHEN INSTR($value, ':') > 0 THEN CAST(SUBSTR($value, 1, INSTR($value, ':') - 1) AS DECIMAL(18,6)) * 60 + CAST(SUBSTR($value, INSTR($value, ':') + 1) AS DECIMAL(18,6)) ELSE CAST($value AS DECIMAL(18,6)) * 60 END";
        }
        return [$expression, array_merge(...array_fill(0, intdiv(substr_count($expression, '?'), count($paths)), $paths))];
    }
}
