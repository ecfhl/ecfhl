<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lineup_advisor_profiles')) {
            Schema::create('lineup_advisor_profiles', function (Blueprint $table) {
                $table->id();
                $table->string('advisor_key',20)->unique();
                $table->string('first_name',40);
                $table->timestamps();
            });
        }

        foreach (['mike'=>'Mike','pierre'=>'Pierre'] as $key=>$name) {
            DB::table('lineup_advisor_profiles')->updateOrInsert(
                ['advisor_key'=>$key],
                ['first_name'=>$name,'updated_at'=>now(),'created_at'=>now()]
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('lineup_advisor_profiles');
    }
};
