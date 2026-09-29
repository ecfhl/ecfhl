<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_line_combinations', function (Blueprint $table) {
            $table->id();
            $table->string('team', 3)->index();
            $table->string('player_name');
            $table->string('position_group', 1); // F or D
            $table->unsignedTinyInteger('line_number');
            $table->unsignedTinyInteger('unit_position')->nullable();
            $table->string('source_url');
            $table->timestamp('last_update');
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['team', 'player_name', 'position_group']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_line_combinations');
    }
};
