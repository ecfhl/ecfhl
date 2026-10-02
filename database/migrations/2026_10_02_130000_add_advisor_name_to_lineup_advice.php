<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('lineup_advice','advisor_name')) {
            Schema::table('lineup_advice', function (Blueprint $table) {
                $table->string('advisor_name',20)->default('Mike')->after('moves_left');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('lineup_advice','advisor_name')) {
            Schema::table('lineup_advice', function (Blueprint $table) {
                $table->dropColumn('advisor_name');
            });
        }
    }
};
