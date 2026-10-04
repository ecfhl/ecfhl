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
