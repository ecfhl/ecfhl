<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->string('contract', 32)->nullable()->after('projected_fpts');
        });
    }

    public function down(): void
    {
        Schema::table('active_fantasy_rosters', function (Blueprint $table) {
            $table->dropColumn('contract');
        });
    }
};
