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
        $outputFailed = (bool) preg_match('/(?:Could not parse|validation failed|no rendered matchup|\bfailed\b|\berror\b|SQLSTATE)/i', $output);
        $failed = $exitCode !== 0 || $outputFailed;
        $result = $output !== '' ? $output."\n" : '';
        $result .= $failed ? 'Job failed.' : 'Job completed.';

        return redirect('/job-status')->with(
            $failed ? 'job_error' : 'job_success',
            $result
        );
    } catch (\Throwable $e) {
        report($e);
        return redirect('/job-status')->with('job_error', 'Job failed: '.$e->getMessage());
    }
})->whereIn('job', ['players', 'goalies', 'lines']);
