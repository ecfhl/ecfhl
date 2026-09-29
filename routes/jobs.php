<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/job-status/run/{job}', function (string $job) {
    $commands = [
        'players' => 'ecfhl:refresh-daily-players',
        'goalies' => 'ecfhl:refresh-starting-goalies',
        'lines' => 'ecfhl:refresh-pp-lines',
    ];

    abort_unless(isset($commands[$job]) || $job === 'all', 404);

    $returnTo = request('return_to') === 'ai-tips' ? '/ai-tips' : '/job-status';
    $jobsToRun = $job === 'all' ? $commands : [$job => $commands[$job]];
    $labels = ['players' => 'Fantrax players', 'goalies' => 'Starting goalies', 'lines' => 'Power-play lines'];
    $results = [];
    $anyFailed = false;

    foreach ($jobsToRun as $jobKey => $command) {
        try {
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());
            $outputFailed = (bool) preg_match('/(?:Could not parse|validation failed|no rendered matchup|\bfailed\b|\berror\b|SQLSTATE)/i', $output);
            $failed = $exitCode !== 0 || $outputFailed;
            $anyFailed = $anyFailed || $failed;
            $result = $labels[$jobKey].': '.($failed ? 'Failed' : 'Completed');
            if ($output !== '') $result .= "\n".$output;
            $results[] = $result;
        } catch (\Throwable $e) {
            report($e);
            $anyFailed = true;
            $results[] = $labels[$jobKey].': Failed' . "\n" . $e->getMessage();
        }
    }

    $message = implode("\n\n", $results);
    if ($job === 'all') $message .= "\n\n".($anyFailed ? 'One or more jobs failed.' : 'All 3 jobs completed.');
    else $message .= "\n".($anyFailed ? 'Job failed.' : 'Job completed.');

    return redirect($returnTo)->with($anyFailed ? 'job_error' : 'job_success', $message);
})->whereIn('job', ['players', 'goalies', 'lines', 'all']);
