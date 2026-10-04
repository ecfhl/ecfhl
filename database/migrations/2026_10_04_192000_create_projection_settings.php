<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('projection_settings', function (Blueprint $table) {
            $table->unsignedInteger('id')->primary();
            foreach (['fantrax', 'season', '7d', '14d', '21d'] as $source) $table->decimal('weight_'.$source, 5, 2);
            $table->timestamps();
        });
        DB::table('projection_settings')->insert(['id'=>1, 'weight_fantrax'=>50, 'weight_season'=>0, 'weight_7d'=>25,
            'weight_14d'=>15, 'weight_21d'=>10, 'created_at'=>now(), 'updated_at'=>now()]);
    }

    public function down(): void { Schema::dropIfExists('projection_settings'); }
};
