<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if(!Schema::hasColumn('push_subscriptions','fantasy_team_id')){
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->string('fantasy_team_id',120)->nullable()->after('enabled')->index();
            });
        }
    }

    public function down(): void
    {
        if(Schema::hasColumn('push_subscriptions','fantasy_team_id')){
            Schema::table('push_subscriptions', function (Blueprint $table) {
                $table->dropIndex(['fantasy_team_id']);
                $table->dropColumn('fantasy_team_id');
            });
        }
    }
};
