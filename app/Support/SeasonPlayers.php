<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

final class SeasonPlayers
{
    private const SKATER_COLUMNS = ['G'=>'Goals', 'A'=>'Assists', 'Pts'=>'Points', 'PPG'=>'Power-play goals',
        'SHG'=>'Short-handed goals', 'GWG'=>'Game-winning goals', 'SOG'=>'Shots on goal', 'TOI'=>'Time on ice'];
    private const BASE_HEADERS = ['player'=>'Player', 'team'=>'Team', 'ec_proj'=>'ECFHL', 'fpts'=>'FPts', 'fpts_gp'=>'FPts/gp', 'today'=>'Today', 'tomorrow'=>'Tomorrow', 'gp'=>'GP'];
    public const DATASETS = ['season'=>'Season', '7d'=>'7 days', '14d'=>'14 days', '21d'=>'21 days', 'fantrax'=>'Fantrax proj'];
    private const TEAM_ALIASES = ['LA'=>'LAK', 'NJ'=>'NJD', 'SJ'=>'SJS', 'TB'=>'TBL'];

    public function data(Request $request): array
    {
        $ownedTeamId = $request->user()?->claim?->fantasy_team_id;
        $dataset = (string)$request->query('dataset', 'season');
        if (!isset(self::DATASETS[$dataset])) $dataset = 'season';
        $datasetLabel = self::DATASETS[$dataset];
        $datasetFields = match($dataset) {
            '7d', '14d', '21d'=>['fpts'=>'p.fpts_'.$dataset, 'gp'=>'p.gp_'.$dataset, 'fpts_gp'=>'p.fpts_per_game_'.$dataset],
            'fantrax'=>['fpts'=>'b.fantrax_season_fpts', 'gp'=>'NULL', 'fpts_gp'=>'b.fantrax_fpts_per_game'],
            default=>['fpts'=>'s.season_fpts', 'gp'=>'s.season_gp', 'fpts_gp'=>'s.season_fpts_per_game'],
        };
        $positions = array_values(array_intersect(['F', 'D', 'G'], explode(',', (string)$request->query('positions', 'F,D'))));
        $rookies = $request->query('rookies') === '1';
        $search = mb_substr(trim((string)$request->query('q', '')), 0, 100);
        $availability = (string)$request->query('availability', 'available');
        if (!in_array($availability, ['all', 'available', 'taken'], true)) $availability = 'available';
        $playing = (string)$request->query('playing', 'all');
        if (!in_array($playing, ['all', 'today', 'tomorrow', 'both'], true)) $playing = 'all';
        $playingDate = in_array($playing, ['all','both'], true) ? null : app(FantasyDay::class)->today()->addDays($playing === 'tomorrow' ? 1 : 0)->toDateString();
        $teamOptions = DB::table('active_fantasy_rosters')->where('game_date', fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))
            ->select('fantasy_team_id')->selectRaw('MAX(fantasy_team_name) as fantasy_team_name')->groupBy('fantasy_team_id')->orderBy('fantasy_team_name')->get();
        $selectedTeam = (string)$request->query('team', '');
        if ($selectedTeam !== '' && !$teamOptions->contains('fantasy_team_id', $selectedTeam)) $selectedTeam = '';
        $lineChoices=['1','2','3','4','none'];
        $ppChoices=['1','2','none'];
        $selectedLines=$this->selections($request->query('line'),$lineChoices);
        $selectedPps=$this->selections($request->query('pp'),$ppChoices);
        $selectedLine=$selectedLines ? implode(',',$selectedLines) : 'empty';
        $selectedPp=$selectedPps ? implode(',',$selectedPps) : 'empty';
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
        // Extra category stats are stored only for Season. Never mix season
        // goals/assists with a selected recent window or frozen projection.
        if ($dataset !== 'season') $columns = [];
        $headers = ['rank'=>'Rank'] + self::BASE_HEADERS + array_combine(array_keys($columns), array_keys($columns));
        if ($dataset === 'fantrax') unset($headers['gp']);
        $sort = (string)$request->query('sort', $request->query('dfo_sort') === '1' ? 'ec_proj' : 'fpts');
        if (in_array($sort,['rank','today','tomorrow'],true) || !isset($headers[$sort])) $sort = 'fpts';
        $direction = $request->query('direction', in_array($sort, ['player','team'], true) ? 'asc' : 'desc') === 'asc' ? 'asc' : 'desc';
        $dailyTargetsSort = $request->query('dfo_sort') === '1';
        // Use only the latest roster snapshot; an old ownership record must not
        // make a released player appear to belong to their former team.
        $roster = DB::table('active_fantasy_rosters')->select('player_id')->selectRaw('MAX(id) as roster_id')
            ->where('game_date', fn($q)=>$q->from('active_fantasy_rosters')->selectRaw('MAX(game_date)'))->groupBy('player_id');
        $query = DB::table('season_player_stats as s')
            ->leftJoin('player_projections as p', 'p.player_id', '=', 's.player_id')
            ->leftJoin('player_projection_baselines as b', 'b.player_id', '=', 's.player_id')
            ->leftJoinSub($roster, 'latest_roster', 'latest_roster.player_id', '=', 's.player_id')
            ->leftJoin('active_fantasy_rosters as r', 'r.id', '=', 'latest_roster.roster_id')
            ->whereIn('s.position', $positions)
            ->select('s.*', 'p.projected_fpts_per_game', 'r.fantasy_team_id', 'r.fantasy_team_name')
            ->selectRaw($datasetFields['fpts'].' as dataset_fpts, '.$datasetFields['gp'].' as dataset_gp, '.$datasetFields['fpts_gp'].' as dataset_fpts_per_game');
        if ($dataset === 'fantrax') $query->whereNotNull('b.player_id');
        elseif ($dataset !== 'season') $query->whereNotNull($datasetFields['fpts_gp']);
        if ($selectedTeam !== '') $query->where('r.fantasy_team_id', $selectedTeam);
        if ($availability === 'available') $query->whereNull('r.id');
        if ($availability === 'taken') $query->whereNotNull('r.id');
        if ($playing !== 'all') {
            $dates = $playing === 'both' ? [app(FantasyDay::class)->today()->toDateString(), app(FantasyDay::class)->today()->addDay()->toDateString()] : [$playingDate];
            $teams = [];
            foreach ($dates as $date) $teams = array_merge($teams, $this->playingTeams($date));
            $query->whereIn(DB::raw('UPPER(TRIM(s.nhl_team))'), array_unique($teams));
        }
        if ($rookies) $query->where('s.rookie', true);
        if (count($selectedLines)!==count($lineChoices) || count($selectedPps)!==count($ppChoices)) {
            // Apply the selected union within each group, and intersect Line with PP.
            $ids = DB::table('season_player_stats')->whereIn('position', ['F', 'D'])->get(['player_id', 'player_name', 'nhl_team', 'position'])
                ->filter(function ($row) use ($lines, $pp, $selectedLines, $selectedPps) {
                    $key = $this->assignmentKey($row->nhl_team, $row->player_name);
                    $line = $lines[$key.'|'.$row->position]->line_number ?? null;
                    $unit = $pp[$key]->pp_unit ?? null;
                    return in_array($line===null?'none':(string)$line,$selectedLines,true)
                        && in_array($unit===null?'none':(string)$unit,$selectedPps,true);
                })->pluck('player_id')->all();
            $query->whereIn('s.player_id', $ids);
        }
        if ($search !== '') $query->where('s.player_name', 'like', '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%');
        $page = max(1, (int)$request->query('page', 1));
        if ($dailyTargetsSort) {
            $players = $this->dailyTargetsPage($query, $lines, $pp, $playingDate ?? app(FantasyDay::class)->today()->toDateString(), $page, $sort, $direction, $datasetFields);
        } else {
            [$expression, $bindings] = $this->sortExpression($sort, $datasetFields);
            // Sort in SQL before pagination, with unavailable values last in either
            // direction. Player ID resolves ties so Show More has a stable order.
            $players = $query->selectRaw($expression.' as season_sort_value', $bindings)
                ->orderByRaw('season_sort_value IS NULL')->orderBy('season_sort_value', $direction)
                ->orderBy('s.player_name')->orderBy('s.player_id')
                ->paginate(25, ['*'], 'page', $page);
        }
        $players->appends([
            'positions'=>implode(',', $positions), 'rookies'=>$rookies ? '1' : '0', 'q'=>$search, 'sort'=>$sort, 'direction'=>$direction, 'team'=>$selectedTeam, 'availability'=>$availability, 'line'=>$selectedLine, 'pp'=>$selectedPp, 'dataset'=>$dataset, 'playing'=>$playing, 'dfo_sort'=>$dailyTargetsSort ? '1' : '0',
        ]);
        $fantasyToday=app(FantasyDay::class)->today();
        $games=app(PlayerGames::class);
        $todayGames=$games->forDate($fantasyToday->toDateString());
        $tomorrowGames=$games->forDate($fantasyToday->addDay()->toDateString());
        $goalieStatuses = [];
        foreach (['today'=>$fantasyToday->toDateString(), 'tomorrow'=>$fantasyToday->addDay()->toDateString()] as $day=>$date) {
            $starters = DB::table('active_starting_goalies')->where('game_date', $date)->orderBy('checked_at')->orderBy('id')->get(['team', 'player_name', 'starting_status']);
            $confirmed = [];
            foreach ($starters as $starter) {
                if (in_array(strtolower(trim((string)$starter->starting_status)), ['starting','confirmed'], true)) $confirmed[$this->teamCode($starter->team)] = $this->assignmentKey($starter->team, $starter->player_name);
            }
            $goalieStatuses[$day] = ['players'=>$starters->keyBy(fn($r)=>$this->assignmentKey($r->team, $r->player_name)), 'confirmed'=>$confirmed];
        }
        $players->getCollection()->transform(function ($row) use ($lines, $pp, $todayGames, $tomorrowGames, $goalieStatuses) {
            $row->today_game=$todayGames[PlayerGames::team($row->nhl_team)]??null;
            $row->tomorrow_game=$tomorrowGames[PlayerGames::team($row->nhl_team)]??null;
            foreach (['today','tomorrow'] as $day) {
                $row->{$day.'_goalie_status'} = null;
                if ($row->position !== 'G' || !$row->{$day.'_game'}) continue;
                $key = $this->assignmentKey($row->nhl_team, $row->player_name);
                $status = strtolower(trim((string)($goalieStatuses[$day]['players'][$key]->starting_status ?? '')));
                $confirmed = $goalieStatuses[$day]['confirmed'][$this->teamCode($row->nhl_team)] ?? null;
                $row->{$day.'_goalie_status'} = $confirmed && $confirmed !== $key ? 'Not starting' : match($status) {
                    'confirmed', 'starting'=>'Confirmed', 'likely', 'probable'=>'Likely',
                    'not starting', 'not_starting'=>'Not starting', default=>'Unconfirmed',
                };
            }
            $row->stats = json_decode($row->stats_json, true) ?: [];
            $row->stats['Pts'] = $row->stats['Pt'] ?? $row->stats['Pts'] ?? '';
            $key = $this->assignmentKey($row->nhl_team, $row->player_name);
            $row->line_number = $row->position === 'G' ? null : ($lines[$key.'|'.$row->position]->line_number ?? null);
            $row->pp_unit = $row->position === 'G' ? null : ($pp[$key]->pp_unit ?? null);
            return $row;
        });
        $statsThrough = match($dataset) {
            'fantrax'=>DB::table('player_projection_baselines')->max('captured_at'),
            'season'=>DB::table('season_player_stats')->max('stats_through'),
            default=>DB::table('player_projections')->max('window_end_date'),
        };
        return compact('players', 'positions', 'rookies', 'search', 'columns', 'headers', 'sort', 'direction', 'dailyTargetsSort', 'teamOptions', 'selectedTeam', 'availability', 'selectedLine', 'selectedPp', 'selectedLines', 'selectedPps', 'dataset', 'datasetLabel', 'statsThrough', 'ownedTeamId', 'playing', 'playingDate');
    }

    public function homeRecommendations(string $date): \Illuminate\Support\Collection
    {
        return PublicData::remember('home-player-watch:dfo:'.$date, 30, function () use ($date) {
            $lines = PublicData::remember('badges:active_line_combinations', 30, fn()=>DB::table('active_line_combinations')->orderBy('checked_at')->orderBy('id')->get())
                ->keyBy(fn($r)=>$this->assignmentKey($r->team, $r->player_name).'|'.strtoupper(trim($r->position_group)));
            $pp = PublicData::remember('badges:active_pp_lines', 30, fn()=>DB::table('active_pp_lines')->orderBy('checked_at')->orderBy('id')->get())
                ->keyBy(fn($r)=>$this->assignmentKey($r->team, $r->player_name));
            $query = DB::table('season_player_stats as s')
                ->leftJoin('player_projections as p', 'p.player_id', '=', 's.player_id')
                ->where('s.season_id', '2026-27')->whereIn('s.position', ['F', 'D', 'G'])
                ->whereNotIn('s.player_id', fn($q)=>$q->from('active_fantasy_rosters')->select('player_id')
                    ->where('game_date', fn($latest)=>$latest->from('active_fantasy_rosters')->selectRaw('MAX(game_date)')))
                ->select('s.*', 'p.projected_fpts_per_game');
            $ordered = $this->dailyTargetsPool($query, $lines, $pp, $date, 'ec_proj', 'desc', ['fpts'=>'s.season_fpts', 'gp'=>'s.season_gp', 'fpts_gp'=>'s.season_fpts_per_game']);
            $ids = $ordered->groupBy('position')->flatMap(fn($players)=>$players->take(3)->pluck('player_id'))->values();
            $rows = (clone $query)->whereIn('s.player_id', $ids)->get()->keyBy('player_id');
            return $ids->map(fn($id)=>$rows[$id])->groupBy('position');
        });
    }

    private function dailyTargetsPool($query, $lines, $pp, string $date, string $sort, string $direction, array $datasetFields): \Illuminate\Support\Collection
    {
        $sources = [];
        foreach (['active_daily_players', 'active_available_goalies'] as $table) {
            $sources[$table] = DB::table($table)->where('game_date', $date)->get(['team', 'player_name', 'source_rank'])
                ->keyBy(fn($row)=>$this->assignmentKey($row->team, $row->player_name));
        }
        $starters = DB::table('active_starting_goalies')->where('game_date', $date)->get(['team', 'player_name', 'starting_status']);
        $confirmed = [];
        foreach ($starters as $row) {
            if (strtolower(trim((string)$row->starting_status)) === 'confirmed') {
                $confirmed[$this->teamCode($row->team)] = $this->assignmentKey($row->team, $row->player_name);
            }
        }
        $starters = $starters->keyBy(fn($row)=>$this->assignmentKey($row->team, $row->player_name));
        // Rank only lightweight records across the complete filtered pool. Fetch
        // full stats for the requested page after sorting, so Show More is stable.
        [$expression, $bindings] = $this->sortExpression($sort, $datasetFields);
        $ordered = (clone $query)->select('s.player_id', 's.player_name', 's.nhl_team', 's.position', 'p.projected_fpts_per_game')->selectRaw($expression.' as season_sort_value', $bindings)->get()
            ->map(function($row) use ($lines, $pp, $sources, $starters, $confirmed) {
                $key = $this->assignmentKey($row->nhl_team, $row->player_name);
                $goalie = $row->position === 'G';
                $unit = $goalie ? null : ($pp[$key]->pp_unit ?? null);
                // The Canucks' forward and defenseman share a name.
                if ($key === 'VAN|eliaspettersson' && $row->position !== 'F') $unit = null;
                return [
                    'player_id'=>$row->player_id, 'position'=>$row->position, 'name'=>$row->player_name, 'goalie'=>$goalie,
                    'projected_points'=>$row->projected_fpts_per_game, 'sort_value'=>$row->season_sort_value,
                    'pp_unit'=>$unit, 'line_number'=>$lines[$key.'|'.$row->position]->line_number ?? null,
                    'source_rank'=>$sources[$goalie ? 'active_available_goalies' : 'active_daily_players'][$key]->source_rank ?? null,
                    'starting_status'=>$starters[$key]->starting_status ?? null,
                    'not_starting'=>$goalie && isset($confirmed[$this->teamCode($row->nhl_team)]) && $confirmed[$this->teamCode($row->nhl_team)] !== $key,
                ];
            })->sort(function($a, $b) use ($sort, $direction) {
                // Mixed position results keep skaters together, then goalies.
                return ($a['goalie'] <=> $b['goalie'])
                    ?: ($a['goalie'] ? DailyTargetsOrder::goaliePriority($a) <=> DailyTargetsOrder::goaliePriority($b) : DailyTargetsOrder::skaterPriority($a) <=> DailyTargetsOrder::skaterPriority($b))
                    ?: (($a['sort_value'] === null) <=> ($b['sort_value'] === null))
                    ?: ($direction === 'asc' ? 1 : -1) * (in_array($sort, ['player', 'team'], true) ? strcmp((string)$a['sort_value'], (string)$b['sort_value']) : ($a['sort_value'] <=> $b['sort_value']))
                    ?: ($a['goalie'] ? DailyTargetsOrder::goalies($a, $b) : DailyTargetsOrder::skaters($a, $b))
                    ?: strcmp($a['player_id'], $b['player_id']);
            })->values();
        return $ordered;
    }

    private function dailyTargetsPage($query, $lines, $pp, string $date, int $page, string $sort, string $direction, array $datasetFields): LengthAwarePaginator
    {
        $ordered = $this->dailyTargetsPool($query, $lines, $pp, $date, $sort, $direction, $datasetFields);
        $ids = $ordered->slice(($page - 1) * 25, 25)->pluck('player_id');
        $rows = (clone $query)->whereIn('s.player_id', $ids)->get()->keyBy('player_id');
        return new LengthAwarePaginator($ids->map(fn($id)=>$rows[$id])->values(), $ordered->count(), 25, $page, [
            'path'=>LengthAwarePaginator::resolveCurrentPath(), 'pageName'=>'page',
        ]);
    }

    private function selections(mixed $input, array $choices): array
    {
        if ($input===null || $input==='') return $choices;
        if($input==='empty')return [];
        $selected=array_values(array_intersect($choices,explode(',',(string)$input)));
        return $selected ?: $choices;
    }

    private function playingTeams(string $date): array
    {
        // Reuse dated snapshots from the scheduled collectors; page views make
        // no upstream requests. Include both sides, independent of availability.
        return PublicData::remember('season-players:playing-teams:'.$date, 30, function () use ($date) {
            $teams = [];
            foreach (['active_daily_players'=>'team', 'active_fantasy_rosters'=>'nhl_team', 'active_starting_goalies'=>'team', 'todays_odds'=>'team'] as $table=>$column) {
                $games = DB::table($table)->where('game_date', $date)->whereNotNull('opponent')->whereRaw("TRIM(opponent) <> ''")
                    ->select($column.' as team', 'opponent')->distinct()->get();
                foreach ($games as $game) {
                    $team = $this->teamCode($game->team);
                    $opponent = $this->teamCode(ltrim(trim($game->opponent), '@'));
                    if (!isset(DailyFaceoffPowerPlay::TEAMS[$team], DailyFaceoffPowerPlay::TEAMS[$opponent]) || $team === $opponent) continue;
                    $teams[$team] = $teams[$opponent] = true;
                }
            }
            foreach (self::TEAM_ALIASES as $alias=>$team) if (isset($teams[$team])) $teams[$alias] = true;
            return array_keys($teams);
        });
    }

    private function teamCode(?string $team): string
    {
        $team = strtoupper(trim((string)$team));
        return self::TEAM_ALIASES[$team] ?? $team;
    }

    private function assignmentKey(?string $team, string $name): string
    {
        $team = $this->teamCode($team);
        if (str_contains($name, ',')) { [$last, $first] = array_map('trim', explode(',', $name, 2)); $name = $first.' '.$last; }
        return $team.'|'.preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($name)));
    }

    private function sortExpression(string $sort, array $datasetFields): array
    {
        $fields = ['player'=>'LOWER(s.player_name)', 'team'=>"LOWER(COALESCE(r.fantasy_team_name, 'Free Agent'))",
            'gp'=>$datasetFields['gp'], 'fpts'=>$datasetFields['fpts'], 'fpts_gp'=>$datasetFields['fpts_gp'], 'ec_proj'=>'p.projected_fpts_per_game'];
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
