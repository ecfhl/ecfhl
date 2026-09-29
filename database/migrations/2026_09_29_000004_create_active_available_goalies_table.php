<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_available_goalies', function (Blueprint $table) {
            $table->id();
            $table->date('game_date')->index();
            $table->string('player_name');
            $table->string('team', 8)->index();
            $table->string('opponent', 8)->nullable();
            $table->string('home_away', 8)->nullable();
            $table->string('availability', 8);
            $table->string('waiver_day', 16)->nullable();
            $table->string('injury_status', 255)->nullable();
            $table->decimal('projected_fpts', 10, 2)->nullable();
            $table->unsignedInteger('source_rank')->nullable();
            $table->text('fantrax_url')->nullable();
            $table->string('starting_status', 32)->nullable();
            $table->timestamp('last_update')->nullable();
            $table->timestamps();
            $table->unique(['game_date', 'team', 'player_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_available_goalies');
    }
};
