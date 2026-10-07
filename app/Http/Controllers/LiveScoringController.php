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
            $standing = $standings->get($team['slug']);
            $rank = (int)($standing['rank'] ?? 0);
            $suffix = in_array($rank % 100, [11, 12, 13], true) ? 'th' : match ($rank % 10) { 1=>'st', 2=>'nd', 3=>'rd', default=>'th' };
            $team['rank_label'] = $rank > 0 ? $rank.$suffix : null;
            $team['record'] = $standing && isset($standing['w'], $standing['l'], $standing['t'])
                ? $standing['w'].'–'.$standing['l'].'–'.$standing['t'] : null;
        }
        unset($team);
        $matchups = [];
        foreach (($snapshot['matchups'] ?? []) as $pair) $matchups[] = ['away'=>$teams[$pair['away_team_id']],'home'=>$teams[$pair['home_team_id']]];
        $scheduleLabel = $snapshot ? 'Scoring period '.$snapshot['period'].' '.$snapshot['period_label'] : null;
        $scoreLastUpdate = $lastUpdate = $snapshot['collected_at'] ?? null;
        $autoRefresh = $date === $today;
        return response()->view('teams.current-index', compact('teams','matchups','scheduleLabel','date','yesterday','today','tomorrow','lastUpdate','scoreLastUpdate','autoRefresh'))
            ->header('Cache-Control','no-store, no-cache, must-revalidate');
    }
}
