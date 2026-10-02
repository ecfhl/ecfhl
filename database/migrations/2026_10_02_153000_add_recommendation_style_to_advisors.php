<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lineup_advisor_profiles')) return;

        if (!Schema::hasColumn('lineup_advisor_profiles','recommendation_style')) {
            Schema::table('lineup_advisor_profiles', function (Blueprint $table) {
                $table->string('recommendation_style',20)->default('neutral')->after('style_text');
            });
        }

        DB::table('lineup_advisor_profiles')->get()->each(function($advisor){
            $style=(bool)($advisor->is_conservative??false)?'conservative':'neutral';
            if((string)($advisor->advisor_key??'')==='john')$style='aggressive';
            DB::table('lineup_advisor_profiles')->where('id',$advisor->id)->update([
                'recommendation_style'=>$style,
                'updated_at'=>now(),
            ]);
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('lineup_advisor_profiles') && Schema::hasColumn('lineup_advisor_profiles','recommendation_style')) {
            Schema::table('lineup_advisor_profiles', function (Blueprint $table) {
                $table->dropColumn('recommendation_style');
            });
        }
    }
};
