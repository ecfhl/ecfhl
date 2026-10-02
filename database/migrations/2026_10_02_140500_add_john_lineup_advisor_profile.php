<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lineup_advisor_profiles')) {
            DB::table('lineup_advisor_profiles')->updateOrInsert(
                ['advisor_key'=>'john'],
                ['first_name'=>'John','updated_at'=>now(),'created_at'=>now()]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lineup_advisor_profiles')) {
            DB::table('lineup_advisor_profiles')->where('advisor_key','john')->delete();
        }
    }
};
