<?php

use App\Support\EcfhlData;
use Illuminate\Support\Facades\Route;

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

Route::get('/prizes', fn(EcfhlData $data) => view('prizes', ['seasons'=>$data->seasons()]));

Route::get('/rules', function () {
    $sections = [
        'Trades' => [
            'No trades are allowed after the trade deadline.',
            'Teams may trade draft picks for the upcoming draft only. Draft picks received in a trade may be traded again and remain valid if a team changes ownership or becomes defunct.',
            'Every trade has a one-day review period. A trade is vetoed if four or more General Managers object to it.',
        ],
        'Drops and buyouts' => [
            'A player may be dropped without penalty if the player retires, leaves the NHL, has a one-year contract, or is an eligible minor-league player.',
            'One-year contract players may not normally be dropped during the offseason unless they are bought out, officially retire, or leave the NHL.',
            'Buyout cost by contract remaining: three years — third-round pick; two years — fourth-round pick; one year — fifth-round pick.',
        ],
        'Free agents and waivers' => [
            'Undrafted players become free agents after the draft. Free agents may be added until the trade deadline, provided the team remains within roster limits and creates a legal roster spot.',
            'A dropped player remains on waivers for one day. If multiple teams claim the player, the team with the highest waiver priority receives the player and moves to the end of the waiver order.',
        ],
        'Playoffs' => [
            'ECFHL Cup: seeds 1–7 qualify. Seed 1 receives a bye. Round 1 is #2 vs #7, #3 vs #6 and #4 vs #5. In Round 2, seed 1 plays the lowest remaining seed.',
            'Loser Cup: seeds 8–14 qualify. Seed 8 receives a bye. Round 1 is #9 vs #14, #10 vs #13 and #11 vs #12. The winner receives Pick 1 and the runner-up receives Pick 2.',
        ],
        'Scoring' => [
            'Skaters: goal 1 point; assist 1; power-play goal 1; short-handed goal 1; game-winning goal 1.',
            'Goalies: a goalie goal is worth 2 points. Additional goalie scoring rules will be migrated from the full bylaws ledger.',
        ],
    ];
    return view('rules', compact('sections'));
});
