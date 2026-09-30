<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->boolean('is_playing')->default(false)->after('injury_status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->dropColumn('is_playing');
        });
    }
};
