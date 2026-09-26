<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prize_awards') || DB::table('prize_awards')->exists()) {
            return;
        }

        $path = database_path('data/ecfhl_database.txt');
        if (!is_file($path)) {
            return;
        }

        $text = file_get_contents($path);
        if (!preg_match('/TAB NAME:\s*prize_awards>\R(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/su', $text, $match)) {
            return;
        }

        $stream = fopen('php://temp', 'r+');
        fwrite($stream, $match[1]);
        rewind($stream);

        $headers = fgetcsv($stream);
        if (!$headers) {
            fclose($stream);
            return;
        }
        if (($headers[0] ?? null) === 'index') {
            array_shift($headers);
        }

        $batch = [];
        while (($row = fgetcsv($stream)) !== false) {
            if ($row === [null] || $row === []) continue;
            if (count($row) === count($headers) + 1) array_shift($row);
            if (count($row) !== count($headers)) continue;

            $record = [];
            foreach ($headers as $i => $header) {
                $value = $row[$i] ?? null;
                $record[$header] = ($value === '') ? null : $value;
            }
            $batch[] = $record;
        }
        fclose($stream);

        if ($batch) {
            DB::table('prize_awards')->insert($batch);
        }
    }

    public function down(): void
    {
        // Historical prize data is source data; do not delete it on rollback.
    }
};
