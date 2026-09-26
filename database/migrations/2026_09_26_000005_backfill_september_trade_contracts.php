<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $tid='2026-27-20260923-green-machine-morning-sherwoods';
        foreach ([1=>'Matthew Tkachuk',4=>'Mitch Marner',8=>'Kyle Connor'] as $item=>$name) {
            DB::table('trade_assets')->where('trade_id',$tid)->where('trade_asset_id',$tid.'-'.$item)
                ->where('asset_type','player')->where('asset_description',$name)
                ->whereNull('contract_years_at_trade')->update(['contract_years_at_trade'=>1]);
        }
    }
    public function down(): void
    {
        // Retain verified observations when reverting application code.
    }
};
