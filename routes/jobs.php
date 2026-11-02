<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Support\WebPush;

$sendTestScore = function (WebPush $webPush) {
    abort_unless(request()->ajax() && request()->headers->get('X-Requested-With') === 'XMLHttpRequest', 403);
    $hash=(string)request()->session()->get('push_endpoint_hash',request()->cookie('ecfhl_push_device',''));
    return response()->json($webPush->testLatestScore(request()->user()->id,$hash));
};
Route::post('/job-status/test-scoring-notification',$sendTestScore)->middleware('throttle:5,1,push-test');
// Support an already-open Collector Status page using the previous URL.
Route::post('/job-status/test-goalie-notification',$sendTestScore)->middleware('throttle:5,1,push-test');

Route::post('/job-status/run/{job}', function (string $job) {
    abort_unless(request()->ajax() && request()->headers->get('X-Requested-With') === 'XMLHttpRequest', 403);
    $catalog=\App\Support\CollectorSchedules::catalog();
    $commands=array_map(fn($job)=>$job['command'],$catalog);

    abort_unless(isset($commands[$job]) || $job === 'all', 404);

    $returnTo = in_array(request('return_to'), ['daily-targets','ai-tips'], true) ? '/players' : '/job-status';
    $jobsToRun = $job === 'all' ? array_intersect_key($commands,array_flip(\App\Support\CollectorSchedules::REFRESH_ALL)) : [$job => $commands[$job]];
    $labels=array_map(fn($job)=>$job['name'],$catalog);
    $results = [];
    $details = [];
    $anyFailed = false;

    foreach ($jobsToRun as $jobKey => $command) {
        try {
            $output='';
            $exitCode = app(\App\Support\CollectorRunner::class)->run($jobKey, function($message)use(&$output){$output=$message;}, $jobKey === 'projections' && $job !== 'all' ? ['--force'=>true] : []);
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
    if ($job === 'all') $message .= "\n\n".($anyFailed ? 'One or more jobs failed.' : 'All '.count($jobsToRun).' jobs completed.');
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
})->whereIn('job', array_merge(array_keys(\App\Support\CollectorSchedules::catalog()),['all']));

Route::post('/job-status/schedule/{job}', function(string $job){
    $catalog=\App\Support\CollectorSchedules::catalog();
    abort_unless(isset($catalog[$job]),404);
    $data=request()->validate([
        'frequency'=>['required',\Illuminate\Validation\Rule::in(\App\Support\CollectorSchedules::FREQUENCIES)],
        'time'=>'required|date_format:H:i',
        'weekday'=>'required|integer|between:0,6',
        'idle_minutes'=>'nullable|integer|in:1,2,5,10,15,30,60,120,360,720',
        'on_transaction'=>'sometimes|boolean',
    ]);
    $data['on_transaction']=in_array($job,['teams','players'],true)&&request()->boolean('on_transaction');
    $data['idle_minutes']=in_array($job,['scores','standings'],true)?(int)($data['idle_minutes']??60):null;
    abort_if(in_array($job,['scores','standings'],true)&&!in_array($data['frequency'],['1','2','5','10','15','30','60','120','360','720','off'],true),422,'Select an interval for live collectors.');
    DB::table('collector_schedules')->updateOrInsert(['job_key'=>$job],[
        'settings_json'=>json_encode($data,JSON_THROW_ON_ERROR),'created_at'=>now(),'updated_at'=>now(),
    ]);
    return redirect('/job-status#collector-'.$job)->with('job_success',$catalog[$job]['name'].' schedule saved.');
});
