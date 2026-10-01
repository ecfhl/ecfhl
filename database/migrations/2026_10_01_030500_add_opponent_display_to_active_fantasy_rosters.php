<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->string('opponent_display', 255)->nullable()->after('opponent');
        });
    }

    public function down(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->dropColumn('opponent_display');
        });
    }
};
