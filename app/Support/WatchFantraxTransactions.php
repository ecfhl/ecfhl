<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class WatchFantraxTransactions
{
    public function __construct(private FantraxTransactions $source,private CollectorRunner $runner){}

    public function refresh(callable $log): void
    {
        $hashes=$this->source->fingerprints();
        $date=app(FantasyDay::class)->today()->toDateString();
        $settings=CollectorSchedules::all();
        $failed=false;
        foreach(['teams','players'] as $key){
            if(!$settings[$key]['on_transaction']||$settings[$key]['frequency']==='off')continue;
            // Lineup edits affect rosters, but do not change the free-agent pool.
            $relevant=$key==='teams'?$hashes:array_intersect_key($hashes,array_flip(['CLAIM_DROP','TRADE']));
            $fingerprint=hash('sha256',$date.'|'.json_encode($relevant));
            $stateKey='transactions-'.$key;
            if(DB::table('collector_state')->where('state_key',$stateKey)->value('value')===$fingerprint){$log($key.': no transaction changes.');continue;}
            $log($key.': transaction change or Pacific date rollover; refreshing.');
            if($this->runner->run($key,$log)!==0){$failed=true;continue;}
            // A failed or overlapping refresh must be retried, never acknowledged.
            DB::table('collector_state')->updateOrInsert(['state_key'=>$stateKey],['value'=>$fingerprint,'created_at'=>now(),'updated_at'=>now()]);
        }
        if($failed)throw new RuntimeException('Transaction-triggered refresh failed; will retry on the next check.');
        $log('Fantrax transaction check complete.');
    }
}
