<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
 public function up(): void {
  Schema::create('chat_reactions',function(Blueprint $t){$t->id();$t->foreignId('message_id')->constrained('chat_messages')->cascadeOnDelete();$t->foreignId('user_id')->constrained('users')->cascadeOnDelete();$t->timestamps();$t->unique(['message_id','user_id']);});
  Schema::create('chat_attachments',function(Blueprint $t){$t->id();$t->foreignId('message_id')->unique()->constrained('chat_messages')->cascadeOnDelete();$t->string('mime',32);$t->string('sha256',64);$t->longText('data');});
 }
 public function down(): void {Schema::dropIfExists('chat_attachments');Schema::dropIfExists('chat_reactions');}
};
