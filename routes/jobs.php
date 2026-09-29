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
        // Some collector commands historically caught per-source exceptions and
        // returned 0. Never paint those runs green just because Artisan exited 0.
        $outputFailed = (bool) preg_match('/(?:Could not parse|validation failed|no rendered matchup|\bfailed\b|\berror\b|SQLSTATE)/i', $output);
        $failed = $exitCode !== 0 || $outputFailed;

        return redirect('/job-status')->with(
            $failed ? 'job_error' : 'job_success',
            ($failed ? 'Job failed.' : 'Job completed.').($output !== '' ? ' '.$output : '')
        );
    } catch (\Throwable $e) {
        report($e);
        return redirect('/job-status')->with('job_error', 'Job failed: '.$e->getMessage());
    }
})->whereIn('job', ['players', 'goalies', 'lines']);
