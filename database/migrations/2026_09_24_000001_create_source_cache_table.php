<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('source_cache', function (Blueprint $table) {
            $table->id();
            $table->string('source_key')->unique();
            $table->text('source_url');
            $table->longText('payload');
            $table->timestamp('retrieved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('source_cache');
    }
};
