<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_starting_goalies', function (Blueprint $table) {
            $table->id();
            $table->date('game_date')->index();
            $table->string('team', 8)->index();
            $table->string('opponent', 8)->nullable();
            $table->string('home_away', 8)->nullable();
            $table->string('player_name');
            $table->string('starting_status', 32)->nullable();
            $table->text('source_url');
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('checked_at')->index();
            $table->timestamps();
            $table->unique(['game_date', 'team', 'player_name'], 'active_starting_goalies_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_starting_goalies');
    }
};
