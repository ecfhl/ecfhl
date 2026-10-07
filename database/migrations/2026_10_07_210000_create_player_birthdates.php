<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('player_birthdates', function (Blueprint $table) {
            $table->unsignedInteger('nhl_player_id')->primary();
            $table->string('name_key')->index();
            $table->date('birth_date');
            $table->timestamp('refreshed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_birthdates');
    }
};
