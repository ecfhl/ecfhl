<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class SeasonPlayers
{
    private const SKATER_COLUMNS = ['G'=>'Goals', 'A'=>'Assists', 'Pts'=>'Points', 'PPG'=>'Power-play goals',
        'SHG'=>'Short-handed goals', 'GWG'=>'Game-winning goals', 'SOG'=>'Shots on goal', 'TOI'=>'Time on ice'];
    private const BASE_HEADERS = ['player'=>'Player', 'team'=>'Team', 'ec_proj'=>'EC Proj', 'fpts'=>'FPts', 'fpts_gp'=>'FPts/gp', 'gp'=>'GP'];

    public function data(Request $request): array
    {
        $positions = array_values(array_intersect(['F', 'D', 'G'], explode(',', (string)$request->query('positions', 'F,D'))));
        $rookies = $request->query('rookies') === '1';
        $search = mb_substr(trim((string)$request->query('q', '')), 0, 100);
        $availability = (string)$request->query('availability', 'all');
        if (!in_array($availability, ['all', 'available', 'taken'], true)) $availability = 'all';
        $teamOptions = DB::table('active_fantasy_rosters')->where('game_date', fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))
            ->select('fantasy_team_id')->selectRaw('MAX(fantasy_team_name) as fantasy_team_name')->groupBy('fantasy_team_id')->orderBy('fantasy_team_name')->get();
        $selectedTeam = (string)$request->query('team', '');
        if ($selectedTeam !== '' && !$teamOptions->contains('fantasy_team_id', $selectedTeam)) $selectedTeam = '';
        $selectedLine = (string)$request->query('line', '');
        if (!in_array($selectedLine, ['', '1', '2', '3', '4', 'none'], true)) $selectedLine = '';
        $selectedPp = (string)$request->query('pp', '');
        if (!in_array($selectedPp, ['', '1', '2', 'none'], true)) $selectedPp = '';
        $lines = PublicData::remember('badges:active_line_combinations', 30, fn()=>DB::table('active_line_combinations')->orderBy('checked_at')->orderBy('id')->get())
            ->keyBy(fn($r)=>$this->assignmentKey($r->team, $r->player_name).'|'.strtoupper(trim($r->position_group)));
        $pp = PublicData::remember('badges:active_pp_lines', 30, fn()=>DB::table('active_pp_lines')->orderBy('checked_at')->orderBy('id')->get())
            ->keyBy(fn($r)=>$this->assignmentKey($r->team, $r->player_name));
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
            ->select('s.*', 'p.projected_fpts_per_game', 'r.fantasy_team_id', 'r.fantasy_team_name');
        if ($selectedTeam !== '') $query->where('r.fantasy_team_id', $selectedTeam);
        if ($availability === 'available') $query->whereNull('r.id');
        if ($availability === 'taken') $query->whereNotNull('r.id');
        if ($rookies) $query->where('s.rookie', true);
        if ($selectedLine !== '' || $selectedPp !== '') {
            // Resolve the collector's team/name assignments to Fantrax IDs first,
            // then filter in SQL before sorting and pagination.
            $ids = DB::table('season_player_stats')->whereIn('position', ['F', 'D'])->get(['player_id', 'player_name', 'nhl_team', 'position'])
                ->filter(function ($row) use ($lines, $pp, $selectedLine, $selectedPp) {
                    $key = $this->assignmentKey($row->nhl_team, $row->player_name);
                    $line = $lines[$key.'|'.$row->position]->line_number ?? null;
                    $unit = $pp[$key]->pp_unit ?? null;
                    return ($selectedLine === '' || ($selectedLine === 'none' ? $line === null : (int)$line === (int)$selectedLine))
                        && ($selectedPp === '' || ($selectedPp === 'none' ? $unit === null : (int)$unit === (int)$selectedPp));
                })->pluck('player_id')->all();
            $query->whereIn('s.player_id', $ids);
        }
        if ($search !== '') $query->where('s.player_name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        [$expression, $bindings] = $this->sortExpression($sort);
        // Sort in SQL before pagination, with unavailable values last in either
        // direction. Player ID resolves ties so Show More has a stable order.
        $players = $query->selectRaw($expression.' as season_sort_value', $bindings)
            ->orderByRaw('season_sort_value IS NULL')->orderBy('season_sort_value', $direction)
            ->orderBy('s.player_name')->orderBy('s.player_id')
            ->paginate(25, ['*'], 'page', max(1, (int)$request->query('page', 1)))->appends([
                'positions'=>implode(',', $positions), 'rookies'=>$rookies ? '1' : '0', 'q'=>$search, 'sort'=>$sort, 'direction'=>$direction, 'team'=>$selectedTeam, 'availability'=>$availability, 'line'=>$selectedLine, 'pp'=>$selectedPp,
            ]);
        $players->getCollection()->transform(function ($row) use ($lines, $pp) {
            $row->stats = json_decode($row->stats_json, true) ?: [];
            $row->stats['Pts'] = $row->stats['Pt'] ?? $row->stats['Pts'] ?? '';
            $key = $this->assignmentKey($row->nhl_team, $row->player_name);
            $row->line_number = $row->position === 'G' ? null : ($lines[$key.'|'.$row->position]->line_number ?? null);
            $row->pp_unit = $row->position === 'G' ? null : ($pp[$key]->pp_unit ?? null);
            return $row;
        });
        return compact('players', 'positions', 'rookies', 'search', 'columns', 'headers', 'sort', 'direction', 'teamOptions', 'selectedTeam', 'availability', 'selectedLine', 'selectedPp')
            + ['statsThrough'=>DB::table('season_player_stats')->max('stats_through')];
    }

    private function assignmentKey(?string $team, string $name): string
    {
        $team = strtoupper(trim((string)$team));
        $team = match($team) { 'LA'=>'LAK', 'NJ'=>'NJD', 'SJ'=>'SJS', 'TB'=>'TBL', default=>$team };
        if (str_contains($name, ',')) { [$last, $first] = array_map('trim', explode(',', $name, 2)); $name = $first.' '.$last; }
        return $team.'|'.preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($name)));
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
