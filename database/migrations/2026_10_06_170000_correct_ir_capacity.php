<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('rules')) return;
        DB::table('rules')->where('rule_id','RULE009')->update(['rule_text'=>'Teams have five separate Injury Reserve (IR) slots. Players in IR do not count toward the 21-player roster limit.']);
    }
    public function down(): void
    {
        if (!Schema::hasTable('rules')) return;
        DB::table('rules')->where('rule_id','RULE009')->update(['rule_text'=>'The league does not use separate Injury Reserve roster slots. Teams must remain within the legal roster limit when adding or dropping players.']);
    }
};
