<?php

use App\Support\FantasyDay;
use App\Support\LiveScoring\RefreshLiveScoring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:refresh-live-scoring {date?}', function (RefreshLiveScoring $refresh) {
    try {
        $base = $this->argument('date') ? (new FantasyDay)->parse($this->argument('date'))->toDateString() : (new FantasyDay)->today()->toDateString();
    } catch (\Throwable $e) {
        $this->error($e->getMessage());
        return 1;
    }
    // Shared across scheduled and HTTP/manual runs on this Railway replica.
    $path = storage_path('app/live-scoring.lock');
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    $lock = fopen($path, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        $this->warn('Live scoring refresh already running.');
        if ($lock) fclose($lock);
        return 1;
    }
    $messages = [];
    try {
        $ok = $refresh->refresh($base, function ($message) use (&$messages) { $messages[] = $message; $this->line($message); });
        DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>'scores'], ['status'=>$ok?'success':'failed','message'=>implode("\n",$messages),'ran_at'=>now(),'updated_at'=>now(),'created_at'=>now()]);
        return $ok ? 0 : 1;
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
})->purpose('Publish independent Fantrax yesterday/today/tomorrow snapshots using Pacific fantasy dates');

Schedule::command('ecfhl:refresh-live-scoring')->everyMinute()->withoutOverlapping(10)->runInBackground()
    ->when(fn()=>app(RefreshLiveScoring::class)->due());
