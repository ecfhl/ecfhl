<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

Route::post('/job-status/run/{job}', function (string $job) {
    $commands = [
        'players' => 'ecfhl:refresh-daily-players',
        'goalies' => 'ecfhl:refresh-starting-goalies',
        'lines' => 'ecfhl:refresh-pp-lines',
        'odds' => 'ecfhl:refresh-odds',
    ];

    abort_unless(isset($commands[$job]) || $job === 'all', 404);

    $returnTo = in_array(request('return_to'), ['daily-targets','ai-tips'], true) ? '/daily-targets' : '/job-status';
    $jobsToRun = $job === 'all' ? $commands : [$job => $commands[$job]];
    $labels = ['players' => 'Fantrax players', 'goalies' => 'Starting goalies', 'lines' => 'Power-play lines', 'odds' => 'NHL odds'];
    $results = [];
    $details = [];
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
            $details[$jobKey] = ['failed'=>$failed, 'output'=>$output];
        } catch (\Throwable $e) {
            report($e);
            $anyFailed = true;
            $results[] = $labels[$jobKey].': Failed' . "\n" . $e->getMessage();
            $details[$jobKey] = ['failed'=>true, 'output'=>$e->getMessage()];
        }
    }

    $message = implode("\n\n", $results);
    if ($job === 'all') $message .= "\n\n".($anyFailed ? 'One or more jobs failed.' : 'All 4 jobs completed.');
    else $message .= "\n".($anyFailed ? 'Job failed.' : 'Job completed.');

    if (request()->expectsJson()) {
        return response()->json([
            'ok' => ! $anyFailed,
            'job' => $job,
            'message' => $message,
            'details' => $details,
        ], $anyFailed ? 500 : 200);
    }

    return redirect($returnTo)->with($anyFailed ? 'job_error' : 'job_success', $message);
})->whereIn('job', ['players', 'goalies', 'lines', 'odds', 'all']);
