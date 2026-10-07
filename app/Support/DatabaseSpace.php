<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Return unused file-per-table space to disk without deleting application data. */
final class DatabaseSpace
{
    public function reclaim(?callable $log=null): array
    {
        $db=DB::connection();
        if($db->getDriverName()!=='mysql')return [];
        $lock='ecfhl-reclaim-'.substr(hash('sha256',$db->getDatabaseName()),0,24);
        if((int)$db->selectOne('SELECT GET_LOCK(?, 0) AS acquired',[$lock])->acquired!==1)throw new \RuntimeException('Database compaction is already running.');
        $settings=null;
        try{
            $settings=$db->selectOne('SELECT @@SESSION.lock_wait_timeout AS lock_timeout, @@SESSION.information_schema_stats_expiry AS stats_expiry');
            $db->statement('SET SESSION lock_wait_timeout = 5');
            $db->statement('SET SESSION information_schema_stats_expiry = 0');
            $sql="SELECT t.TABLE_NAME AS name, t.DATA_FREE AS reusable_bytes, s.FILE_SIZE AS file_bytes, s.ALLOCATED_SIZE AS allocated_bytes
                FROM information_schema.TABLES t JOIN information_schema.INNODB_TABLESPACES s ON s.NAME = CONCAT(t.TABLE_SCHEMA, '/', t.TABLE_NAME)
                WHERE t.TABLE_SCHEMA = DATABASE() AND t.ENGINE = 'InnoDB' AND s.SPACE_TYPE = 'Single' ORDER BY s.FILE_SIZE ASC";
            $before=$db->select($sql);
            if(!$before)throw new \RuntimeException('No individual InnoDB table files were available to measure; compaction was not run.');
            $rebuilt=0;$failed=[];$skipped=0;
            foreach($before as $table){
                if((int)$table->reusable_bytes<1048576)continue;
                // Keep temporary rebuilds bounded on the 500 MB volume; smaller tables free room first.
                if((int)$table->file_bytes>67108864 || !preg_match('/^[A-Za-z0-9_]+$/D',$table->name)){
                    $skipped++;if($log)$log('Compaction skipped oversized table: '.$table->name);continue;
                }
                try{
                    if($log)$log('Compacting '.$table->name.'; file bytes before: '.$table->file_bytes);
                    // Explicit NONE prevents a fallback to an operation that blocks normal reads/writes.
                    $db->statement('ALTER TABLE '.$db->getQueryGrammar()->wrapTable($table->name).' FORCE, ALGORITHM=INPLACE, LOCK=NONE');
                    $rebuilt++;
                }catch(\Throwable $e){
                    $failed[]=$table->name;if($log)$log('Compaction deferred for '.$table->name.': '.$e->getMessage());
                }
            }
            $after=$db->select($sql);
            $sum=fn($rows,$key)=>array_sum(array_map(fn($r)=>(int)$r->$key,$rows));
            $result=['tables_rebuilt'=>$rebuilt,'tables_skipped'=>$skipped,
                'file_bytes_before'=>$sum($before,'file_bytes'),'file_bytes_after'=>$sum($after,'file_bytes'),
                'allocated_bytes_before'=>$sum($before,'allocated_bytes'),'allocated_bytes_after'=>$sum($after,'allocated_bytes')];
            $result['allocated_bytes_reclaimed']=max(0,$result['allocated_bytes_before']-$result['allocated_bytes_after']);
            foreach($result as $key=>$value)if($log)$log($key.': '.$value);
            if($failed)throw new \RuntimeException('Compaction needs a retry for: '.implode(', ',$failed));
            return $result;
        }finally{
            try{
                if($settings){
                    $db->statement('SET SESSION lock_wait_timeout = '.(int)$settings->lock_timeout);
                    $db->statement('SET SESSION information_schema_stats_expiry = '.(int)$settings->stats_expiry);
                }
            }finally{$db->selectOne('SELECT RELEASE_LOCK(?) AS released',[$lock]);}
        }
    }
}
