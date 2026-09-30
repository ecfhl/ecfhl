<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('todays_odds', function (Blueprint $table) {
            $table->id();
            $table->date('game_date')->index();
            $table->string('team', 8)->index();
            $table->string('opponent', 8)->nullable();
            $table->string('home_away', 8)->nullable();
            $table->integer('american_odds')->nullable();
            $table->decimal('decimal_odds', 8, 3)->nullable();
            $table->unsignedSmallInteger('bookmaker_count')->default(0);
            $table->timestamp('source_updated_at')->nullable();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['game_date', 'team']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('todays_odds');
    }
};
