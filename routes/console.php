<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

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

        $this->info("Synced {$key}");
    }
})->purpose('Import the current ECFHL history data into MySQL');
