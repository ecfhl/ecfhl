<?php

use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // A blank hosting database receives 2026-27 schema/teams before the
        // historical dataset. Recover missing history without ever truncating
        // current-season rows, user accounts, or collector state.
        if (!Schema::hasTable('franchises') || !Schema::hasTable('draft_picks')) {
            return;
        }
        if (DB::table('franchises')->where('franchise_id', 'F012')->exists()
            && DB::table('draft_picks')->count() >= 2103) {
            return;
        }
        (new DatabaseSeeder())->run(true);
    }

    public function down(): void
    {
        // Historical archive restoration is intentionally irreversible.
    }
};
