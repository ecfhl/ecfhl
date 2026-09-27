<?php
/** Isolated SQLite validation; no production connection or writes. */
putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Database\Seeders\DraftsOnlySeeder;
config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
Artisan::call('migrate',['--force'=>true,'--path'=>'database/migrations/2026_09_24_000001_create_ecfhl_tables.php']);
Artisan::call('migrate',['--force'=>true,'--path'=>'database/migrations/2026_09_24_000001_create_source_cache_table.php']);
$text=file_get_contents(__DIR__.'/../database/data/ecfhl_database.txt');
preg_match_all('/TAB NAME:\s*([^>]+)>\R(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/su',$text,$matches,PREG_SET_ORDER);
foreach($matches as $m){
 $table=trim($m[1]);if(in_array($table,['drafts','draft_picks'])||!Illuminate\Support\Facades\Schema::hasTable($table))continue;
 $f=fopen('php://temp','r+');fwrite($f,$m[2]);rewind($f);$h=fgetcsv($f);array_shift($h);
 while(($r=fgetcsv($f))!==false){if($r===[null])continue;array_shift($r);if(count($r)!==count($h))throw new RuntimeException('Bad fixture');$r=array_map(fn($v)=>$v===''?null:($v==='True'?1:($v==='False'?0:$v)),$r);DB::table($table)->insert(array_combine($h,$r));}fclose($f);
}
function check($ok,$why){if(!$ok)throw new RuntimeException($why);}
$seeder=new DraftsOnlySeeder;
$plan=$seeder->plan();check(count($plan['draft_picks'])===2103,'Source count');
$seeder->run();$seeder->run();check(DB::table('draft_picks')->count()===2103,'Idempotence');
$before=DB::table('draft_picks')->get()->toJson();
DB::statement("CREATE TRIGGER reject_pick BEFORE INSERT ON draft_picks BEGIN SELECT RAISE(ABORT, 'test rollback'); END");
try{$seeder->run();throw new RuntimeException('Expected insertion failure');}catch(Illuminate\Database\QueryException $e){}
check(DB::table('draft_picks')->get()->toJson()===$before,'Transaction must restore old picks');
DB::statement('DROP TRIGGER reject_pick');
DB::table('team_seasons')->where('season_id','S2025')->delete();
try{$seeder->run();throw new LogicException('Expected mapping failure');}catch(RuntimeException $e){check(!($e instanceof LogicException),'Missing mapping accepted');}
check(DB::table('draft_picks')->get()->toJson()===$before,'Mapping failure must not mutate drafts');
// Restore just the removed disposable fixture rows for page verification.
foreach(DraftsOnlySeeder::source()['team_seasons'] as $r)if($r['season_id']==='S2025')DB::table('team_seasons')->insert($r);
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
$years=DB::table('seasons')->pluck('season_name','season_id');
foreach($plan['counts'] as $sid=>$count){
 $app->forgetScopedInstances();$req=Illuminate\Http\Request::create('/draft?type=all&season='.urlencode($years[$sid]));$res=$kernel->handle($req);
 check($res->getStatusCode()===200,'Draft page status');check(substr_count($res->getContent(),'class="draft-pick-row"')===$count,'Season page count '.$sid);$kernel->terminate($req,$res);
}
foreach(DB::table('draft_picks')->distinct()->pluck('franchise_id') as $fid){
 $app->forgetScopedInstances();$req=Illuminate\Http\Request::create('/draft?type=all&season=all&franchise='.$fid);$res=$kernel->handle($req);
 check($res->getStatusCode()===200,'Franchise status');preg_match_all('/class="draft-pick-row" data-franchise="([^"]+)"/',$res->getContent(),$m);
 check(count($m[1])===DB::table('draft_picks')->where('franchise_id',$fid)->count(),'Franchise pick count');check(array_unique($m[1])===[$fid],'Franchise leak');$kernel->terminate($req,$res);
}
echo "PASS: 2103 picks, 16 seasons, all franchise filters, repeat seed, rollback, missing mapping refusal; protected tables unchanged.\n";
