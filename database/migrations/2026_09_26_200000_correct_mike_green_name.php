<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('players')
            ->where('player_id', 'P0975')
            ->where('player_name', 'Matt Green')
            ->update(['player_name' => 'Mike Green']);
    }

    public function down(): void
    {
        DB::table('players')
            ->where('player_id', 'P0975')
            ->where('player_name', 'Mike Green')
            ->update(['player_name' => 'Matt Green']);
    }
};
