<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('push_settings', function (Blueprint $table) {
            $table->string('setting_key')->primary();
            $table->longText('setting_value');
            $table->timestamps();
        });

        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->char('endpoint_hash',64)->unique();
            $table->text('endpoint');
            $table->boolean('enabled')->default(true);
            $table->timestamp('last_push_at')->nullable();
            $table->timestamps();
        });

        Schema::create('push_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('category',40);
            $table->string('title',120);
            $table->string('body',255);
            $table->string('url',255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notifications');
        Schema::dropIfExists('push_subscriptions');
        Schema::dropIfExists('push_settings');
    }
};
