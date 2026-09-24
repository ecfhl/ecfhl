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
        $keys = is_array($decoded) ? array_slice(array_keys($decoded), 0, 30) : [];
        $sample = is_array($decoded) ? array_slice($decoded, 0, 1, true) : null;

        Log::info('ECFHL source shape', [
            'source' => $key,
            'keys' => $keys,
            'sample' => $sample,
        ]);

        $this->info("Synced {$key}");
    }
})->purpose('Import the current ECFHL history data into MySQL');
