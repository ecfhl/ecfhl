<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $databaseConnected = false;
    $databaseVersion = null;
    $databaseName = config('database.connections.mysql.database');

    try {
        $row = DB::selectOne('SELECT VERSION() AS version');
        $databaseVersion = $row->version ?? null;
        $databaseConnected = true;
    } catch (\Throwable $e) {
        report($e);
    }

    return view('home', compact('databaseConnected', 'databaseVersion', 'databaseName'));
});

Route::get('/source-shape', function () {
    $sources = DB::table('source_cache')->get()->mapWithKeys(function ($row) {
        $decoded = json_decode($row->payload, true);

        return [$row->source_key => [
            'retrieved_at' => $row->retrieved_at,
            'type' => gettype($decoded),
            'keys' => is_array($decoded) ? array_slice(array_keys($decoded), 0, 50) : [],
            'sample' => is_array($decoded) ? array_slice($decoded, 0, 1, true) : null,
        ]];
    });

    return response()->json($sources);
});
