<?php

use App\Support\EcfhlData;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/', function (EcfhlData $data) {
    $seasons = $data->seasons();
    $teams = $data->teams();
    $trades = $data->trades();
    $latest = $seasons[0] ?? null;
    $latestStandings = $latest ? $data->teamSeasons($latest['season']) : [];
    $latestLeader = $latestStandings[0]['team'] ?? null;

    $championships = count(array_filter($seasons, fn($s) => !empty($s['champion'])));
    $statsSeasons = count(array_unique(array_column($data->teamSeasons(), 'season')));
    $leaders = $data->overviewLeaders();

    return view('home', compact('seasons','teams','trades','latest','latestLeader','championships','statsSeasons','leaders'));
});

Route::get('/seasons', function (EcfhlData $data) {
    $seasons = $data->seasons();
    foreach ($seasons as &$season) {
        $season['regular_top3'] = $data->seasonRegularTop3($season['season']);
    }
    unset($season);
    return view('seasons.index', compact('seasons'));
});

Route::get('/seasons/{season}', function (string $season, EcfhlData $data) {
    $season = rawurldecode($season);
    $row = $data->season($season);
    abort_unless($row, 404);

    return view('seasons.show', [
        'season' => $row,
        'standings' => $data->teamSeasons($season),
        'awards' => $data->seasonAwards($season),
        'tradeCount' => $data->seasonTradeCount($season),
        'topPicks' => array_slice($data->draftSeason($season),0,3),
    ]);
})->where('season', '.*');

Route::get('/teams', function (EcfhlData $data) {
    $type = request('type','h2h');
    $status = request('status','all');
    return view('teams.index', [
        'teams'=>$data->teamLedger($type,$status),
        'type'=>$type,
        'status'=>$status,
    ]);
});

Route::get('/teams/{slug}', function (string $slug, EcfhlData $data) {
    $team = $data->team($slug);
    abort_unless($team, 404);
    return view('teams.show', [
        'team'=>$team,
        'history'=>$data->teamSeasons(null, $team['team']),
        'tradeCount'=>$data->teamTradeCount($team['id']),
    ]);
});

Route::get('/trades', function (EcfhlData $data) {
    $trades = $data->trades();
    $seasons = array_values(array_unique(array_column($trades, 'season')));
    rsort($seasons);
    $teams = [];
    foreach ($trades as $t) {
        if (!empty($t['from'])) $teams[$t['from']] = true;
        if (!empty($t['to'])) $teams[$t['to']] = true;
    }
    $teams = array_keys($teams);
    sort($teams,SORT_NATURAL|SORT_FLAG_CASE);
    $selectedSeason = (string)request('season','');
    $selectedTeam = (string)request('team','');
    return view('trades.index', compact('trades','seasons','teams','selectedSeason','selectedTeam'));
});

Route::get('/draft', function (EcfhlData $data) {
    $seasons = $data->draftSeasons();
    $selected = request('season', $seasons[0] ?? 'all');
    if ($selected !== 'all' && !in_array($selected,$seasons,true)) $selected = $seasons[0] ?? 'all';
    $q = trim((string)request('q',''));
    $picks = $data->draftSeason($selected);
    if ($q !== '') {
        $needle = mb_strtolower($q);
        $picks = array_values(array_filter($picks, fn($p) =>
            str_contains(mb_strtolower(($p['player']??'').' '.($p['team']??'')), $needle)
        ));
    }
    return view('draft.index', compact('seasons','selected','picks','q'));
});

Route::get('/prizes', fn(EcfhlData $data) => view('prizes', [
    'totals'=>$data->prizeTotals(),
    'awardsBySeason'=>$data->awardsBySeason(),
]));

Route::get('/rules', function () {
    $sections = DB::table('rules')->orderBy('rule_id')->get()->groupBy('section')->map(function($items){
        return $items->map(fn($r)=>trim(($r->subsection ? $r->subsection.' — ' : '').$r->rule_text))->all();
    })->all();
    return view('rules', compact('sections'));
});

Route::get('/api/debug/db-status', function () {
    $tables = ['seasons','franchises','team_seasons','players','drafts','draft_picks','trades','trade_assets','award_types','awards','prize_awards','season_prizes','rules'];
    $counts = [];
    foreach ($tables as $table) {
        try { $counts[$table] = DB::table($table)->count(); } catch (\Throwable $e) { $counts[$table] = 'ERROR: '.$e->getMessage(); }
    }
    return response()->json($counts);
});
