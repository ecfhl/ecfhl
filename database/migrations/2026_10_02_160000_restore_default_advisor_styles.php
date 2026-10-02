<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('lineup_advisor_profiles')) return;

        $styles = [
            'mike' => "Here is the thing. {advice}\nListen, if you want to be a good pro, {advice_lower}\nAt the end of the day, {advice_lower}\nEvery day, it is process and structure. {advice}",
            'pierre' => "My understanding is that {advice_lower}\nChecking in on the options, {advice_lower}\nKeep an eye on this one. {advice}\nFrom what I am seeing, {advice_lower}",
            'john' => "Listen. {advice}\nHonestly, this is not complicated. {advice}\nAccountability. {advice}\nEarn it. {advice}",
        ];

        foreach ($styles as $key => $style) {
            DB::table('lineup_advisor_profiles')
                ->where('advisor_key', $key)
                ->update([
                    'style_text' => $style,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Intentionally left blank. This migration restores known-good advisor
        // styles after accidental manual edits.
    }
};
