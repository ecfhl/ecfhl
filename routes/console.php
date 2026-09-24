<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

Artisan::command('ecfhl:sync', function () {
    $sources = [
        'history' => 'https://ecfhl.win/data.json',
        'drafts' => 'https://ecfhl.win/draft-picks.json',
    ];

    foreach ($sources as $key => $url) {
        $response = Http::timeout(30)->retry(3, 1000)->get($url);
        $response->throw();

        DB::table('source_cache')->updateOrInsert(
            ['source_key' => $key],
            [
                'source_url' => $url,
                'payload' => $response->body(),
                'retrieved_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        $decoded = $response->json();

        if ($key === 'history') {
            Log::info('ECFHL history shape', [
                'league' => $decoded['league'] ?? null,
                'coverage' => $decoded['coverage'] ?? null,
                'seasons_count' => count($decoded['seasons'] ?? []),
                'seasons_sample' => array_slice($decoded['seasons'] ?? [], 0, 2),
                'team_seasons_count' => count($decoded['team_seasons'] ?? []),
                'team_seasons_sample' => array_slice($decoded['team_seasons'] ?? [], 0, 2),
                'team_summary_type' => gettype($decoded['team_summary'] ?? null),
                'team_summary_sample' => array_slice($decoded['team_summary'] ?? [], 0, 2, true),
                'trade_seasons' => array_keys($decoded['trades_by_season'] ?? []),
                'trade_sample' => array_slice($decoded['trades_by_season'] ?? [], 0, 1, true),
            ]);
        } else {
            Log::info('ECFHL drafts shape', [
                'seasons' => array_keys($decoded ?? []),
                'sample' => array_slice($decoded ?? [], 0, 1, true),
            ]);
        }

        $this->info("Synced {$key}");
    }
})->purpose('Import the current ECFHL history data into MySQL');
