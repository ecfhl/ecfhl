<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    private array $allowed = [
        'seasons','franchises','franchise_aliases','season_members','team_seasons','players','drafts','draft_picks',
        'trades','trade_assets','award_types','awards','playoff_rounds','playoff_games','playoff_winners',
        'prize_awards','season_prizes','rules'
    ];

    public function run(): void
    {
        $path = database_path('data/ecfhl_database.txt');
        if (!is_file($path)) {
            return;
        }

        $text = file_get_contents($path);
        preg_match_all('/<PARSED TEXT FOR SHEET:\s*\d+\s*\/\s*\d+\s*TABS\s*\nTAB NAME:\s*([^>]+)>\n(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/s', $text, $matches, PREG_SET_ORDER);

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        foreach ($matches as $match) {
            $table = trim($match[1]);
            if (!in_array($table, $this->allowed, true) || !Schema::hasTable($table)) {
                continue;
            }

            $lines = preg_split('/\r?\n/', trim($match[2]));
            if (!$lines) {
                continue;
            }

            $headers = str_getcsv(array_shift($lines));
            if (($headers[0] ?? null) === 'index') {
                array_shift($headers);
            }

            DB::table($table)->truncate();
            $batch = [];

            foreach ($lines as $line) {
                if (trim($line) === '') {
                    continue;
                }

                $row = str_getcsv($line);
                if (count($row) === count($headers) + 1) {
                    array_shift($row);
                }

                if (count($row) !== count($headers)) {
                    continue;
                }

                $record = [];
                foreach ($headers as $i => $header) {
                    $record[$header] = $this->value($row[$i] ?? null);
                }

                $batch[] = $record;

                if (count($batch) >= 250) {
                    DB::table($table)->insert($batch);
                    $batch = [];
                }
            }

            if ($batch) {
                DB::table($table)->insert($batch);
            }
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=1');
    }

    private function value($value)
    {
        if ($value === null || $value === '') {
            return null;
        }

        if ($value === 'True') {
            return 1;
        }

        if ($value === 'False') {
            return 0;
        }

        return $value;
    }
}
