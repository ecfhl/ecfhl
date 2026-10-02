<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('lineup_advisor_profiles')) {
            Schema::table('lineup_advisor_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('lineup_advisor_profiles','style_text')) {
                    $table->text('style_text')->nullable()->after('first_name');
                }
                if (!Schema::hasColumn('lineup_advisor_profiles','is_conservative')) {
                    $table->boolean('is_conservative')->default(false)->after('style_text');
                }
                if (!Schema::hasColumn('lineup_advisor_profiles','sort_order')) {
                    $table->unsignedInteger('sort_order')->default(100)->after('is_conservative');
                }
            });

            $defaults=[
                'mike'=>[
                    'style_text'=>"Here is the thing. {advice}\nListen, if you want to be a good pro, {advice_lower}\nAt the end of the day, {advice_lower}\nEvery day, it is process and structure. {advice}",
                    'is_conservative'=>false,'sort_order'=>10,
                ],
                'pierre'=>[
                    'style_text'=>"My understanding is that {advice_lower}\nChecking in on the options, {advice_lower}\nKeep an eye on this one. {advice}\nFrom what I am seeing, {advice_lower}",
                    'is_conservative'=>true,'sort_order'=>30,
                ],
                'john'=>[
                    'style_text'=>"Listen. {advice}\nHonestly, this is not complicated. {advice}\nAccountability. {advice}\nEarn it. {advice}",
                    'is_conservative'=>false,'sort_order'=>20,
                ],
            ];
            foreach($defaults as $key=>$values){
                DB::table('lineup_advisor_profiles')->where('advisor_key',$key)->update(array_merge($values,['updated_at'=>now()]));
            }
        }

        if (Schema::hasTable('lineup_advice') && !Schema::hasColumn('lineup_advice','advisor_advice_json')) {
            Schema::table('lineup_advice', function (Blueprint $table) {
                $table->longText('advisor_advice_json')->nullable()->after('advice_text');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('lineup_advice') && Schema::hasColumn('lineup_advice','advisor_advice_json')) {
            Schema::table('lineup_advice', function (Blueprint $table) {
                $table->dropColumn('advisor_advice_json');
            });
        }
        if (Schema::hasTable('lineup_advisor_profiles')) {
            Schema::table('lineup_advisor_profiles', function (Blueprint $table) {
                $drop=[];
                foreach(['style_text','is_conservative','sort_order'] as $column){
                    if(Schema::hasColumn('lineup_advisor_profiles',$column))$drop[]=$column;
                }
                if($drop)$table->dropColumn($drop);
            });
        }
    }
};
