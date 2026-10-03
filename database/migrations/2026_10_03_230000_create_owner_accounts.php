<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('users',function(Blueprint $t){
   $t->id(); $t->string('name',100); $t->string('email')->unique();
   $t->timestamp('email_verified_at')->nullable(); $t->string('password')->nullable();
   $t->string('google_id')->nullable()->unique(); $t->boolean('is_admin')->default(false);
   $t->json('notification_preferences')->nullable(); $t->rememberToken(); $t->timestamps();
  });
  Schema::create('owner_team_claims',function(Blueprint $t){
   $t->string('fantasy_team_id',64)->primary(); $t->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
   $t->string('team_name'); $t->timestamps();
  });
  if(!Schema::hasTable('sessions')) Schema::create('sessions',function(Blueprint $t){
   $t->string('id')->primary(); $t->foreignId('user_id')->nullable()->index(); $t->string('ip_address',45)->nullable();
   $t->text('user_agent')->nullable(); $t->longText('payload'); $t->integer('last_activity')->index();
  });
  Schema::create('password_reset_tokens',function(Blueprint $t){$t->string('email')->primary();$t->string('token');$t->timestamp('created_at')->nullable();});
  Schema::table('push_subscriptions',function(Blueprint $t){
   $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete(); $t->char('feed_token_hash',64)->nullable()->unique();
  });
  Schema::create('push_deliveries',function(Blueprint $t){
   $t->id(); $t->foreignId('subscription_id')->constrained('push_subscriptions')->cascadeOnDelete();
   $t->foreignId('notification_id')->constrained('push_notifications')->cascadeOnDelete();
   $t->unique(['subscription_id','notification_id']);
  });
 }
 public function down(): void {
  Schema::dropIfExists('push_deliveries');
  Schema::table('push_subscriptions',function(Blueprint $t){$t->dropConstrainedForeignId('user_id');$t->dropColumn('feed_token_hash');});
  Schema::dropIfExists('owner_team_claims');Schema::dropIfExists('password_reset_tokens');Schema::dropIfExists('users');
 }
};
