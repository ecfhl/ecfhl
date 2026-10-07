<?php
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE=:memory:');putenv('SESSION_DRIVER=array');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Carbon\CarbonImmutable;
function checkMaintenance($ok,$message){if(!$ok)throw new RuntimeException($message);}
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
foreach(glob(__DIR__.'/../database/migrations/*create*.php') as $file)(require $file)->up();
(require __DIR__.'/../database/migrations/2026_10_03_210000_add_season_actuals_to_player_projections.php')->up();
$indexMigration=require __DIR__.'/../database/migrations/2026_10_07_224500_add_database_performance_indexes.php';
$indexMigration->up();
checkMaintenance(Schema::hasIndex('active_fantasy_rosters','rosters_day_player') && Schema::hasIndex('season_player_stats','season_players_points'),'Hot path indexes must be created');
CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-07T19:00:00-03:00'));
foreach(['played'=>2,'listed'=>0,'unused'=>0,'same-name-other-team'=>0] as $id=>$gp){
 DB::table('season_player_stats')->insert(['player_id'=>$id,'season_id'=>'2026-27','player_name'=>$id==='same-name-other-team'?'listed':$id,'nhl_team'=>$id==='same-name-other-team'?'BOS':'MTL','position'=>'F','season_fpts'=>0,'season_gp'=>$gp,'season_fpts_per_game'=>0,'stats_json'=>'{}','stats_through'=>'2026-10-07','refreshed_at'=>now()]);
}
DB::table('active_line_combinations')->insert(['team'=>'MTL','player_name'=>'listed','position_group'=>'F','line_number'=>1,'source_url'=>'https://www.dailyfaceoff.com','last_update'=>now()]);
$baseline=['player_id'=>'frozen','season_id'=>'2026-27','player_name'=>'Frozen Player','nhl_team'=>'MTL','position'=>'F','source_rank'=>1,'fantrax_fpts_per_game'=>2,'fantrax_season_fpts'=>164,'season_start'=>'2026-09-29','captured_at'=>now()];
DB::table('player_projection_baselines')->insert($baseline);
DB::table('player_projections')->insert(['player_id'=>'frozen','as_of_date'=>'2026-10-07','window_end_date'=>'2026-10-07','season_gp'=>0,'season_fpts'=>0,'season_fpts_per_game'=>0,'projected_fpts_per_game'=>2,'refreshed_at'=>now()]+array_fill_keys(['fpts_7d','gp_7d','fpts_per_game_7d','fpts_14d','gp_14d','fpts_per_game_14d','fpts_21d','gp_21d','fpts_per_game_21d'],0));
$raw=json_encode(['day'=>['redundant'=>str_repeat('x',20000)],'details'=>str_repeat('y',10000)]);
$canonical=json_encode(['fantasy_date'=>'2026-09-01','players'=>[['player_id'=>'played','stats'=>['G'=>['value'=>2]]]],'matchups'=>[['home'=>'a','away'=>'b']]]);
DB::table('live_scoring_snapshots')->insert(['league_id'=>'092zcn40molvao69','fantasy_date'=>'2026-09-01','source_date'=>'2026-09-01','source'=>'fantrax','source_payload'=>$raw,'payload'=>$canonical,'player_count'=>1,'collected_at'=>now()]);
foreach(['2026-09-01','2026-09-29','2026-09-30','2026-10-07','2026-10-08'] as $day)DB::table('active_daily_players')->insert(['game_date'=>$day,'player_name'=>'Daily '.$day,'team'=>'MTL','availability'=>'FA']);
// Preserve last collected days when a collector has been down for longer than the retention window.
foreach(['2026-09-01','2026-09-06','2026-09-07','2026-09-08'] as $day)DB::table('active_fantasy_rosters')->insert(['game_date'=>$day,'fantasy_team_id'=>'a','fantasy_team_name'=>'Alpha','player_id'=>'id-'.$day,'player_name'=>'Roster']);
DB::table('sessions')->insert([['id'=>'expired','payload'=>'x','last_activity'=>now()->subDays(8)->timestamp],['id'=>'active','payload'=>'x','last_activity'=>now()->timestamp]]);
foreach([['live','2026-08-01'],['live','2026-09-01'],['standings','2026-08-01']] as [$job,$day])DB::table('job_run_history')->insert(['job_name'=>$job,'target_date'=>$day,'completed_at'=>$day.' 00:00:00']);
DB::table('seasons')->insert(['season_id'=>'archive','season_name'=>'Old Season']);
DB::table('historical_player_stats')->insert(['season_id'=>'2025-26','player_id'=>'played','fpts'=>22,'gp'=>10,'fpts_per_game'=>2.2,'stats_json'=>'{}']);
$frozen=DB::table('player_projection_baselines')->get()->toJson();
$archive=DB::table('historical_player_stats')->get()->toJson();
$maintenance=new App\Support\DatabaseMaintenance;$result=$maintenance->run();
checkMaintenance($result['zero_game_stats']===2 && DB::table('season_player_stats')->count()===2 && DB::table('season_player_stats')->where('player_id','listed')->exists(),'Remove unused zero-game stats; retain played players and team-specific DFO exceptions');
checkMaintenance(DB::table('live_scoring_snapshots')->value('payload')===$canonical,'Canonical historical scores must remain byte-for-byte intact');
$summary=json_decode(DB::table('live_scoring_snapshots')->value('source_payload'),true);
checkMaintenance($summary['compact_version']===1 && strlen(json_encode($summary))<200,'Duplicate rich source response must become a small audit summary');
checkMaintenance(DB::table('active_daily_players')->orderBy('game_date')->pluck('game_date')->all()===['2026-09-30','2026-10-07','2026-10-08'],'Retain last seven days and future records using the Pacific cutoff');
checkMaintenance(DB::table('active_fantasy_rosters')->orderBy('game_date')->pluck('game_date')->all()===['2026-09-06','2026-09-07','2026-09-08'],'An upstream outage must retain the three most recent working days');
checkMaintenance(DB::table('sessions')->pluck('id')->all()===['active'],'Expired sessions must be removed without logging out active owners');
checkMaintenance(DB::table('job_run_history')->count()===2,'Retain the last successful run for every collector');
checkMaintenance(DB::table('player_projection_baselines')->get()->toJson()===$frozen && DB::table('player_projections')->where('player_id','frozen')->exists(),'Frozen preseason projections must remain intact');
checkMaintenance(DB::table('historical_player_stats')->get()->toJson()===$archive && DB::table('seasons')->where('season_id','archive')->exists(),'League and player archives must be preserved');
$again=$maintenance->run();checkMaintenance(array_sum($again)===0,'Repeat maintenance must be idempotent');
$indexMigration->down();checkMaintenance(!Schema::hasIndex('season_player_stats','season_players_points'),'Index migration must roll back cleanly');
checkMaintenance((new App\Support\DatabaseSpace)->reclaim()===[],'MySQL file compaction must not issue ALTER statements against SQLite');

