<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::post('/job-status/run/{job}', function (string $job) {
    abort_unless(request()->ajax() && request()->headers->get('X-Requested-With') === 'XMLHttpRequest', 403);
    $commands = [
        'players' => 'ecfhl:refresh-daily-players',
        'goalies' => 'ecfhl:refresh-starting-goalies',
        'lines' => 'ecfhl:refresh-pp-lines',
        'odds' => 'ecfhl:refresh-odds',
        'teams' => 'ecfhl:refresh-fantasy-rosters',
        'scores' => 'ecfhl:refresh-daily-scores',
        'standings' => 'ecfhl:refresh-current-standings',
        'advisor' => 'ecfhl:refresh-lineup-advice',
    ];

    abort_unless(isset($commands[$job]) || $job === 'all', 404);

    $returnTo = in_array(request('return_to'), ['daily-targets','ai-tips'], true) ? '/daily-targets' : '/job-status';
    $jobsToRun = $job === 'all' ? $commands : [$job => $commands[$job]];
    $labels = ['players' => 'Fantrax players', 'goalies' => 'Starting goalies', 'lines' => 'Power-play lines', 'odds' => 'NHL odds', 'teams' => 'Fantasy team rosters', 'scores' => 'Live daily scores', 'standings' => 'Current standings', 'advisor' => 'Lineup Advisor'];
    $results = [];
    $details = [];
    $anyFailed = false;

    foreach ($jobsToRun as $jobKey => $command) {
        try {
            $exitCode = Artisan::call($command);
            $output = trim(Artisan::output());
            $outputFailed = (bool) preg_match('/(?:Could not parse|validation failed|no rendered matchup|\bfailed\b|\berror\b|SQLSTATE)/i', $output);
            $failed = $exitCode !== 0 || $outputFailed;
            $warning = ! $failed && (bool) preg_match('/(?:\bwarning\b|\bpartial\b|\bskipped\b|preserved|0 records updated)/i', $output);
            $status = $failed ? 'failed' : ($warning ? 'warning' : 'success');
            $anyFailed = $anyFailed || $failed;
            $result = $labels[$jobKey].': '.($failed ? 'Failed' : ($warning ? 'Warning' : 'Completed'));
            if ($output !== '') $result .= "\n".$output;
            $results[] = $result;
            $details[$jobKey] = ['failed'=>$failed, 'warning'=>$warning, 'status'=>$status, 'output'=>$output];
            DB::table('collector_job_statuses')->updateOrInsert(
                ['job_key'=>$jobKey],
                ['status'=>$status,'message'=>$output ?: $result,'ran_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
            );
        } catch (\Throwable $e) {
            report($e);
            $anyFailed = true;
            $results[] = $labels[$jobKey].': Failed' . "\n" . $e->getMessage();
            $details[$jobKey] = ['failed'=>true, 'warning'=>false, 'status'=>'failed', 'output'=>$e->getMessage()];
            DB::table('collector_job_statuses')->updateOrInsert(
                ['job_key'=>$jobKey],
                ['status'=>'failed','message'=>$e->getMessage(),'ran_at'=>now(),'updated_at'=>now(),'created_at'=>now()]
            );
        }
    }

    $message = implode("\n\n", $results);
    if ($job === 'all') $message .= "\n\n".($anyFailed ? 'One or more jobs failed.' : 'All 8 jobs completed.');
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
})->whereIn('job', ['players', 'goalies', 'lines', 'odds', 'teams', 'scores', 'standings', 'advisor', 'all']);
