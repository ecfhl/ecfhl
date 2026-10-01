<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_daily_moves', function (Blueprint $table) {
            $table->id();
            $table->date('move_date');
            $table->string('fantasy_team_id',80);
            $table->string('fantasy_team_name');
            $table->unsignedTinyInteger('moves_used')->default(0);
            $table->unsignedTinyInteger('moves_left')->default(7);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['move_date','fantasy_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_daily_moves');
    }
};