// Exercise compaction orchestration without touching a production database.
class ReclaimTestConnection extends Illuminate\Database\MySqlConnection {
 public array $statements=[];
 public bool $released=false;
 private int $reads=0;
 public function __construct(private bool $fail){parent::__construct(fn()=>throw new RuntimeException('No network allowed'),'test','',['driver'=>'mysql']);}
 public function selectOne($sql,$bindings=[],$useReadPdo=true){
  if(str_contains($sql,'GET_LOCK'))return (object)['acquired'=>1];
  if(str_contains($sql,'RELEASE_LOCK')){$this->released=true;return (object)['released'=>1];}
  if(str_contains($sql,'@@SESSION.lock_wait_timeout'))return (object)['lock_timeout'=>31536000,'stats_expiry'=>86400];
  throw new RuntimeException('Unexpected query: '.$sql);
 }
 public function select($sql,$bindings=[],$useReadPdo=true){
  checkMaintenance(str_contains($sql,"s.SPACE_TYPE = 'Single'")&&str_contains($sql,'ORDER BY s.FILE_SIZE ASC'),'Only individual tablespaces may be rebuilt, smallest first');
  $small=(object)['name'=>'small','reusable_bytes'=>2097152,'file_bytes'=>4194304,'allocated_bytes'=>4194304];
  if($this->reads++){$small->file_bytes=$small->allocated_bytes=2097152;}
  return [$small,(object)['name'=>'large','reusable_bytes'=>4194304,'file_bytes'=>16777216,'allocated_bytes'=>16777216],
   (object)['name'=>'oversized','reusable_bytes'=>8388608,'file_bytes'=>100663296,'allocated_bytes'=>100663296],
   (object)['name'=>'packed','reusable_bytes'=>0,'file_bytes'=>1048576,'allocated_bytes'=>1048576]];
 }
 public function statement($sql,$bindings=[]){
  $this->statements[]=$sql;
  if($this->fail && str_starts_with($sql,'ALTER TABLE `large`'))throw new RuntimeException('Metadata lock unavailable');
  return true;
 }
}
$manager=DB::getFacadeRoot();
foreach([false,true] as $fail){
 $connection=new ReclaimTestConnection($fail);
 DB::swap(new class($connection){public function __construct(private $connection){}public function connection(){return $this->connection;}});
 try{
  $result=(new App\Support\DatabaseSpace)->reclaim();
  checkMaintenance(!$fail && $result['tables_rebuilt']===2 && $result['tables_skipped']===1 && $result['allocated_bytes_reclaimed']===2097152,'Compaction must measure physical bytes and skip oversized/packed files');
 }catch(RuntimeException $e){checkMaintenance($fail && str_contains($e->getMessage(),'retry for: large'),'Partial compaction must report the failed table for retry');}
 finally{DB::swap($manager);}
 checkMaintenance($connection->released,'Compaction must release its database lock after success or partial failure');
 checkMaintenance($connection->statements===['SET SESSION lock_wait_timeout = 5','SET SESSION information_schema_stats_expiry = 0',
  'ALTER TABLE `small` FORCE, ALGORITHM=INPLACE, LOCK=NONE','ALTER TABLE `large` FORCE, ALGORITHM=INPLACE, LOCK=NONE',
  'SET SESSION lock_wait_timeout = 31536000','SET SESSION information_schema_stats_expiry = 86400'],'Compaction must preserve concurrent writes, skip packed/oversized tables, and restore session settings');
}
CarbonImmutable::setTestNow();
echo "Database maintenance checks passed: DFO exceptions, team identity, compact sources, canonical/league archives, seven-day working sets, outage fallback, session expiration, last job state, frozen projections, idempotency and reversible indexes.\n";
