<?php

use App\Support\ProjectionSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/admin/projections', function () {
    $weights = ProjectionSettings::weights();
    $labels = ProjectionSettings::LABELS;
    $count = DB::table('player_projections')->count();
    return view('admin.projections', compact('weights', 'labels', 'count'));
});

Route::post('/admin/projections', function () {
    $rules = [];
    $sliders = request()->has('units');
    foreach (ProjectionSettings::LABELS as $key => $_) $rules[($sliders ? 'units.' : 'weights.').$key] = $sliders
        ? 'required|numeric|between:0,10|decimal:0,1' : 'required|numeric|between:0,100|decimal:0,2';
    $validated = request()->validate($rules);
    $weights = $sliders ? array_map(fn($units)=>(float)$units * 10, $validated['units']) : $validated['weights'];
    $count = ProjectionSettings::save($weights);
    return redirect('/admin/projections')->with('notice', 'Projection weights saved. '.$count.' player projections recalculated.');
});
