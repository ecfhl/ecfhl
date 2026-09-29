<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('ALTER TABLE active_daily_players MODIFY injury_status VARCHAR(255) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE active_daily_players MODIFY injury_status VARCHAR(32) NULL');
    }
};
