<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::transaction(function () {
            // Delete the merged trade and all its assets
            $mergedTradeId = '2026-27-20260923-green-machine-morning-sherwoods';
            DB::table('trade_assets')->where('trade_id', $mergedTradeId)->delete();
            DB::table('trades')->where('trade_id', $mergedTradeId)->delete();

            // Get season_id for 2026-27
            $seasonId = DB::table('seasons')->where('season_name', '2026-27')->value('season_id');
            
            // Get franchise IDs
            $greenMachine = DB::table('franchises')->where('franchise_name', 'Green Machine')->value('franchise_id');
            $morningSherwoodz = DB::table('franchises')->where('franchise_name', 'Morning Sherwoods')->value('franchise_id');
            $orcas = DB::table('franchises')->where('franchise_name', 'Orcas')->value('franchise_id');
            $loneTsar = DB::table('franchises')->where('franchise_name', 'Lone Tsar')->value('franchise_id');
            $formentonsConstruction = DB::table('franchises')->where('franchise_name', "Formenton's Construction Company")->value('franchise_id');

            // Get player IDs
            $tkachuk = DB::table('players')->where('player_name', 'Matthew Tkachuk')->value('player_id');
            $marner = DB::table('players')->where('player_name', 'Mitch Marner')->value('player_id');
            $connor = DB::table('players')->where('player_name', 'Kyle Connor')->value('player_id');
            $zegras = DB::table('players')->where('player_name', 'Trevor Zegras')->value('player_id');

            // TRADE 1: Sep 23 2026 - Green Machine sent Matthew Tkachuk; Morning Sherwoods sent Trevor Zegras + 2027 R3 pick
            $trade1Id = '2026-27-20260923-green-machine-morning-sherwoods';
            DB::table('trades')->insert([
                'trade_id' => $trade1Id,
                'season_id' => $seasonId,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $morningSherwoodz,
                'from_name_raw' => 'Green Machine',
                'to_name_raw' => 'Morning Sherwoods',
                'trade_datetime' => '2026-09-23',
                'trade_date_raw' => '2026-09-23',
                'period' => null,
                'status' => 'approved',
                'is_vetoed' => false,
                'is_reversed' => false,
                'executed_explicit' => true,
                'source' => null,
                'source_id' => null,
            ]);

            // Trade 1 assets
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade1Id . '-1',
                'trade_id' => $trade1Id,
                'source_side' => 'from',
                'item_order' => 1,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $morningSherwoodz,
                'asset_type' => 'player',
                'asset_description' => 'Matthew Tkachuk',
                'player_id' => $tkachuk,
                'draft_year' => null,
                'draft_round' => null,
                'pick_original_franchise_id' => null,
                'pick_original_team_raw' => null,
                'contract_years_at_trade' => 1,
            ]);
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade1Id . '-2',
                'trade_id' => $trade1Id,
                'source_side' => 'to',
                'item_order' => 1,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $morningSherwoodz,
                'asset_type' => 'player',
                'asset_description' => 'Trevor Zegras',
                'player_id' => $zegras,
                'draft_year' => null,
                'draft_round' => null,
                'pick_original_franchise_id' => null,
                'pick_original_team_raw' => null,
                'contract_years_at_trade' => 1,
            ]);
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade1Id . '-3',
                'trade_id' => $trade1Id,
                'source_side' => 'to',
                'item_order' => 2,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $morningSherwoodz,
                'asset_type' => 'pick',
                'asset_description' => '2027 Round 3 Pick',
                'player_id' => null,
                'draft_year' => 2027,
                'draft_round' => 3,
                'pick_original_franchise_id' => $morningSherwoodz,
                'pick_original_team_raw' => 'Morning Sherwoods',
                'contract_years_at_trade' => null,
            ]);

            // TRADE 2: Sep 20 2026 - Green Machine sent Mitch Marner; Orcas sent 2026 R2 P10 + 2026 R2 P7
            $trade2Id = '2026-27-20260920-green-machine-orcas';
            DB::table('trades')->insert([
                'trade_id' => $trade2Id,
                'season_id' => $seasonId,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $orcas,
                'from_name_raw' => 'Green Machine',
                'to_name_raw' => 'Orcas',
                'trade_datetime' => '2026-09-20',
                'trade_date_raw' => '2026-09-20',
                'period' => null,
                'status' => 'approved',
                'is_vetoed' => false,
                'is_reversed' => false,
                'executed_explicit' => true,
                'source' => null,
                'source_id' => null,
            ]);

            // Trade 2 assets
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade2Id . '-1',
                'trade_id' => $trade2Id,
                'source_side' => 'from',
                'item_order' => 1,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $orcas,
                'asset_type' => 'player',
                'asset_description' => 'Mitch Marner',
                'player_id' => $marner,
                'draft_year' => null,
                'draft_round' => null,
                'pick_original_franchise_id' => null,
                'pick_original_team_raw' => null,
                'contract_years_at_trade' => 1,
            ]);
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade2Id . '-2',
                'trade_id' => $trade2Id,
                'source_side' => 'to',
                'item_order' => 1,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $orcas,
                'asset_type' => 'pick',
                'asset_description' => '2026 Round 2 Pick 10',
                'player_id' => null,
                'draft_year' => 2026,
                'draft_round' => 2,
                'pick_original_franchise_id' => $orcas,
                'pick_original_team_raw' => 'Orcas',
                'contract_years_at_trade' => null,
            ]);
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade2Id . '-3',
                'trade_id' => $trade2Id,
                'source_side' => 'to',
                'item_order' => 2,
                'from_franchise_id' => $greenMachine,
                'to_franchise_id' => $orcas,
                'asset_type' => 'pick',
                'asset_description' => '2026 Round 2 Pick 7',
                'player_id' => null,
                'draft_year' => 2026,
                'draft_round' => 2,
                'pick_original_franchise_id' => $orcas,
                'pick_original_team_raw' => 'Orcas',
                'contract_years_at_trade' => null,
            ]);

            // TRADE 3: Aug 23 2026 - Lone Tsar sent Kyle Connor + 2026 R5 P13; Formenton's Construction sent 2026 R3 P1
            $trade3Id = '2026-27-20260823-lone-tsar-formentonscc';
            DB::table('trades')->insert([
                'trade_id' => $trade3Id,
                'season_id' => $seasonId,
                'from_franchise_id' => $loneTsar,
                'to_franchise_id' => $formentonsConstruction,
                'from_name_raw' => 'Lone Tsar',
                'to_name_raw' => "Formenton's Construction Company",
                'trade_datetime' => '2026-08-23',
                'trade_date_raw' => '2026-08-23',
                'period' => null,
                'status' => 'approved',
                'is_vetoed' => false,
                'is_reversed' => false,
                'executed_explicit' => true,
                'source' => null,
                'source_id' => null,
            ]);

            // Trade 3 assets
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade3Id . '-1',
                'trade_id' => $trade3Id,
                'source_side' => 'from',
                'item_order' => 1,
                'from_franchise_id' => $loneTsar,
                'to_franchise_id' => $formentonsConstruction,
                'asset_type' => 'player',
                'asset_description' => 'Kyle Connor',
                'player_id' => $connor,
                'draft_year' => null,
                'draft_round' => null,
                'pick_original_franchise_id' => null,
                'pick_original_team_raw' => null,
                'contract_years_at_trade' => 1,
            ]);
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade3Id . '-2',
                'trade_id' => $trade3Id,
                'source_side' => 'from',
                'item_order' => 2,
                'from_franchise_id' => $loneTsar,
                'to_franchise_id' => $formentonsConstruction,
                'asset_type' => 'pick',
                'asset_description' => '2026 Round 5 Pick 13',
                'player_id' => null,
                'draft_year' => 2026,
                'draft_round' => 5,
                'pick_original_franchise_id' => $loneTsar,
                'pick_original_team_raw' => 'Lone Tsar',
                'contract_years_at_trade' => null,
            ]);
            DB::table('trade_assets')->insert([
                'trade_asset_id' => $trade3Id . '-3',
                'trade_id' => $trade3Id,
                'source_side' => 'to',
                'item_order' => 1,
                'from_franchise_id' => $loneTsar,
                'to_franchise_id' => $formentonsConstruction,
                'asset_type' => 'pick',
                'asset_description' => '2026 Round 3 Pick 1',
                'player_id' => null,
                'draft_year' => 2026,
                'draft_round' => 3,
                'pick_original_franchise_id' => $formentonsConstruction,
                'pick_original_team_raw' => "Formenton's Construction Company",
                'contract_years_at_trade' => null,
            ]);
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            // Remove the three new trades
            $tradeIds = [
                '2026-27-20260923-green-machine-morning-sherwoods',
                '2026-27-20260920-green-machine-orcas',
                '2026-27-20260823-lone-tsar-formentonscc',
            ];
            foreach ($tradeIds as $id) {
                DB::table('trade_assets')->where('trade_id', $id)->delete();
                DB::table('trades')->where('trade_id', $id)->delete();
            }
        });
    }
};

