<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collector_run_progress', function (Blueprint $table) {
            $table->string('job_key')->primary();
            $table->unsignedInteger('completed')->default(0);
            $table->unsignedInteger('total')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
        });
    }
    public function down(): void { Schema::dropIfExists('collector_run_progress'); }
};
