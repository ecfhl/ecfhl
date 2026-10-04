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
    foreach (ProjectionSettings::LABELS as $key => $_) $rules['weights.'.$key] = 'required|numeric|between:0,100|decimal:0,2';
    $validated = request()->validate($rules);
    $count = ProjectionSettings::save($validated['weights']);
    return redirect('/admin/projections')->with('notice', 'Projection weights saved. '.$count.' player projections recalculated.');
});
