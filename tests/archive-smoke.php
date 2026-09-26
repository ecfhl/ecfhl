<?php
/** Disposable SQLite smoke checks; never connects to the production database. */
putenv('DB_CONNECTION=sqlite');
putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array');
putenv('CACHE_STORE=array');
putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
Artisan::call('migrate',['--force'=>true]);
$text=file_get_contents(__DIR__.'/../database/data/ecfhl_database.txt');
preg_match_all('/TAB NAME:\s*([^>]+)>\R(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/su',$text,$matches,PREG_SET_ORDER);
foreach($matches as $match){
 $table=trim($match[1]);if(!Illuminate\Support\Facades\Schema::hasTable($table))continue;
 $stream=fopen('php://temp','r+');fwrite($stream,$match[2]);rewind($stream);$headers=fgetcsv($stream);array_shift($headers);
 while(($row=fgetcsv($stream))!==false){array_shift($row);if(count($row)!==count($headers))continue;
  $row=array_map(fn($v)=>$v===''?null:($v==='True'?1:($v==='False'?0:$v)),$row);
  DB::table($table)->insert(array_combine($headers,$row));
 }fclose($stream);
}
function check($condition,$message){if(!$condition)throw new RuntimeException($message);}
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
foreach(['h2h','total','all','none'] as $mode){
 foreach(['/','/seasons','/teams','/teams/F001','/trades','/draft','/prizes','/players?q=Sidney','/rules'] as $path){
  $app->forgetScopedInstances();
  $request=Request::create($path.(str_contains($path,'?')?'&':'?').'type='.$mode);
  $response=$kernel->handle($request);
  check($response->getStatusCode()===200,"$path ($mode): ".$response->getStatusCode().' '.substr(strip_tags($response->getContent()),0,700));
  if($path==='/rules'){check(!str_contains($response->getContent(),'season-type-choice"'),'Rules must not show slicer');check(str_contains($response->getContent(),'5 injured reserve'),'IR rule missing');}
  $kernel->terminate($request,$response);
 }
}
foreach(['/seasons/2025-26','/teams/F001'] as $path){
 $app->forgetScopedInstances();$request=Request::create($path.'?type=all');$response=$kernel->handle($request);
 check($response->getStatusCode()===200,"Detail failed $path: ".$response->getStatusCode());
}
$app->forgetScopedInstances();$app->instance('request',Request::create('/?type=all'));
$data=$app->make(App\Support\Archive::class);
check(count($data->seasons())===19,'Missing seasons');
foreach($data->teamSeasons() as $r)check($r['team']===$r['original_name'],'Historical names not used');
check(count($data->overviewLeaders()['championships'])>3,'Leaders are truncated');
check(count($data->awardEvents())>0,'No awards');
check(count($data->playerHistory('Sidney'))>0,'Player search is empty');
$events=$data->playerHistory('Sidney');$sorted=$events;usort($sorted,fn($a,$b)=>strcmp($a['sort'],$b['sort']));check($events===$sorted,'Player chronology wrong');
$app->forgetScopedInstances();$app->instance('request',Request::create('/','GET',[],['ecfhl-season-type'=>'total']));
$data=$app->make(App\Support\Archive::class);check($data->mode()==='total','Cookie not restored');
foreach($data->seasons() as $s)check(stripos($s['format'],'head')===false,'H2H leaked into total points');
Artisan::call('view:cache');
echo "Archive smoke checks passed: 38 page renders, filtering, names, leaders, chronology, cookie persistence, and Blade compilation.\n";

require __DIR__.'/trade-contracts.php';

