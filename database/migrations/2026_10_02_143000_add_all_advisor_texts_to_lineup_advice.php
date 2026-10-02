<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('lineup_advice', function (Blueprint $table) {
            if (!Schema::hasColumn('lineup_advice','mike_advice_text')) {
                $table->text('mike_advice_text')->nullable()->after('advice_text');
            }
            if (!Schema::hasColumn('lineup_advice','pierre_advice_text')) {
                $table->text('pierre_advice_text')->nullable()->after('mike_advice_text');
            }
            if (!Schema::hasColumn('lineup_advice','john_advice_text')) {
                $table->text('john_advice_text')->nullable()->after('pierre_advice_text');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lineup_advice', function (Blueprint $table) {
            $drop=[];
            foreach(['mike_advice_text','pierre_advice_text','john_advice_text'] as $column){
                if(Schema::hasColumn('lineup_advice',$column))$drop[]=$column;
            }
            if($drop)$table->dropColumn($drop);
        });
    }
};
