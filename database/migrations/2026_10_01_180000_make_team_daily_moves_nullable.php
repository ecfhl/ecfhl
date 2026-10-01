<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('team_daily_moves', function (Blueprint $table) {
            $table->unsignedTinyInteger('moves_used')->nullable()->default(null)->change();
            $table->unsignedTinyInteger('moves_left')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('team_daily_moves', function (Blueprint $table) {
            $table->unsignedTinyInteger('moves_used')->nullable(false)->default(0)->change();
            $table->unsignedTinyInteger('moves_left')->nullable(false)->default(7)->change();
        });
    }
};
