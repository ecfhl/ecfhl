<?php
// Reuse the isolated authenticated HTTP/database harness; no production writes.
require __DIR__.'/owner-accounts.php';
use App\Support\CollectorSchedules;
use App\Support\CollectorRunner;
use App\Support\FantraxTransactions;
use App\Support\WatchFantraxTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Carbon\CarbonImmutable;

function checkControl($condition,$message){if(!$condition)throw new RuntimeException($message);}
$payload=json_decode(file_get_contents(__DIR__.'/fixtures/fantrax-transactions.json'),true);
$source=new FantraxTransactions;
$hashes=$source->parse($payload);
$news=$payload;$news['responses'][0]['data']['table']['rows'][0]['scorer']['icons']=[['tooltip'=>'New injury update']];
checkControl($source->parse($news)===$hashes,'Player news triggered a false transaction');
$changed=$payload;$changed['responses'][0]['data']['table']['rows'][0]['txSetId']='new-transaction';
checkControl($source->parse($changed)!==$hashes,'New transaction was ignored');
foreach([[],['pageError'=>['text'=>'Login required']]] as $bad){try{$source->parse($bad);throw new RuntimeException('Malformed feed accepted');}catch(RuntimeException $e){checkControl($e->getMessage()!=='Malformed feed accepted','Malformed feed accepted');}}
$empty=$payload;foreach($empty['responses'] as &$r)$r['data']['table']['rows']=[];unset($r);
checkControl(count($source->parse($empty))===3,'Valid empty history rejected');
Http::fake(['www.fantrax.com/fxpa/req*'=>Http::response($payload)]);
checkControl($source->fingerprints()===$hashes,'HTTP transaction reader changed valid data');
checkControl(Http::recorded(fn($r)=>$r->method()==='POST'&&count($r['msgs'])===3&&$r['msgs'][2]['data']['view']==='LINEUP_CHANGE'&&$r['msgs'][0]['data']['executedOnly']===true)->count()===1,'Incorrect transaction request');

$fakeSource=new class extends FantraxTransactions{public array $hashes=[];public function fingerprints():array{return $this->hashes;}};
$fakeSource->hashes=$hashes;
$runner=new class extends CollectorRunner{public array $calls=[];public bool $fail=false;public function run(string $key,?callable $log=null,array $options=[]):int{$this->calls[]=$key;return $this->fail?1:0;}};
$watcher=new WatchFantraxTransactions($fakeSource,$runner);$log=fn($message)=>null;
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-02T23:58:00-08:00'));
$watcher->refresh($log);checkControl($runner->calls===['teams','players'],'Initial transaction baseline did not refresh both datasets');
$runner->calls=[];$watcher->refresh($log);checkControl(!$runner->calls,'Unchanged transactions repeated heavy refreshes');
$fakeSource->hashes['LINEUP_CHANGE']='new-lineup';$watcher->refresh($log);checkControl($runner->calls===['teams'],'Lineup change did not isolate roster refresh');
$runner->calls=[];$fakeSource->hashes['TRADE']='new-trade';$runner->fail=true;
try{$watcher->refresh($log);}catch(RuntimeException $e){}
$runner->calls=[];$runner->fail=false;$watcher->refresh($log);checkControl($runner->calls===['teams','players'],'Failed changes were acknowledged instead of retried');
$runner->calls=[];CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-03T00:01:00-08:00'));$watcher->refresh($log);
checkControl($runner->calls===['teams','players'],'Pacific midnight did not refresh dated datasets');
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-03T00:30:00-03:00'));

$body=['frequency'=>'10','time'=>'05:15','weekday'=>2,'on_transaction'=>1];
checkControl(ownerRequest('POST','/job-status/schedule/teams',$body,$guest)->getStatusCode()===401,'Guest changed collector settings');
checkControl(ownerRequest('POST','/job-status/schedule/teams',$body,$alpha)->getStatusCode()===403,'Owner changed collector settings');
checkControl(ownerRequest('POST','/job-status/schedule/teams',$body,$admin)->getStatusCode()===302,'Admin could not save schedule');
checkControl(CollectorSchedules::all()['teams']['frequency']==='10','Saved frequency not used');
checkControl(CollectorSchedules::due('teams',CarbonImmutable::parse('2026-11-02T12:10:00-04:00'))&&!CollectorSchedules::due('teams',CarbonImmutable::parse('2026-11-02T12:11:00-04:00')),'Saved schedule timing is wrong');
checkControl(ownerRequest('POST','/job-status/schedule/teams',array_replace($body,['frequency'=>'arbitrary-command']),$admin)->getStatusCode()===422,'Invalid schedule accepted');
checkControl(ownerRequest('POST','/job-status/schedule/scores',array_replace($body,['frequency'=>'daily']),$admin)->getStatusCode()===422,'Unsupported live interval accepted');
checkControl(ownerRequest('POST','/job-status/schedule/teams',array_replace($body,['frequency'=>'off']),$admin)->getStatusCode()===302,'Could not pause collector');
$runner->calls=[];$fakeSource->hashes['TRADE']='another-trade';$watcher->refresh($log);checkControl($runner->calls===['players'],'Paused collector ran after transaction');
checkControl(!CollectorSchedules::due('teams'),'Paused collector scheduled');
$lock=fopen(storage_path('app/collector-odds.lock'),'c');flock($lock,LOCK_EX|LOCK_NB);
checkControl((new CollectorRunner)->run('odds')===1,'Overlapping collector run was allowed');flock($lock,LOCK_UN);fclose($lock);
$app->instance(CollectorRunner::class,$runner);$runner->calls=[];
checkControl(ownerRequest('POST','/job-status/run/teams',[],$admin,['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'])->getStatusCode()===200,'Paused collector could not run manually');
checkControl($runner->calls===['teams'],'Manual run bypassed shared collector runner');
checkControl(!isset(Artisan::all()['ecfhl:refresh-lineup-advice']),'Advisor command remains registered');
$events=$app->make(Illuminate\Console\Scheduling\Schedule::class)->events();
checkControl(count($events)===count(CollectorSchedules::catalog()),'Missing or duplicate collector schedules');
foreach($events as $event)checkControl($event->runInBackground&&$event->withoutOverlapping,'Scheduled collector lacks overlap protection');
// Admin page must render actual settings, while retired advisor pages return 404.
$page=ownerRequest('GET','/job-status',[],$admin,['HTTP_ACCEPT'=>'text/html']);
checkControl($page->getStatusCode()===200,'Collector settings page failed: '.substr(strip_tags($page->getContent()),0,500));
checkControl(str_contains($page->getContent(),'/job-status/schedule/teams')&&str_contains($page->getContent(),'Fantrax Transactions')&&!str_contains($page->getContent(),'Lineup Advisor'),'Collector controls or advisor removal missing');
checkControl(ownerRequest('GET','/admin/advisors',[],$admin)->getStatusCode()===404,'Retired advisor administration still available');
echo "Collector checks passed: validated admin controls, dynamic timing, pause/manual behavior, overlap locks, transaction deduplication, lineup-only changes, retry after failure, Pacific rollover, and advisor removal.\n";
