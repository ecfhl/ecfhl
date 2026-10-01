<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scoring_period_matchups', function (Blueprint $table) {
            $table->id();
            $table->string('season_id',20);
            $table->unsignedSmallInteger('period_number');
            $table->date('start_date');
            $table->date('end_date');
            $table->string('away_team_id',80)->nullable();
            $table->string('away_team_name');
            $table->decimal('away_score',10,2)->nullable();
            $table->string('home_team_id',80)->nullable();
            $table->string('home_team_name');
            $table->decimal('home_score',10,2)->nullable();
            $table->timestamps();
            $table->unique(['season_id','period_number','away_team_name','home_team_name'],'scoring_period_matchup_unique');
            $table->index(['season_id','period_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_period_matchups');
    }
};
