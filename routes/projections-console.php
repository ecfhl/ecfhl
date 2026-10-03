<?php

use App\Support\RefreshPlayerProjections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:refresh-player-projections {--force : Regenerate even if already refreshed today}', function (RefreshPlayerProjections $refresh) {
    $path = storage_path('app/player-projections.lock');
    if (!is_dir(dirname($path))) mkdir(dirname($path), 0775, true);
    $lock = fopen($path, 'c');
    if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
        if ($lock) fclose($lock);
        $this->error('Player projection regeneration is already running.');
        return 1;
    }
    $messages = [];
    $ok = false;
    try {
        $refresh->refresh(CarbonImmutable::now('America/Halifax'), (bool)$this->option('force'), function ($message) use (&$messages) { $messages[] = $message; $this->line($message); });
        $ok = true;
        return 0;
    } catch (\Throwable $e) {
        report($e);
        $message = $e->getMessage().'. Existing projection data preserved.';
        $messages[] = $message;
        $this->error($message);
        return 1;
    } finally {
        DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>'projections'], ['status'=>$ok?'success':'failed', 'message'=>implode("\n", $messages), 'ran_at'=>now(), 'created_at'=>now(), 'updated_at'=>now()]);
        flock($lock, LOCK_UN);
        fclose($lock);
    }
})->purpose('Regenerate custom FPts/GP for the frozen top 1,000 Fantrax players');

Schedule::command('ecfhl:refresh-player-projections')->dailyAt('04:00')->timezone('America/Halifax')->withoutOverlapping(60)->runInBackground()
    ->onSuccess(fn()=>Artisan::call('ecfhl:refresh-lineup-advice'));
