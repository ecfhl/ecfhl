<?php

namespace App\Support\LiveScoring;

use App\Support\FantasyDay;
use App\Support\WebPush;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class RefreshLiveScoring
{
    public function __construct(private FantraxClient $client, private SnapshotRepository $repository, private SnapshotBuilder $builder) {}

    public function refresh(string $base, callable $output): bool
    {
        $day = (new FantasyDay)->parse($base);
        $failed = false;
        foreach ([$day->subDay(),$day,$day->addDay()] as $date) {
            $date = $date->toDateString();
            try {
                $daily = $this->client->matchup($date, '1');
                $period = $this->client->matchup($date, '2');
                $details = (new FantraxDailyDetailsCollector)->fetch($this->client, $date, (new FantraxRosterCollector)->collect($daily));
                $snapshot = $this->builder->build($date, $daily, $period, $details);
                $previous = $this->repository->get($date);
                $previousPlayers = [];
                foreach (($previous['players'] ?? []) as $p) $previousPlayers[$p['fantasy_team_id'].'|'.$p['player_id']] = $p;
                foreach ($snapshot['players'] as &$p) {
                    $old = $previousPlayers[$p['fantasy_team_id'].'|'.$p['player_id']] ?? null;
                    $p['fpts_changed'] = $old !== null && abs($old['daily_fpts'] - $p['daily_fpts']) > 0.0001;
                }
                unset($p);
                foreach ($snapshot['teams'] as $id=>&$team) {
                    $old = $previous['teams'][$id] ?? null;
                    $team['daily_fpts_changed'] = $old !== null && $old['daily_fpts'] !== $team['daily_fpts'];
                    $team['period_fpts_changed'] = $old !== null && $old['period_fpts'] !== $team['period_fpts'];
                }
                unset($team);
                DB::transaction(function () use ($snapshot, $daily, $period, $details, $date) {
                    $this->repository->publish($snapshot, ['day'=>$daily,'period'=>$period,'details'=>$details], CarbonImmutable::now('UTC'));
                    DB::table('job_run_history')->insert(['job_name'=>'ecfhl:refresh-live-scoring','target_date'=>$date,'rows_processed'=>count($snapshot['players']),'completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
                });
                $output($date.': published '.count($snapshot['players']).' players, '.count($snapshot['matchups']).' matchups');
                $this->notify($snapshot, $previousPlayers);
            } catch (\Throwable $e) {
                $failed = true;
                Log::error('Live scoring date refresh failed', ['fantasy_date'=>$date,'error'=>$e->getMessage()]);
                $output($date.': failed — '.$e->getMessage().'. Previous valid snapshot preserved.');
            }
        }
        return !$failed;
    }

    private function notify(array $snapshot, array $previous): void
    {
        if ($snapshot['fantasy_date'] !== (new FantasyDay)->today()->toDateString()) return;
        foreach ($snapshot['players'] as $player) {
            $old = $previous[$player['fantasy_team_id'].'|'.$player['player_id']] ?? null;
            if ($player['scoring_status'] !== 'ACTIVE' || !$old || $player['daily_fpts'] <= $old['daily_fpts']) continue;
            try {
                app(WebPush::class)->notify('live-score','ECFHL Live Scoring', $player['player_name'].' now has '.$player['daily_fpts'].' FPts.', '/teams/current?date='.$snapshot['fantasy_date'], $player['fantasy_team_id']);
            } catch (\Throwable $e) {
                Log::warning('Live scoring notification failed', ['error'=>$e->getMessage()]);
            }
        }
    }

    public function due(): bool
    {
        $now = CarbonImmutable::now('UTC');
        $live = false;
        foreach ((new FantasyDay)->dates() as $date) {
            foreach (($this->repository->get($date)['players'] ?? []) as $player) {
                if ($player['game_status'] === '2') $live = true;
                if ($player['game_status'] === '1' && $player['starts_at']) {
                    $start = CarbonImmutable::parse($player['starts_at']);
                    if ($now->betweenIncluded($start, $start->addHours(5))) $live = true;
                }
            }
        }
        return (int)$now->format('i') % ($live ? 2 : 15) === 0;
    }
}
