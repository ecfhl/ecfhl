<?php

use Illuminate\Support\Facades\Route;

// Keep bookmarked pickup links useful after consolidating into Players.
$playersRedirect = function () {
    $today = app(\App\Support\FantasyDay::class)->today();
    $playing = request('date') === $today->addDay()->toDateString() ? 'tomorrow' : 'today';
    return redirect('/players?'.http_build_query(['availability'=>'available', 'playing'=>$playing, 'dfo_sort'=>'1', 'sort'=>'ec_proj', 'direction'=>'desc']), 301);
};
Route::get('/daily-targets', $playersRedirect);
Route::get('/ai-tips', $playersRedirect);
