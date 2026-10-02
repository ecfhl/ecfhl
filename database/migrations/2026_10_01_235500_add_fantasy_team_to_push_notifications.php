<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->string('fantasy_team_id',120)->nullable()->after('url')->index();
        });
    }

    public function down(): void
    {
        Schema::table('push_notifications', function (Blueprint $table) {
            $table->dropIndex(['fantasy_team_id']);
            $table->dropColumn('fantasy_team_id');
        });
    }
};
