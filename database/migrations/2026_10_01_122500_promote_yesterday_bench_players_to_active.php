<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        $fantasyDay=CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay();
        $yesterday=$fantasyDay->subDay()->toDateString();

        DB::table('active_fantasy_rosters')
            ->whereDate('game_date',$yesterday)
            ->where('is_bench',true)
            ->update([
                'is_bench'=>false,
                'roster_status'=>'ACTIVE',
                'updated_at'=>now(),
            ]);
    }

    public function down(): void
    {
        // Historical lineup correction is intentionally not reversed.
    }
};
