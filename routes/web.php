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
