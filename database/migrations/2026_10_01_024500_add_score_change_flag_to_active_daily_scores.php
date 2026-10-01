<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_daily_scores', function (Blueprint $table) {
            $table->boolean('fpts_changed')->default(false)->after('today_fpts');
        });
    }

    public function down(): void
    {
        Schema::table('active_daily_scores', function (Blueprint $table) {
            $table->dropColumn('fpts_changed');
        });
    }
};
