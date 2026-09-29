<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('active_pp_lines', function (Blueprint $table) {
            $table->id();
            $table->string('team', 3)->index();
            $table->string('player_name');
            $table->unsignedTinyInteger('pp_unit');
            $table->unsignedTinyInteger('unit_position')->nullable();
            $table->string('source_url');
            $table->timestamp('last_update');
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();

            $table->unique(['team', 'player_name', 'pp_unit']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('active_pp_lines');
    }
};
