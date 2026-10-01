<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_daily_players', function (Blueprint $table) {
            if (!Schema::hasColumn('active_daily_players', 'game_started')) {
                $table->boolean('game_started')->default(false)->after('game_time')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('active_daily_players', function (Blueprint $table) {
            if (Schema::hasColumn('active_daily_players', 'game_started')) {
                $table->dropColumn('game_started');
            }
        });
    }
};
