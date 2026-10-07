<?php
use App\Support\DatabaseMaintenance;
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
