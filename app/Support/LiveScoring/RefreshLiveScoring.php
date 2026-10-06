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
                    $oldFpts = $old !== null ? (float)($old['daily_fpts'] ?? 0) : null;
                    $newFpts = (float)($p['daily_fpts'] ?? 0);
                    $p['fpts_change'] = $oldFpts === null ? 'same' : ($newFpts > $oldFpts + 0.0001 ? 'up' : ($newFpts < $oldFpts - 0.0001 ? 'down' : 'same'));
                    $p['fpts_changed'] = $p['fpts_change'] !== 'same';
                }
                unset($p);
                foreach ($snapshot['teams'] as $id=>&$team) {
                    $old = $previous['teams'][$id] ?? null;
                    $oldDaily = $old !== null ? (float)($old['daily_fpts'] ?? 0) : null;
                    $newDaily = (float)($team['daily_fpts'] ?? 0);
                    $oldPeriod = $old !== null ? (float)($old['period_fpts'] ?? 0) : null;
                    $newPeriod = (float)($team['period_fpts'] ?? 0);

                    $team['daily_fpts_change'] = $oldDaily === null ? 'same' : ($newDaily > $oldDaily + 0.0001 ? 'up' : ($newDaily < $oldDaily - 0.0001 ? 'down' : 'same'));
                    $team['period_fpts_change'] = $oldPeriod === null ? 'same' : ($newPeriod > $oldPeriod + 0.0001 ? 'up' : ($newPeriod < $oldPeriod - 0.0001 ? 'down' : 'same'));
                    $team['daily_fpts_changed'] = $team['daily_fpts_change'] !== 'same';
                    $team['period_fpts_changed'] = $team['period_fpts_change'] !== 'same';
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
        $opponents=[];
        foreach($snapshot['matchups'] as $m){$opponents[$m['away_team_id']]=$m['home_team_id'];$opponents[$m['home_team_id']]=$m['away_team_id'];}
        foreach ($snapshot['players'] as $player) {
            $old = $previous[$player['fantasy_team_id'].'|'.$player['player_id']] ?? null;
            if ($player['scoring_status'] !== 'ACTIVE' || !$old || $player['daily_fpts'] <= $old['daily_fpts']) continue;
            try {
                $alert = ScoringAlert::payload($snapshot, $player);
                app(WebPush::class)->notify('live-score',$alert['title'],$alert['body'],$alert['url'],$alert['fantasy_team_id'],['opponent_team_id'=>$opponents[$player['fantasy_team_id']]??null,'game_date'=>$snapshot['fantasy_date']]);
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
        // The scheduler invokes this check every minute. During the live-game
        // window collect on every invocation; outside games keep the 15-minute cadence.
        return $live || (int)$now->format('i') % 15 === 0;
    }
}
