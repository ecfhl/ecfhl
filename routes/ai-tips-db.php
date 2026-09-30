<?php

use Illuminate\Support\Facades\Route;

Route::get('/daily-targets', function () {
    $now = \Carbon\CarbonImmutable::now('America/Halifax');
    $today = $now->toDateString();
    $tomorrow = $now->addDay()->toDateString();
    $date = request('date', $today);

    abort_unless(is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/D', $date), 422, 'Use a valid game date.');
    $parts = array_map('intval', explode('-', $date));
    abort_unless(checkdate($parts[1], $parts[2], $parts[0]), 422, 'Use a valid game date.');

    $selectedDate = \Carbon\CarbonImmutable::createFromFormat('!Y-m-d', $date, 'America/Halifax');

    // AI Tips is database-backed. AiTips reads active_daily_players and
    // active_starting_goalies directly; PP badges are read from active_pp_lines
    // by the view. No JSON snapshot is used by this route.
    $groups = \App\Support\AiTips::groups([], $date);
    $fantasyRosterRows = \Illuminate\Support\Facades\DB::table('active_fantasy_rosters')
        ->whereDate('game_date', $date)
        ->orderBy('fantasy_team_name')
        ->orderByRaw("FIELD(position, 'F', 'D', 'G')")
        ->orderBy('is_bench')
        ->orderByRaw('opponent IS NULL')
        ->orderByDesc('projected_fpts')
        ->get();
    $fantasyTeams = $fantasyRosterRows->groupBy('fantasy_team_id');
    $availableDates = [$today, $tomorrow];
    $snapshot = null;

    return view('ai-tips', compact(
        'date',
        'today',
        'tomorrow',
        'selectedDate',
        'availableDates',
        'snapshot',
        'groups',
        'fantasyTeams'
    ));
});

Route::get('/ai-tips', function () {
    $query = request()->getQueryString();
    return redirect('/daily-targets'.($query ? '?'.$query : ''), 301);
});
