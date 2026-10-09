<?php

namespace App\Http\Controllers;

use App\Support\FantasyDay;
use App\Support\LiveScoring\SnapshotRepository;
use App\Support\LiveScoring\ViewData;

final class LiveScoringController
{
    public function __invoke(FantasyDay $days, SnapshotRepository $repository, ViewData $presenter)
    {
        ['yesterday'=>$yesterday,'today'=>$today,'tomorrow'=>$tomorrow] = $days->dates();
        $date = (string)request('date', $today);
        if (!in_array($date, [$yesterday,$today,$tomorrow], true)) $date = $today;
        $snapshot = $repository->get($date);
        $teams = $snapshot ? $presenter->teams($snapshot) : [];
        $standings = collect(\App\Support\CurrentTeams::standings())->keyBy('slug');
        foreach ($teams as &$team) {
            $team += \App\Support\CurrentTeams::scoreboardStanding($standings->get($team['slug']));
        }
        unset($team);
        $matchups = [];
        foreach (($snapshot['matchups'] ?? []) as $pair) $matchups[] = ['away'=>$teams[$pair['away_team_id']],'home'=>$teams[$pair['home_team_id']]];
        $scheduleLabel = $snapshot ? 'Scoring period '.$snapshot['period'].' '.$snapshot['period_label'] : null;
        $scoreLastUpdate = $lastUpdate = $snapshot['collected_at'] ?? null;
        $autoRefresh = $date === $today;
        $scoringState=\App\Support\LiveScoring\ScoringAlert::state($snapshot??['fantasy_date'=>$date]);
        if (request('updates_scope') === 'nhl') $scoringState['nhlEvents'] = \App\Support\LiveScoring\NhlUpdates::events($date, $snapshot ?? []);
        return response()->view('teams.current-index', compact('teams','matchups','scheduleLabel','date','yesterday','today','tomorrow','lastUpdate','scoreLastUpdate','autoRefresh','scoringState'))
            ->header('Cache-Control','no-store, no-cache, must-revalidate');
    }
}
