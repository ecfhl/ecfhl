<?php
namespace App\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CollectorRunner
{
    public function run(string $key, ?callable $log=null, array $options=[]): int
    {
        $definition=CollectorSchedules::catalog()[$key]??throw new RuntimeException('Unknown collector.');
        $directory=storage_path('app');
        if(!is_dir($directory))mkdir($directory,0775,true);
        $lock=fopen($directory.'/collector-'.$key.'.lock','c');
        if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){
            if($lock)fclose($lock);
            if($log)$log('Collector already running; retry on the next check.');
            return 1;
        }
        $output='';$code=1;
        try{
            $options=array_replace(in_array($key,['projections','birthdates'],true)?['--force'=>true]:[],$options);
            $code=Artisan::call($definition['command'],$options);
            $output=trim(Artisan::output());
            if(preg_match('/(?:Could not parse|validation failed|\bfailed\b|\berror\b|SQLSTATE)/i',$output))$code=1;
        }catch(\Throwable $e){report($e);$output=$e->getMessage();}
        finally{
            try{
                DB::table('collector_job_statuses')->updateOrInsert(['job_key'=>$key],[
                    'status'=>$code===0?'success':'failed','message'=>$output,'ran_at'=>now(),'created_at'=>now(),'updated_at'=>now(),
                ]);
            }finally{flock($lock,LOCK_UN);fclose($lock);}
        }
        if($log)$log($output);
        return $code;
    }
}
