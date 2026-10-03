<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('live_scoring_snapshots', function (Blueprint $table) {
            $table->id();
            $table->string('league_id', 64);
            $table->date('fantasy_date');
            $table->date('source_date');
            $table->string('source', 32);
            $table->json('payload');
            $table->json('source_payload');
            $table->unsignedInteger('player_count');
            $table->timestamp('collected_at');
            $table->timestamps();
            $table->unique(['league_id','fantasy_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('live_scoring_snapshots');
    }
};
