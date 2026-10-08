<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('chat_messages',function(Blueprint $t){
   $t->id();$t->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
   $t->foreignId('recipient_id')->nullable()->constrained('users')->cascadeOnDelete();
   $t->uuid('client_id');$t->text('body');$t->timestamps();
   $t->unique(['sender_id','client_id']);$t->index(['recipient_id','id']);$t->index(['sender_id','recipient_id','id']);
  });
  Schema::create('chat_reads',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
   $t->string('conversation',80);$t->unsignedBigInteger('last_message_id')->default(0);$t->timestamps();$t->unique(['user_id','conversation']);
  });
  Schema::create('owner_notification_inbox',function(Blueprint $t){
   $t->id();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();
   $t->foreignId('notification_id')->constrained('push_notifications')->cascadeOnDelete();
   $t->timestamp('read_at')->nullable();$t->timestamps();$t->unique(['user_id','notification_id']);$t->index(['user_id','read_at']);
  });
 }
 public function down(): void {Schema::dropIfExists('owner_notification_inbox');Schema::dropIfExists('chat_reads');Schema::dropIfExists('chat_messages');}
};
