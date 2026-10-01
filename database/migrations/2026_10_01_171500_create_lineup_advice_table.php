<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lineup_advice', function (Blueprint $table) {
            $table->id();
            $table->date('advice_date');
            $table->string('fantasy_team_id',80);
            $table->string('fantasy_team_name');
            $table->unsignedTinyInteger('moves_left')->nullable();
            $table->string('advice_text',500);
            $table->timestamp('generated_at');
            $table->timestamps();
            $table->unique(['advice_date','fantasy_team_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lineup_advice');
    }
};
