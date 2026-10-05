<?php

use App\Support\SeasonPlayers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/players', function (Request $request, SeasonPlayers $service) {
    $data = $service->data($request);
    if ($request->expectsJson()) return response()->json([
        'html'=>view('partials.season-player-rows', $data)->render(),
        'next_url'=>$data['players']->nextPageUrl(),
        'shown'=>$data['players']->lastItem() ?? 0,
        'total'=>$data['players']->total(),
    ])->header('Cache-Control', 'private, no-store');
    return view('season-players', $data);
});

Route::get('/players/{playerId}', function (Request $request, string $playerId, \App\Support\PlayerProfile $service) {
    $data=$service->data($playerId);
    if ($request->expectsJson()) return response()->json(['html'=>view('partials.player-profile',$data)->render()])->header('Cache-Control','private, no-store');
    return view('player-profile',$data);
})->where('playerId','[A-Za-z0-9_-]+');
