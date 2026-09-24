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

    $byTitles = $teams;
    usort($byTitles, fn($a,$b) => ($b['titles'] ?? 0) <=> ($a['titles'] ?? 0));
    $byFirst = $teams;
    usort($byFirst, fn($a,$b) => ($b['h2h_first'] ?? 0) <=> ($a['h2h_first'] ?? 0));
    $byWins = $teams;
    usort($byWins, fn($a,$b) => ($b['w'] ?? 0) <=> ($a['w'] ?? 0));

    $leaders = [
        'Championships' => ['value' => $byTitles[0]['titles'] ?? 0, 'team' => $byTitles[0]['team'] ?? '—'],
        'H2H regular-season firsts' => ['value' => $byFirst[0]['h2h_first'] ?? 0, 'team' => $byFirst[0]['team'] ?? '—'],
        'H2H wins' => ['value' => $byWins[0]['w'] ?? 0, 'team' => $byWins[0]['team'] ?? '—'],
    ];

    return view('home', compact('seasons','teams','trades','latest','latestLeader','championships','statsSeasons','leaders'));
});

Route::get('/seasons', fn(EcfhlData $data) => view('seasons.index', ['seasons' => $data->seasons()]));

Route::get('/seasons/{season}', function (string $season, EcfhlData $data) {
    $season = rawurldecode($season);
    $row = $data->season($season);
    abort_unless($row, 404);

    $awards = [];
    foreach ([
        ['art_ross','art_ross_team','art_ross_points','Art Ross'],
        ['norris','norris_team','norris_points','Norris'],
        ['vezina','vezina_team','vezina_points','Vezina'],
        ['calder','calder_team','calder_points','Calder'],
    ] as [$player,$team,$points,$label]) {
        if (!empty($row[$player])) {
            $awards[] = ['player'=>$row[$player], 'team'=>$row[$team] ?? '', 'points'=>$row[$points] ?? null, 'label'=>$label];
        }
    }

    return view('seasons.show', [
        'season' => $row,
        'standings' => $data->teamSeasons($season),
        'awards' => $awards,
    ]);
})->where('season', '.*');

Route::get('/teams', fn(EcfhlData $data) => view('teams.index', ['teams' => $data->teams()]));

Route::get('/teams/{slug}', function (string $slug, EcfhlData $data) {
    $team = $data->team($slug);
    abort_unless($team, 404);
    return view('teams.show', ['team'=>$team, 'history'=>$data->teamSeasons(null, $team['team'])]);
});

Route::get('/trades', function (EcfhlData $data) {
    $trades = $data->trades();
    $seasons = array_values(array_unique(array_column($trades, 'season')));
    rsort($seasons);
    return view('trades.index', compact('trades','seasons'));
});

Route::get('/draft', function (EcfhlData $data) {
    $seasons = $data->draftSeasons();
    $selected = request('season', $seasons[0] ?? '');
    return view('draft.index', ['seasons'=>$seasons, 'selected'=>$selected, 'picks'=>$data->draftSeason($selected)]);
});

Route::get('/prizes', fn(EcfhlData $data) => view('prizes', ['seasons'=>$data->seasons(), 'totals'=>$data->prizeTotals()]));

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
