<?php

use App\Support\RefreshPlayerProjections;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:refresh-player-birthdates {--force : Refresh even when already collected this week}', function (\App\Support\PlayerBirthdates $birthdates) {
    try {
        $this->line($birthdates->refresh((bool)$this->option('force')).' NHL player birth dates available.');
        return 0;
    } catch(\Throwable $e) {
        report($e);
        $this->error('NHL birth date refresh failed; previous ages preserved.');
        return 1;
    }
})->purpose('Store NHL birth dates for locally calculated player ages');

Schedule::command('ecfhl:refresh-player-birthdates')->weeklyOn(1, '03:50')->timezone('America/Halifax')->withoutOverlapping(10)->runInBackground();

Artisan::command('ecfhl:refresh-player-projections {--force : Regenerate even if already refreshed today} {--ensure-season-stats : Collect complete season stats only if missing} {--ensure-projection-coverage : Collect missing rolling inputs for players outside the frozen baseline}', function (RefreshPlayerProjections $refresh) {
    if ($this->option('ensure-projection-coverage') && !DB::table('player_projections as p')->leftJoin('player_projection_baselines as b', 'b.player_id', '=', 'p.player_id')->whereNull('b.player_id')->where(fn($q)=>$q->whereNull('p.fpts_per_game_7d')->orWhereNull('p.fpts_per_game_14d')->orWhereNull('p.fpts_per_game_21d'))->exists()) {
        $this->line('Player projection coverage already available.');
        return 0;
    }
    $skaterColumns = json_decode(DB::table('season_player_stat_columns')->where('group', 'skater')->value('columns_json') ?? '{}', true);
    if ($this->option('ensure-season-stats') && isset($skaterColumns['SHG'])) {
        $this->line('Complete season player stats already available.');
        return 0;
    }
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
        $refresh->refresh(CarbonImmutable::now('America/Halifax'), (bool)($this->option('force') || $this->option('ensure-season-stats') || $this->option('ensure-projection-coverage')), function ($message) use (&$messages) { $messages[] = $message; $this->line($message); });
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
})->purpose('Regenerate custom FPts/GP for all collected players');

Schedule::command('ecfhl:refresh-player-projections')->dailyAt('04:00')->timezone('America/Halifax')->withoutOverlapping(60)->runInBackground();
