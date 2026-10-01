<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_daily_scores', function (Blueprint $table) {
            $table->unsignedInteger('gp')->default(0)->after('today_fpts');
            $table->unsignedInteger('g')->default(0)->after('gp');
            $table->unsignedInteger('a')->default(0)->after('g');
            $table->unsignedInteger('ppg')->default(0)->after('a');
            $table->unsignedInteger('shg')->default(0)->after('ppg');
            $table->unsignedInteger('gwg')->default(0)->after('shg');
            $table->unsignedInteger('w')->default(0)->after('gwg');
            $table->unsignedInteger('so')->default(0)->after('w');
        });
    }

    public function down(): void
    {
        Schema::table('active_daily_scores', function (Blueprint $table) {
            $table->dropColumn(['gp','g','a','ppg','shg','gwg','w','so']);
        });
    }
};
