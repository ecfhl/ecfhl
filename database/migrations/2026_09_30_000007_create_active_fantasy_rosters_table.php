<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_fantasy_rosters', function (Blueprint $table) {
            $table->id();
            $table->date('game_date')->index();
            $table->string('fantasy_team_id', 64)->index();
            $table->string('fantasy_team_name', 255)->index();
            $table->string('player_id', 64)->index();
            $table->string('player_name', 255);
            $table->string('nhl_team', 8)->nullable()->index();
            $table->string('position', 8)->nullable()->index();
            $table->string('roster_status', 32)->nullable();
            $table->boolean('is_bench')->default(false);
            $table->boolean('is_ir')->default(false);
            $table->string('injury_status', 255)->nullable();
            $table->string('opponent', 8)->nullable();
            $table->string('home_away', 8)->nullable();
            $table->decimal('projected_fpts', 10, 2)->nullable();
            $table->timestamp('last_update')->nullable();
            $table->timestamps();
            $table->unique(['game_date','fantasy_team_id','player_id'], 'fantasy_roster_day_team_player_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_fantasy_rosters');
    }
};
