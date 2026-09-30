<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('active_daily_players', function (Blueprint $table) {
            if (!Schema::hasColumn('active_daily_players', 'game_time')) {
                $table->string('game_time', 32)->nullable()->after('home_away');
            }
        });
    }

    public function down(): void
    {
        Schema::table('active_daily_players', function (Blueprint $table) {
            if (Schema::hasColumn('active_daily_players', 'game_time')) {
                $table->dropColumn('game_time');
            }
        });
    }
};
