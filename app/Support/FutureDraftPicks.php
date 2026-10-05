<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class FutureDraftPicks
{
    public function forTeam(string $teamName, int $year = 2027): array
    {
        $teams = CurrentTeams::standings();
        $team = collect($teams)->first(fn($row)=>Str::slug($row['team']) === Str::slug($teamName));
        $franchise = $team['franchise_id'] ?? null;
        if (!$franchise) return ['franchise_id'=>null, 'picks'=>[]];
        $rule = DB::table('rules')->where('rule_text', 'like', '%draft%rounds%')->value('rule_text');
        $rounds = preg_match('/(\d+)\s+rounds/i', (string)$rule, $match) ? (int)$match[1] : 21;
        $assets = DB::table('trade_assets as a')->join('trades as t', 't.trade_id', '=', 'a.trade_id')
            ->where('a.asset_type','pick')->where('a.draft_year',$year)
            ->where('t.is_vetoed',false)->where('t.is_reversed',false)
            ->where(fn($q)=>$q->whereNull('t.executed_explicit')->orWhere('t.executed_explicit',true))
            ->orderBy('t.trade_datetime')->orderBy('t.trade_id')->orderBy('a.item_order')
            ->get(['a.*'])->map(fn($row)=>(array)$row)->all();
        $picks = self::ownership($teams, $assets, $rounds);
        return ['franchise_id'=>$franchise, 'picks'=>array_values(array_filter($picks, fn($pick)=>$pick['owner']===$franchise))];
    }

    public static function ownership(array $teams, array $assets, int $rounds): array
    {
        $picks=[]; $names=[];
        foreach ($teams as $team) {
            $id=$team['franchise_id'] ?? null;
            if (!$id) continue;
            $names[Str::slug($team['team'])]=$id;
            for ($round=1; $round<=$rounds; $round++) $picks[$id.'|'.$round]=[
                'owner'=>$id, 'original_franchise_id'=>$id, 'original_team'=>$team['team'], 'round'=>$round,
            ];
        }
        foreach ($assets as $asset) {
            $original=($asset['pick_original_franchise_id'] ?? null) ?: ($names[Str::slug($asset['pick_original_team_raw'] ?? '')] ?? null);
            $original ??= $asset['from_franchise_id'] ?? null;
            $key=$original.'|'.($asset['draft_round'] ?? '');
            if (!isset($picks[$key]) || empty($asset['to_franchise_id'])) continue;
            // Follow recorded transfers in chronological order, including a pick traded onward.
            $picks[$key]['owner']=$asset['to_franchise_id'];
        }
        usort($picks, fn($a,$b)=>($a['round']<=>$b['round']) ?: strnatcasecmp($a['original_team'],$b['original_team']));
        return $picks;
    }
}
