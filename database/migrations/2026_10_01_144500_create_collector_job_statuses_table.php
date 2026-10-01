<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('collector_job_statuses', function (Blueprint $table) {
            $table->string('job_key')->primary();
            $table->string('status', 16);
            $table->text('message')->nullable();
            $table->timestamp('ran_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collector_job_statuses');
    }
};
