<?php

use App\Support\TradeContracts;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $seasons=DB::table('seasons')->get()->keyBy('season_id');
        $trades=DB::table('trades')->get()->keyBy('trade_id');
        $assets=DB::table('trade_assets')->where('asset_type','player')->get()->groupBy('trade_id');
        DB::transaction(function () use ($seasons, $trades, $assets) {
            $updated=0;
            foreach (TradeContracts::records() as $c) {
                $trade=$trades->get($c['trade_id']);
                $season=$trade ? $seasons->get($trade->season_id) : null;
                if (!$season || $season->season_name!==$c['season'] || $season->league_id!==$c['league_id']) continue;
                $matches=($assets->get($c['trade_id'])??collect())->filter(fn($a)=>
                    $a->source_side===$c['source_side'] && TradeContracts::playerName($a->asset_description??'')===$c['player_name']);
                // Never guess which asset to update when the identity is ambiguous.
                if ($matches->count()!==1) continue;
                $updated+=DB::table('trade_assets')->where('trade_asset_id',$matches->first()->trade_asset_id)
                    ->whereNull('contract_years_at_trade')->update(['contract_years_at_trade'=>$c['contract_years_at_trade']]);
            }
            logger()->info('Verified historical trade contracts backfilled', ['updated_assets'=>$updated]);
        });
    }

    public function down(): void
    {
        // Preserve researched data on rollback; existing values were never overwritten.
    }
};
