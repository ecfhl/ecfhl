<?php
use App\Support\CollectorRunner;
use App\Support\CollectorSchedules;
use App\Support\WatchFantraxTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('ecfhl:collect {job}',function(CollectorRunner $runner){
    $key=(string)$this->argument('job');
    if(!isset(CollectorSchedules::catalog()[$key])){$this->error('Unknown collector.');return 1;}
    return $runner->run($key,fn($message)=>$this->line($message));
});
Artisan::command('ecfhl:watch-transactions',function(WatchFantraxTransactions $watcher){
    try{$watcher->refresh(fn($message)=>$this->line($message));return 0;}
    catch(\Throwable $e){report($e);$this->error($e->getMessage());return 1;}
});
foreach(CollectorSchedules::catalog() as $key=>$definition){
    // Resolve settings at execution time, so schedule:work needs no restart after a save.
    Schedule::command('ecfhl:collect '.$key)->everyMinute()->withoutOverlapping(match($key){'goalies'=>4,'scores','standings','transactions','birthdates'=>10,'teams','players'=>14,'lines'=>55,'odds'=>110,'projections'=>60,'available-goalies'=>50,default=>30})->runInBackground()
        ->when(fn()=>CollectorSchedules::due($key));
}
