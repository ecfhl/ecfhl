<?php
use App\Support\TeamImages;
use Illuminate\Support\Facades\Route;

// Public image reads do not start or write a session. Uploads stay in web/admin middleware.
Route::get('/team-icons/{slug}/thumbnail', fn(string $slug)=>TeamImages::response(request(), $slug, max(24,min(640,(int)request('size',64)))))
    ->where('slug','[A-Za-z0-9-]+');
Route::get('/team-icons/{slug}', fn(string $slug)=>TeamImages::response(request(), $slug))
    ->where('slug','[A-Za-z0-9-]+');
