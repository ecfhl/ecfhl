<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_matchup_scores', function (Blueprint $table) {
            $table->id();
            $table->date('game_date')->index();
            $table->string('fantasy_team_id', 64);
            $table->decimal('week_fpts', 10, 2)->nullable();
            $table->boolean('week_fpts_changed')->default(false);
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['game_date','fantasy_team_id'], 'active_matchup_scores_day_team_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_matchup_scores');
    }
};
