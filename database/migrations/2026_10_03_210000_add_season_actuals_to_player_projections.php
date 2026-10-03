<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('player_projections', function (Blueprint $table) {
            $table->decimal('season_fpts', 10, 2)->default(0)->after('window_end_date');
            $table->unsignedInteger('season_gp')->default(0)->after('season_fpts');
            $table->decimal('season_fpts_per_game', 10, 4)->default(0)->after('season_gp');
        });
    }
    public function down(): void {
        Schema::table('player_projections', fn (Blueprint $table) => $table->dropColumn(['season_fpts','season_gp','season_fpts_per_game']));
    }
};
