<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('collector_schedules',function(Blueprint $t){$t->string('job_key')->primary();$t->text('settings_json');$t->timestamps();});
        Schema::create('collector_state',function(Blueprint $t){$t->string('state_key')->primary();$t->text('value');$t->timestamps();});
    }
    public function down(): void {Schema::dropIfExists('collector_state');Schema::dropIfExists('collector_schedules');}
};
