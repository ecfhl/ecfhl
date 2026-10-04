<?php
putenv('DB_CONNECTION=sqlite');putenv('DB_DATABASE=:memory:');putenv('SESSION_DRIVER=array');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});
use App\Support\SeasonPlayers;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
function verifySeason($ok,$message){if(!$ok)throw new RuntimeException($message);}
// Run real migrations, including the transition from stored season rates.
foreach(glob(__DIR__.'/../database/migrations/*create*.php') as $file)(require $file)->up();
(require __DIR__.'/../database/migrations/2026_10_03_210000_add_season_actuals_to_player_projections.php')->up();
config(['performance.public_data_cache'=>false]);
foreach(['skater'=>['GP'=>'Games played','G'=>'Goals','A'=>'Assists','TOI'=>'Time on ice'],'goalie'=>['GP'=>'Games played','W'=>'Wins','SV%'=>'Save percentage']] as $group=>$columns) DB::table('season_player_stat_columns')->insert(['group'=>$group,'columns_json'=>json_encode($columns)]);
for($i=1;$i<=65;$i++) DB::table('season_player_stats')->insert(['player_id'=>'p'.$i,'season_id'=>'2026-27','player_name'=>$i===1?'Player <unsafe>':'Player '.str_pad($i,2,'0',STR_PAD_LEFT),'nhl_team'=>'MTL','position'=>$i>60?'G':($i%2?'F':'D'),'rookie'=>$i%3===0,'season_fpts'=>100-$i,'season_gp'=>10,'season_fpts_per_game'=>(100-$i)/10,'stats_json'=>json_encode(['G'=>'2','A'=>'5','TOI'=>'71:11','W'=>'3','SV%'=>'.925']),'stats_through'=>'2026-10-04','refreshed_at'=>now()]);
$projection=['player_id'=>'p1','as_of_date'=>'2026-10-04','window_end_date'=>'2026-10-04','projected_fpts_per_game'=>6.25,'refreshed_at'=>now()];foreach([7,14,21] as $days){$projection['gp_'.$days.'d']=0;$projection['fpts_'.$days.'d']=0;$projection['fpts_per_game_'.$days.'d']=0;}DB::table('player_projections')->insert($projection);
foreach([['2026-10-03','old','Old owner','p2'],['2026-10-04','alpha','Alpha','p1'],['2026-10-04','beta','Beta','p1']] as [$date,$id,$name,$player]) DB::table('active_fantasy_rosters')->insert(['game_date'=>$date,'fantasy_team_id'=>$id,'fantasy_team_name'=>$name,'player_id'=>$player,'player_name'=>'Player','position'=>'F']);
$service=new SeasonPlayers;
$d=$service->data(Request::create('/players'));
verifySeason($d['positions']===['F','D']&&$d['players']->total()===60&&$d['players']->count()===25,'Defaults must show F/D only and 25 players.');
verifySeason($d['players'][0]->fantasy_team_name==='Beta'&&$d['players'][1]->fantasy_team_name===null,'Latest snapshot ownership / deduplicated roster join failed.');
verifySeason((float)$d['players'][0]->projected_fpts_per_game===6.25&&$d['players'][1]->projected_fpts_per_game===null,'MyProj must use saved custom rates and preserve untracked nulls.');
verifySeason(array_keys($d['columns'])===['G','A','TOI']&&$d['players'][0]->stats['TOI']==='71:11','Full stat columns / time values were lost.');
$rookies=$service->data(Request::create('/players?positions=F,D&rookies=1'));
verifySeason($rookies['players']->total()===20&&$rookies['players']->getCollection()->every(fn($p)=>(bool)$p->rookie),'Rookie On must exclude veterans.');
$all=$service->data(Request::create('/players?positions=F,D,G&rookies=0'));
verifySeason($all['players']->total()===65&&isset($all['columns']['SV%']),'All selected groups / rookie Off must include every player.');
$goalies=$service->data(Request::create('/players?positions=G&rookies=1'));
verifySeason($goalies['players']->total()===1&&array_keys($goalies['columns'])===['W','SV%'],'Goalie-only rookie filtering / columns failed.');
$empty=$service->data(Request::create('/players?positions='));verifySeason($empty['players']->total()===0,'Deselected positions must not reset to defaults.');
$second=$service->data(Request::create('/players?positions=F,D&page=2'));
verifySeason($second['players']->count()===25&&$second['players']->firstItem()===26&&$second['players']->lastItem()===50,'Second page must append the next 25.');
$third=$service->data(Request::create('/players?positions=F,D&page=3'));verifySeason($third['players']->count()===10&&$third['players']->nextPageUrl()===null,'Final page must stop pagination.');
$filtered=$service->data(Request::create('/players?positions=F,D&rookies=1&q=Player'));verifySeason($filtered['players']->total()===20,'Search must preserve the position and rookie filters.');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function seasonRequest($url,$json=false){global $app,$kernel;$app->forgetScopedInstances();$r=Request::create($url,'GET',[],[],[],['HTTP_ACCEPT'=>$json?'application/json':'text/html']);$response=$kernel->handle($r);$kernel->terminate($r,$response);verifySeason($response->getStatusCode()===200,'Players response failed: '.$response->getContent());return $response;}
$html=seasonRequest('/players')->getContent();
verifySeason(substr_count($html,'data-player-id=')===25&&str_contains($html,'Player &lt;unsafe&gt;')&&!str_contains($html,'Player <unsafe>'),'SSR row count / escaped names failed.');
verifySeason(str_contains($html,'MyProj/GP')&&str_contains($html,'71:11')&&str_contains($html,'/teams/current/beta')&&str_contains($html,'Free Agent'),'Stats / ownership / custom projection rendering failed.');
verifySeason(str_contains($html,'aria-pressed="true" href="/players?positions=D')&&str_contains($html,'aria-pressed="true" href="/players?positions=F')&&str_contains($html,'aria-pressed="false" href="/players?positions=F%2CD%2CG'),'Default filter button states failed.');
$json=json_decode(seasonRequest('/players?positions=F,D&page=2',true)->getContent(),true);
verifySeason(substr_count($json['html'],'data-player-id=')===25&&$json['shown']===50&&$json['total']===60&&str_contains($json['next_url'],'positions=F%2CD'),'Show More must return next rows with preserved filters.');
$final=json_decode(seasonRequest('/players?positions=G&rookies=1',true)->getContent(),true);verifySeason($final['shown']===1&&$final['total']===1&&$final['next_url']===null,'Filtered Show More termination failed.');
$admin=view('admin.index')->render();foreach(['/admin/projections','/job-status','/admin/advisors','/admin/team-images'] as $url)verifySeason(str_contains($admin,'class="card admin-menu-card" href="'.$url.'"'),'Admin card missing: '.$url);
echo "Season players checks passed: defaults, full stats, rookies, positions, search, latest ownership, MyProj, escaped SSR, 25-row pagination and admin cards.\n";
