<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

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
            throw new RuntimeException("ECFHL seed file not found: {$path}");
        }

        $text = file_get_contents($path);

        preg_match_all(
            '/<PARSED TEXT FOR SHEET:\s*\d+\s*\/\s*\d+\s*TABS\s*\R' .
            'TAB NAME:\s*([^>]+)>\R(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/su',
            $text,
            $matches,
            PREG_SET_ORDER
        );

        if (!$matches) {
            throw new RuntimeException('No ECFHL spreadsheet sections were detected in the seed file.');
        }

        DB::statement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($matches as $match) {
                $table = trim($match[1]);

                if (!in_array($table, $this->allowed, true) || !Schema::hasTable($table)) {
                    continue;
                }

                $stream = fopen('php://temp', 'r+');
                fwrite($stream, $match[2]);
                rewind($stream);

                $headers = fgetcsv($stream);
                if (!$headers) {
                    fclose($stream);
                    continue;
                }

                if (($headers[0] ?? null) === 'index') {
                    array_shift($headers);
                }

                DB::table($table)->truncate();
                $batch = [];

                while (($row = fgetcsv($stream)) !== false) {
                    if ($row === [null] || $row === []) {
                        continue;
                    }

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

                fclose($stream);

                if ($batch) {
                    DB::table($table)->insert($batch);
                }

                $count = DB::table($table)->count();
                $this->command?->info("Seeded {$table}: {$count} rows");
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
        }

        $required = [
            'seasons' => 19,
            'franchises' => 21,
            'team_seasons' => 218,
            'players' => 975,
            'draft_picks' => 2103,
            'trades' => 482,
            'trade_assets' => 1881,
        ];

        foreach ($required as $table => $minimum) {
            $count = DB::table($table)->count();
            if ($count < $minimum) {
                throw new RuntimeException("Seed validation failed for {$table}: expected at least {$minimum}, got {$count}.");
            }
        }
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
