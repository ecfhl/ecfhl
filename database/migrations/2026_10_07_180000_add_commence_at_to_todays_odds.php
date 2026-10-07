<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('todays_odds', fn(Blueprint $table) => $table->timestamp('commence_at')->nullable());
    }

    public function down(): void
    {
        Schema::table('todays_odds', fn(Blueprint $table) => $table->dropColumn('commence_at'));
    }
};
