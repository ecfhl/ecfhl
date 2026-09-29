<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/job-status/run/{job}', function (string $job) {
    $commands = [
        'players' => 'ecfhl:refresh-daily-players',
        'goalies' => 'ecfhl:refresh-starting-goalies',
        'lines' => 'ecfhl:refresh-pp-lines',
    ];

    abort_unless(isset($commands[$job]), 404);

    try {
        $exitCode = Artisan::call($commands[$job]);
        $output = trim(Artisan::output());

        return redirect('/job-status')->with(
            $exitCode === 0 ? 'job_success' : 'job_error',
            ($exitCode === 0 ? 'Job completed.' : 'Job failed.').($output !== '' ? ' '.$output : '')
        );
    } catch (\Throwable $e) {
        report($e);
        return redirect('/job-status')->with('job_error', 'Job failed: '.$e->getMessage());
    }
})->whereIn('job', ['players', 'goalies', 'lines']);
