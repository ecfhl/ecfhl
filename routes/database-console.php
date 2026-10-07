<?php
use App\Support\DatabaseMaintenance;
use App\Support\DatabaseSpace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:database-maintenance {--report : Show table sizes without changing data}',function(DatabaseMaintenance $maintenance){
    if($this->option('report')){
        foreach($maintenance->report() as $table)$this->line(json_encode($table,JSON_THROW_ON_ERROR));
        return 0;
    }
    try{
        $maintenance->run(fn($message)=>$this->line($message));
        $this->info('Database maintenance complete; canonical scoring and league archives retained.');
        return 0;
    }catch(\Throwable $e){report($e);$this->error($e->getMessage());return 1;}
})->purpose('Compact duplicate sources and prune expired operational data');
Schedule::command('ecfhl:database-maintenance')->dailyAt('04:30')->timezone('America/Halifax')->withoutOverlapping(30)->runInBackground();

Artisan::command('ecfhl:database-reclaim {--once : Skip after the initial successful compaction}',function(DatabaseSpace $space){
    $job='ecfhl:database-reclaim-2026-10-07';
    if($this->option('once') && DB::table('job_run_history')->where('job_name',$job)->exists()){
        $this->info('Initial database compaction already complete.');return 0;
    }
    try{
        $result=$space->reclaim(fn($message)=>$this->line($message));
        if($result && !$result['tables_skipped'])DB::table('job_run_history')->insert(['job_name'=>$job,'rows_processed'=>$result['tables_rebuilt'],'completed_at'=>now(),'created_at'=>now(),'updated_at'=>now()]);
        $this->info('Database file compaction complete; application data retained.');return 0;
    }catch(\Throwable $e){report($e);$this->error($e->getMessage());return 1;}
})->purpose('Rebuild fragmented InnoDB files online to reclaim physical disk space');
