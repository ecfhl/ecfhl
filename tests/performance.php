<?php
// Disposable database, image directory and cache; never production credentials or data.
putenv('DB_CONNECTION=sqlite'); putenv('DB_DATABASE=:memory:');
putenv('SESSION_DRIVER=array'); putenv('CACHE_STORE=array'); putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Http\Request;
use App\Support\TeamImages;
use App\Support\PublicData;

function checkSpeed($ok,$message){if(!$ok)throw new RuntimeException($message);}
config(['database.connections.sqlite'=>['driver'=>'sqlite','database'=>':memory:','prefix'=>'','foreign_key_constraints'=>true]]);
foreach (['2026_09_24_000001_create_ecfhl_tables.php','2026_09_24_000001_create_source_cache_table.php'] as $name) (require __DIR__.'/../database/migrations/'.$name)->up();
$text=file_get_contents(__DIR__.'/../database/data/ecfhl_database.txt');
preg_match_all('/TAB NAME:\s*([^>]+)>\R(.*?)(?=<PARSED TEXT FOR SHEET:|\z)/su',$text,$matches,PREG_SET_ORDER);
foreach($matches as $match){
 $table=trim($match[1]);if(!Illuminate\Support\Facades\Schema::hasTable($table))continue;
 $stream=fopen('php://temp','r+');fwrite($stream,$match[2]);rewind($stream);$headers=fgetcsv($stream);array_shift($headers);
 while(($row=fgetcsv($stream))!==false){array_shift($row);if(count($row)!==count($headers))continue;
  $row=array_map(fn($v)=>$v===''?null:($v==='True'?1:($v==='False'?0:$v)),$row);DB::table($table)->insert(array_combine($headers,$row));
 }fclose($stream);
}
// Initial tables came from the fixture, so mark their migrations as complete.
DB::getSchemaBuilder()->create('migrations',fn($t)=>[$t->id(),$t->string('migration'),$t->integer('batch')]);
foreach(['2026_09_24_000001_create_ecfhl_tables','2026_09_24_000001_create_source_cache_table'] as $migration) DB::table('migrations')->insert(['migration'=>$migration,'batch'=>1]);
Artisan::call('migrate',['--force'=>true]);
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
DB::enableQueryLog();
$optimized=class_exists(PublicData::class);
$cacheDirectory=sys_get_temp_dir().'/ecfhl-performance-'.getmypid();
config(['cache.stores.file.path'=>$cacheDirectory.'/cache','performance.public_data_cache'=>$optimized]);
Illuminate\Support\Facades\Cache::forgetDriver('file');
$budgets=['/'=>5,'/seasons'=>5,'/teams'=>5,'/trades'=>5,'/draft'=>5,'/players/history?q=Sidney'=>3,'/rules'=>2,'/register'=>6];
foreach(['/','/seasons','/teams','/trades','/draft','/players/history?q=Sidney','/rules','/register'] as $path){
 $counts=[];
 for($run=0;$run<2;$run++){
  $app->forgetScopedInstances();DB::flushQueryLog();$request=Request::create($path.(str_contains($path,'?')?'&':'?').'type=all');
  $start=microtime(true);$response=$kernel->handle($request);$kernel->terminate($request,$response);
  checkSpeed($response->getStatusCode()===200,'Page failed: '.$path);
  $counts[]=count(DB::getQueryLog());$milliseconds=round((microtime(true)-$start)*1000,1);
 }
 if($optimized)checkSpeed($counts[1]<=$budgets[$path], 'Warm query budget exceeded: '.$path);
 echo json_encode(['path'=>$path,'cold_queries'=>$counts[0],'warm_queries'=>$counts[1],'warm_ms'=>$milliseconds])."\n";
}
if(!$optimized)exit(0);
$app->usePublicPath($cacheDirectory.'/public');
$image=imagecreatetruecolor(1200,800);imagealphablending($image,false);imagesavealpha($image,true);
imagefill($image,0,0,imagecolorallocatealpha($image,0,0,0,127));imagefilledellipse($image,600,400,700,700,imagecolorallocate($image,40,120,240));
ob_start();imagepng($image);$bytes=ob_get_clean();imagedestroy($image);
DB::table('team_icons')->insert(['team_slug'=>'sample','mime_type'=>'image/png','image_data'=>base64_encode($bytes)]);
DB::flushQueryLog();$request=Request::create('/team-icons/sample/thumbnail?size=160');$response=$kernel->handle($request);$kernel->terminate($request,$response);
checkSpeed($response->getStatusCode()===200,'Thumbnail response failed');
checkSpeed(count(DB::getQueryLog())===1,'Cold image must use one indexed lookup, without a session');
checkSpeed(!$response->headers->has('Set-Cookie'),'Public image started a session');
$path=$response->getFile()->getPathname();$info=getimagesize($path);checkSpeed($info[0]===160 && $info[1]===160 && $info['mime']==='image/webp','Thumbnail was not resized to WebP');
$thumbnail=imagecreatefromwebp($path);checkSpeed((imagecolorat($thumbnail,0,0)>>24)===127,'Transparent padding was lost');imagedestroy($thumbnail);
DB::flushQueryLog();$request=Request::create('/team-icons/sample/thumbnail?size=160','GET',[],[],[],['HTTP_IF_NONE_MATCH'=>$response->getEtag()]);$cached=$kernel->handle($request);$kernel->terminate($request,$cached);
checkSpeed($cached->getStatusCode()===304,'Cached image did not support ETag validation');
checkSpeed(count(DB::getQueryLog())===0,'Warm image queried the database');
$oldUrl=TeamImages::url('sample',160);TeamImages::generate('sample',$bytes.' ', 'image/png');checkSpeed(TeamImages::url('sample',160)!==$oldUrl,'Upload revision did not invalidate browser cache');
// A detailed alpha-heavy upload must respect both small-logo transfer budgets.
$noise=imagecreatetruecolor(256,256);imagealphablending($noise,false);imagesavealpha($noise,true);
for($y=0;$y<256;$y++)for($x=0;$x<256;$x++)imagesetpixel($noise,$x,$y,imagecolorallocatealpha($noise,($x*73+$y*31)%256,($x*17+$y*89)%256,($x*43+$y*53)%256,($x*11+$y*7)%128));
ob_start();imagepng($noise);$detailed=ob_get_clean();imagedestroy($noise);
$manifest=TeamImages::generate('detailed',$detailed,'image/png');
foreach([64=>3072,160=>6144] as $size=>$budget){
 $file=$cacheDirectory.'/public'.TeamImages::url('detailed',$size);
 checkSpeed(filesize($file)<=$budget,'Detailed logo exceeded '.$size.'px byte budget');
 $info=getimagesize($file);checkSpeed($info[0]<=$size&&$info[1]<=$size&&$info['mime']==='image/webp','Bounded logo format changed');
}
checkSpeed(file_get_contents($cacheDirectory.'/public'.TeamImages::url('detailed'))===$detailed,'Original logo was modified');
// Upgrade an old manifest from its local original, without a database read.
$manifest['version']=1;file_put_contents($cacheDirectory.'/public/media/team-icons/detailed/manifest.json',json_encode($manifest));
checkSpeed(str_starts_with(TeamImages::url('detailed',160),'/team-icons/'),'Outdated thumbnail remained in page URLs');
DB::flushQueryLog();$upgraded=TeamImages::response(Request::create('/team-icons/detailed/thumbnail'),'detailed',160);
checkSpeed(count(DB::getQueryLog())===0,'Thumbnail upgrade reloaded database original');
checkSpeed(filesize($upgraded->getFile()->getPathname())<=6144,'Upgraded logo exceeded byte budget');
DB::flushQueryLog();PublicData::teamMenu();PublicData::teamMenu();checkSpeed(count(DB::getQueryLog())<=1,'Menu cache duplicated its query');
$repository=app(App\Support\LiveScoring\SnapshotRepository::class);
$snapshot=['fantasy_date'=>'2026-10-03','source_date'=>'2026-10-03','players'=>[]];
$repository->publish($snapshot,['large_upstream_fixture'=>str_repeat('x',100000)],Carbon\CarbonImmutable::now());
DB::flushQueryLog();$repository->get('2026-10-03');$repository->get('2026-10-03');
checkSpeed(count(DB::getQueryLog())===1,'Snapshot loaded twice in one request');
checkSpeed(!str_contains(DB::getQueryLog()[0]['query'],'select *'),'Snapshot unnecessarily loaded raw upstream payload');
$snapshot['revision']='new';$repository->publish($snapshot,[],Carbon\CarbonImmutable::now());checkSpeed($repository->get('2026-10-03')['revision']==='new','Publish did not invalidate cached scoring');
$plan=DB::select('EXPLAIN QUERY PLAN SELECT * FROM active_fantasy_rosters WHERE game_date=? AND fantasy_team_id=?',['2026-10-03','sample']);
checkSpeed(str_contains(json_encode($plan),'USING INDEX'),'Roster date/team lookup did not use an index');
echo 'Performance checks passed: real WebP sizes/transparency, zero-query cached images, no image sessions, ETags, upload invalidation, menu cache, snapshot reuse/invalidation and indexed date filters.'."\n";
(new Illuminate\Filesystem\Filesystem)->deleteDirectory($cacheDirectory);
