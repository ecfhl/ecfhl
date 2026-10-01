<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->decimal('projected_gp', 10, 2)->nullable()->after('projected_fpts');
            $table->decimal('projected_fpts_per_game', 10, 3)->nullable()->after('projected_gp');
        });
    }

    public function down(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->dropColumn(['projected_gp','projected_fpts_per_game']);
        });
    }
};
