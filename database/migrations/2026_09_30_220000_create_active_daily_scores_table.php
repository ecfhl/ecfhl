<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_daily_scores', function (Blueprint $table) {
            $table->id();
            $table->date('game_date')->index();
            $table->string('player_name', 255)->index();
            $table->string('nhl_team', 8)->nullable()->index();
            $table->string('position', 32)->nullable();
            $table->string('fantasy_status', 255)->nullable();
            $table->decimal('today_fpts', 10, 2)->default(0);
            $table->text('source_url')->nullable();
            $table->timestamp('checked_at')->nullable()->index();
            $table->timestamps();
            $table->unique(['game_date','player_name','nhl_team'], 'daily_scores_day_player_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_daily_scores');
    }
};
