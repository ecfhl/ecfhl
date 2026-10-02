<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('team_icons', function (Blueprint $table) {
            $table->id();
            $table->string('team_slug')->unique();
            $table->string('mime_type', 50);
            $table->longText('image_data');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_icons');
    }
};
