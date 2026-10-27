<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Bootstrap missing identity/history rows on a new installation.
        // The legacy export is clipped to 1000 rows in some tables; never
        // treat it as a complete historical restore.
        if (!Schema::hasTable('franchises')) return;
        $source = database_path('data/ecfhl_database.txt');
        if (!is_file($source)) throw new RuntimeException('Historical seed file missing');
        $contents = file_get_contents($source);
        foreach (['franchises', 'franchise_aliases'] as $table) {
            if (DB::table($table)->count() > 0) continue;
            if (!preg_match('/TAB NAME:\\s*'.preg_quote($table, '/').'>\\R(.*?)(?=<PARSED TEXT FOR SHEET:|\\z)/su', $contents, $match)) {
                throw new RuntimeException("Missing historical identity source: {$table}");
            }
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, $match[1]); rewind($stream);
            $headers = fgetcsv($stream);
            if (($headers[0] ?? null) === 'index') array_shift($headers);
            $rows = [];
            while (($values = fgetcsv($stream)) !== false) {
                if ($values === [null] || $values === []) continue;
                if (count($values) === count($headers) + 1) array_shift($values);
                if (count($values) !== count($headers)) throw new RuntimeException("Malformed identity row in {$table}");
                $rows[] = array_combine($headers, array_map(fn($v) => $v === '' ? null : ($v === 'True' ? 1 : ($v === 'False' ? 0 : $v)), $values));
            }
            fclose($stream);
            if (count($rows) < ($table === 'franchises' ? 21 : 10)) {
                throw new RuntimeException("Insufficient historical identity rows in {$table}");
            }
            foreach (array_chunk($rows, 100) as $batch) DB::table($table)->insertOrIgnore($batch);
        }
        if (!DB::table('franchises')->where('franchise_id', 'F012')->exists()) {
            throw new RuntimeException('Lone Tsar franchise F012 missing after identity bootstrap');
        }
    }
    public function down(): void {}
};
