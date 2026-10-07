<?php

namespace App\Support\LiveScoring;

use Illuminate\Support\Str;

final class ViewData
{
    public function teams(array $snapshot): array
    {
        $teams = [];
        $allPlayers = (new PlayerDisplayEnrichment)->decorate(collect($snapshot['players'])->map(fn($p)=>$this->player($p)), $snapshot['fantasy_date']);
        foreach ($snapshot['teams'] as $id=>$team) {
            $players = $allPlayers->where('fantasy_team_id', (string)$id);
            $positions = [];
            foreach (['F'=>'Forwards','D'=>'Defense','G'=>'Goalies'] as $pos=>$label) {
                $positions[$pos] = ['label'=>$label,'rows'=>$players->where('position',$pos)->reject(fn($p)=>$p->roster_status==='MINORS')->sortBy(fn($p)=>$p->is_ir?1:0)->values()];
            }
            $positions['M'] = ['label'=>'Minors','rows'=>$players->where('roster_status','MINORS')->values()];
            $active = $players->where('scoring_status','ACTIVE');
            $stats = [];
            foreach (['gp','g','a','ppg','shg','gwg','w','so'] as $stat) $stats[$stat] = $active->sum(fn($p)=>$p->{'today_'.$stat} ?? 0);
            $teams[$id] = [
                'id'=>(string)$id,'name'=>$team['name'],'slug'=>Str::slug($team['name']),'positions'=>$positions,'count'=>$players->count(),
                'today_stats'=>$stats,'today_fpts'=>$team['daily_fpts'],'week_fpts'=>$team['period_fpts'],
                'daily_projected_fpts'=>$active->contains(fn($p)=>property_exists($p, 'custom_projection'))
                    ? $active->sum(fn($p)=>$p->projected_fpts_per_game ?? 0) : $team['daily_projected_fpts'],
                'today_fpts_change'=>$team['daily_fpts_change'] ?? 'same','week_fpts_change'=>$team['period_fpts_change'] ?? 'same',
                'today_fpts_changed'=>$team['daily_fpts_changed'] ?? false,'week_fpts_changed'=>$team['period_fpts_changed'] ?? false,
                'games_in_progress'=>$players->where('game_status','2')->pluck('game_id')->unique()->count(),
            ];
        }
        return $teams;
    }

    public function player(array $p): object
    {
        $bench = $p['scoring_status'] === 'BENCH';
        $contract = trim((string)($p['contract'] ?? ''));
        $row = (object)array_merge($p, [
            'daily_participant'=>true, 'is_bench'=>$bench, 'is_ir'=>$p['roster_status']==='INJURED_RESERVE',
            'last_update'=>$p['collected_at'] ?? null,
            'roster_status'=>$p['roster_status'],
            'today_fpts'=>$p['daily_fpts'], 'today_fpts_change'=>$p['fpts_change'] ?? 'same', 'today_fpts_changed'=>$p['fpts_changed'] ?? false,
            'projected_fpts_per_game'=>$p['daily_projected_fpts'], 'live_opponent_display'=>$p['game_display'],
            'opponent_display'=>$p['game_display'], 'game_time'=>null,
            'game_finished'=>$p['game_status']==='3', 'game_in_progress'=>$p['game_status']==='2',
            'line_number'=>null, 'pp_unit'=>null, 'vegas_odds'=>null,'vegas_odds_class'=>null,
            'contract_label'=>$contract, 'contract_class'=>str_contains(strtoupper($contract),'MINOR')?'team-minors':(preg_match('/^[234]\s*YEAR/i',$contract)?'contract-red':'contract-green'),
            'starting_status'=>null,'starting_status_class'=>'goalie-status-na',
        ]);
        $row->today_gp = $p['gp'];
        foreach (['g'=>'G','a'=>'A','ppg'=>'PPG','shg'=>'SHG','gwg'=>'GWG','w'=>'W','so'=>'SHO','ol'=>'OL+ShL'] as $key=>$stat) $row->{'today_'.$key} = $p['stats'][$stat]['value'] ?? 0;
        return $row;
    }
}
