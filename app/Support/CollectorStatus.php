<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CollectorStatus
{
    public const JOBS = [
        'ecfhl:refresh-player-projections'=>'projections', 'ecfhl:refresh-daily-players'=>'players',
        'ecfhl:refresh-starting-goalies'=>'goalies', 'ecfhl:refresh-pp-lines'=>'lines',
        'ecfhl:refresh-odds'=>'odds', 'ecfhl:refresh-fantasy-rosters'=>'teams',
        'ecfhl:refresh-live-scoring'=>'scores', 'ecfhl:refresh-current-standings'=>'standings',
        'ecfhl:refresh-lineup-advice'=>'advisor', 'ecfhl:refresh-player-birthdates'=>'birthdates',
        'ecfhl:refresh-scoring-period-matchups'=>'matchups',
    ];

    public static function start(string $key): void
    {
        if (!Schema::hasTable('collector_run_progress')) return;
        $total = match ($key) { 'players', 'teams', 'goalies', 'odds', 'matchups'=>2, 'scores', 'standings'=>3, 'lines'=>32, default=>null };
        DB::table('collector_run_progress')->updateOrInsert(['job_key'=>$key], [
            'completed'=>0, 'total'=>$total, 'started_at'=>now(), 'finished_at'=>null,
        ]);
        DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>$key], [
            'status'=>'running', 'message'=>'Collecting…', 'ran_at'=>now(), 'created_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public static function advance(string $key): void
    {
        if (Schema::hasTable('collector_run_progress')) DB::table('collector_run_progress')->where('job_key',$key)->increment('completed');
    }

    public static function total(string $key, int $total): void
    {
        if (Schema::hasTable('collector_run_progress')) DB::table('collector_run_progress')->where('job_key',$key)->update(['total'=>$total]);
    }

    public static function finish(string $key, int $exit): void
    {
        if (!Schema::hasTable('collector_run_progress')) return;
        DB::table('collector_run_progress')->where('job_key',$key)->update(['finished_at'=>now()]);
        // Keep detailed collector diagnostics, including partial failures.
        $row = DB::table('collector_job_statuses')->where('job_key',$key)->first();
        DB::table('collector_job_statuses')->where('job_key',$key)->update([
            'status'=>$exit === 0 ? 'success' : 'failed',
            'message'=>$row && $row->message !== 'Collecting…' ? $row->message : ($exit === 0 ? 'Completed' : 'Failed — check collector logs'),
            'ran_at'=>now(), 'updated_at'=>now(),
        ]);
    }

    public static function snapshot(): array
    {
        if (!Schema::hasTable('collector_job_statuses')) return [];
        $progress = Schema::hasTable('collector_run_progress') ? DB::table('collector_run_progress')->get()->keyBy('job_key') : collect();
        return DB::table('collector_job_statuses')->get()->mapWithKeys(function ($row) use ($progress) {
            $run = $progress[$row->job_key] ?? null;
            $status = $row->status;
            // A killed worker cannot leave the page claiming it is running forever.
            if ($status === 'running' && $run && \Carbon\CarbonImmutable::parse($run->started_at, config('app.timezone'))->lt(now()->subHours(2))) $status = 'interrupted';
            return [$row->job_key=>[
                'status'=>$status, 'message'=>match($status) {
                    'running'=>'Collecting…', 'success'=>'Completed', 'warning'=>'Completed with warnings',
                    'interrupted'=>'Interrupted — retry', default=>'Failed — open details',
                },
                'details'=>$row->message, 'completed'=>$run->completed ?? 0, 'total'=>$run->total ?? null,
                'updated_at'=>$row->ran_at ? \Carbon\CarbonImmutable::parse($row->ran_at, config('app.timezone'))->setTimezone('America/Halifax')->format('M j · g:i:s a T') : null,
            ]];
        })->all();
    }
}
