<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
 public function up(): void {
  DB::table('users')->where('messaging_persona','gary-betman')->update(['name'=>'Gary Bettman','updated_at'=>now()]);
 }
 public function down(): void {
  DB::table('users')->where('messaging_persona','gary-betman')->update(['name'=>'Gary Betman','updated_at'=>now()]);
 }
};
