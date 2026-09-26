<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Values verified directly against the 2025-26 Fantrax Players page.
        $verified = [
            "Ryan O'Reilly" => 'FA',
            'Elias Pettersson' => '2 Years',
        ];

        foreach ($verified as $player => $contract) {
            $assets = DB::table('trade_assets as ta')
                ->join('trades as t', 't.trade_id', '=', 'ta.trade_id')
                ->join('seasons as s', 's.season_id', '=', 't.season_id')
                ->where('s.season_name', '2025-26')
                ->where('ta.asset_type', 'player')
                ->where('ta.asset_description', $player)
                ->select('ta.trade_asset_id')
                ->get();

            // These verified labels are non-database contract-year display values.
            // Store them in the source descriptions so Archive::trades() preserves
            // and renders the contract sticker without guessing a numeric year.
            foreach ($assets as $asset) {
                DB::table('trade_assets')
                    ->where('trade_asset_id', $asset->trade_asset_id)
                    ->where('asset_description', $player)
                    ->update(['asset_description' => $player.' ('.$contract.')']);
            }
        }
    }

    public function down(): void
    {
        foreach (["Ryan O'Reilly" => 'FA', 'Elias Pettersson' => '2 Years'] as $player => $contract) {
            DB::table('trade_assets')
                ->where('asset_description', $player.' ('.$contract.')')
                ->update(['asset_description' => $player]);
        }
    }
};
