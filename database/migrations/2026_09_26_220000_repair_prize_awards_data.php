<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('prize_awards')) {
            return;
        }

        $path = database_path('data/ecfhl_database.txt');
        if (!is_file($path)) {
            throw new RuntimeException('Missing database/data/ecfhl_database.txt');
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $inPrizeAwards = false;
        $headers = null;
        $rows = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            if ($trimmed === 'TAB NAME: prize_awards>') {
                $inPrizeAwards = true;
                $headers = null;
                continue;
            }

            if (!$inPrizeAwards) {
                continue;
            }

            if (str_starts_with($trimmed, '<PARSED TEXT FOR SHEET:')) {
                break;
            }

            if ($trimmed === '') {
                continue;
            }

            $values = str_getcsv($line);
            if ($headers === null) {
                $headers = $values;
                continue;
            }

            if (count($values) !== count($headers)) {
                continue;
            }

            $record = array_combine($headers, $values);
            if (!$record || empty($record['prize_award_id'])) {
                continue;
            }

            $rows[] = [
                'prize_award_id' => $record['prize_award_id'],
                'season_id' => $record['season_id'],
                'franchise_id' => $record['franchise_id'],
                'award_type_id' => $record['award_type_id'],
                'amount_cents' => (int) $record['amount_cents'],
                'source_id' => $record['source_id'],
            ];
        }

        if (!$rows) {
            throw new RuntimeException('No prize_awards rows parsed from ecfhl_database.txt');
        }

        DB::transaction(function () use ($rows) {
            DB::table('prize_awards')->delete();
            foreach (array_chunk($rows, 100) as $chunk) {
                DB::table('prize_awards')->insert($chunk);
            }
        });
    }

    public function down(): void
    {
        // Historical source data; no destructive rollback.
    }
};
