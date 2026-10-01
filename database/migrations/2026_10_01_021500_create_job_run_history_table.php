<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_run_history', function (Blueprint $table) {
            $table->id();
            $table->string('job_name', 100);
            $table->date('target_date')->nullable();
            $table->unsignedInteger('rows_processed')->nullable();
            $table->timestamp('completed_at');
            $table->timestamps();

            $table->index(['job_name', 'target_date', 'completed_at'], 'job_run_history_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_run_history');
    }
};
