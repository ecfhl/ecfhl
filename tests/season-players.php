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
foreach(['skater'=>['GP'=>'Games played','G'=>'Goals','A'=>'Assists','TOI'=>'Time on ice'],'goalie'=>['GP'=>'Games played','Min'=>'Minutes','W'=>'Wins','L'=>'Losses','OL'=>'Overtime losses','SHO'=>'Shutouts','GAA'=>'Goals against average','G'=>'Goals','A'=>'Assists','SV%'=>'Save percentage']] as $group=>$columns) DB::table('season_player_stat_columns')->insert(['group'=>$group,'columns_json'=>json_encode($columns)]);
for($i=1;$i<=65;$i++) DB::table('season_player_stats')->insert(['player_id'=>'p'.$i,'season_id'=>'2026-27','player_name'=>$i===1?'Player <unsafe>':'Player '.str_pad($i,2,'0',STR_PAD_LEFT),'nhl_team'=>'MTL','position'=>$i>60?'G':($i%2?'F':'D'),'rookie'=>$i%3===0,'season_fpts'=>100-$i,'season_gp'=>10,'season_fpts_per_game'=>(100-$i)/10,'stats_json'=>json_encode(['G'=>'2','A'=>'5','TOI'=>$i===1?'71:11':'10:00','W'=>'3','SV%'=>'.925']),'stats_through'=>'2026-10-04','refreshed_at'=>now()]);
$projection=['player_id'=>'p1','as_of_date'=>'2026-10-04','window_end_date'=>'2026-10-04','projected_fpts_per_game'=>6.25,'refreshed_at'=>now()];foreach([7,14,21] as $days){$projection['gp_'.$days.'d']=0;$projection['fpts_'.$days.'d']=0;$projection['fpts_per_game_'.$days.'d']=0;}DB::table('player_projections')->insert($projection);
foreach([['2026-10-03','old','Old owner','p2'],['2026-10-04','alpha','Alpha','p1'],['2026-10-04','beta','Beta','p1']] as [$date,$id,$name,$player]) DB::table('active_fantasy_rosters')->insert(['game_date'=>$date,'fantasy_team_id'=>$id,'fantasy_team_name'=>$name,'player_id'=>$player,'player_name'=>'Player','position'=>'F']);
// Assignments use collector names/abbreviations, including reversed names and aliases.
DB::table('season_player_stats')->where('player_id','p3')->update(['nhl_team'=>'SJS']);
for($i=1;$i<=35;$i++) {
 $name=$i===1?'Player <unsafe>':($i===3?'03, Player':'Player '.str_pad($i,2,'0',STR_PAD_LEFT));
 $team=$i===3?'SJ':'MTL';
 DB::table('active_line_combinations')->insert(['team'=>$team,'player_name'=>$name,'position_group'=>$i%2?'F':'D','line_number'=>$i===2?2:1,'source_url'=>'https://example.com','last_update'=>now()]);
 DB::table('active_pp_lines')->insert(['team'=>$team,'player_name'=>$name,'pp_unit'=>in_array($i,[2,4])?2:1,'source_url'=>'https://example.com','last_update'=>now()]);
}
$service=new SeasonPlayers;
$defaults=$service->data(Request::create('/players'));
verifySeason($defaults['availability']==='available'&&$defaults['playing']==='all'&&$defaults['playingDate']===null&&$defaults['players']->total()===59&&$defaults['players']->getCollection()->every(fn($p)=>$p->fantasy_team_id===null),'Defaults must select Available, All game days, and exclude currently owned players.');
$d=$service->data(Request::create('/players?availability=all'));
verifySeason($d['positions']===['F','D']&&$d['players']->total()===60&&$d['players']->count()===25,'All availability must show F/D only and 25 players.');
verifySeason($d['players'][0]->fantasy_team_name==='Beta'&&$d['players'][1]->fantasy_team_name===null,'Latest snapshot ownership / deduplicated roster join failed.');
verifySeason((float)$d['players'][0]->projected_fpts_per_game===6.25&&$d['players'][1]->projected_fpts_per_game===null,'MyProj must use saved custom rates and preserve untracked nulls.');
verifySeason(array_keys($d['columns'])===['G','A','Pts','PPG','SHG','GWG','SOG','TOI']&&$d['players'][0]->stats['TOI']==='71:11','Full stat columns / time values were lost.');
$rookies=$service->data(Request::create('/players?availability=all&positions=F,D&rookies=1'));
verifySeason($rookies['players']->total()===20&&$rookies['players']->getCollection()->every(fn($p)=>(bool)$p->rookie),'Rookie On must exclude veterans.');
$all=$service->data(Request::create('/players?availability=all&positions=F,D,G&rookies=0'));
verifySeason($all['players']->total()===65&&isset($all['columns']['SV%']),'All selected groups / rookie Off must include every player.');
$goalies=$service->data(Request::create('/players?availability=all&positions=G&rookies=1'));
verifySeason($goalies['players']->total()===1&&array_keys($goalies['columns'])===['W','L','OL','SHO','GAA','G','A','SV%']&&$goalies['headers']['SHO']==='SO'&&!isset($goalies['headers']['Min']),'Goalie-only rookie filtering / columns failed.');
$empty=$service->data(Request::create('/players?availability=all&positions='));verifySeason($empty['players']->total()===0,'Deselected positions must not reset to defaults.');
$second=$service->data(Request::create('/players?availability=all&positions=F,D&page=2'));
verifySeason($second['players']->count()===25&&$second['players']->firstItem()===26&&$second['players']->lastItem()===50,'Second page must append the next 25.');
$third=$service->data(Request::create('/players?availability=all&positions=F,D&page=3'));verifySeason($third['players']->count()===10&&$third['players']->nextPageUrl()===null,'Final page must stop pagination.');
$filtered=$service->data(Request::create('/players?availability=all&positions=F,D&rookies=1&q=Player'));verifySeason($filtered['players']->total()===20,'Search must preserve the position and rookie filters.');
// Use values with different digit lengths, negatives, missing rates and tied minutes.
foreach(['p1'=>['A'=>'9','G'=>'2','Pt'=>'11','PPG'=>'2','SHG'=>'1','GWG'=>'2','SOG'=>'999','TOI'=>'71:11'], 'p2'=>['A'=>'100','G'=>'10','Pt'=>'110','PPG'=>'10','SHG'=>'3','GWG'=>'10','SOG'=>'1,000','TOI'=>'71:59'], 'p60'=>['A'=>'-2','G'=>'0','Pt'=>'-2','PPG'=>'0','SHG'=>'0','GWG'=>'0','SOG'=>'0','TOI'=>'105:01']] as $id=>$stats)DB::table('season_player_stats')->where('player_id',$id)->update(['stats_json'=>json_encode($stats)]);
DB::table('season_player_stats')->where('player_id','p2')->update(['season_gp'=>25,'season_fpts_per_game'=>20]);
foreach(['gp'=>'p2','fpts_gp'=>'p2','PPG'=>'p2','GWG'=>'p2','A'=>'p2','G'=>'p2','Pts'=>'p2','SHG'=>'p2','SOG'=>'p2','TOI'=>'p60','ec_proj'=>'p1'] as $key=>$first){
 $sorted=$service->data(Request::create('/players?availability=all&sort='.$key.'&direction=desc'));
 verifySeason($sorted['players'][0]->player_id===$first, 'Descending full-dataset numeric sorting failed: '.$key);
 verifySeason(str_contains($sorted['players']->nextPageUrl(),'sort='.$key)&&str_contains($sorted['players']->nextPageUrl(),'direction=desc'), 'Show More lost sorting: '.$key);
}
$names=$service->data(Request::create('/players?availability=all&sort=player&direction=asc'));verifySeason($names['players'][0]->player_id==='p2','Player sorting must use names alphabetically.');
$ascending=$service->data(Request::create('/players?availability=all&sort=A&direction=asc'));verifySeason($ascending['players'][0]->player_id==='p60','Negative stats must sort before zero/positive stats.');
$minutes=$service->data(Request::create('/players?availability=all&sort=TOI&direction=desc'));verifySeason($minutes['players'][1]->player_id==='p2'&&$minutes['players'][2]->player_id==='p1','TOI must compare seconds when minutes tie.');
$missing=$service->data(Request::create('/players?availability=all&sort=ec_proj&direction=asc'));verifySeason($missing['players'][0]->player_id==='p1','Unavailable ECFHL Score must stay last when ascending.');
$teams=$service->data(Request::create('/players?availability=all&sort=team&direction=asc'));verifySeason($teams['players'][0]->fantasy_team_name==='Beta','Team sorting must use displayed ECFHL team.');
$invalid=$service->data(Request::create('/players?availability=all&sort=DROP%20TABLE&direction=INVALID'));verifySeason($invalid['sort']==='fpts'&&$invalid['direction']==='desc','Reject unknown SQL sort fields and directions.');
$sortedRookies=$service->data(Request::create('/players?availability=all&positions=F,D&rookies=1&sort=A&direction=asc'));verifySeason($sortedRookies['players'][0]->player_id==='p60'&&$sortedRookies['players']->total()===20,'Sorting must preserve rookie/position filters.');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function seasonRequest($url,$json=false){global $app,$kernel;$app->forgetScopedInstances();$r=Request::create($url,'GET',[],[],[],['HTTP_ACCEPT'=>$json?'application/json':'text/html']);$response=$kernel->handle($r);$kernel->terminate($r,$response);verifySeason($response->getStatusCode()===200,'Players response failed: '.$response->getContent());return $response;}
$html=seasonRequest('/players?availability=all')->getContent();
$defaultHtml=seasonRequest('/players')->getContent();
verifySeason(str_contains($html,'player-assignment-tag player-assignment-2">L2</span>')&&str_contains($html,'player-assignment-tag player-assignment-2">PP2</span>')&&!str_contains($html,'Pair2'),'Defensemen and PP assignments must use colored L/PP stickers.');
verifySeason(str_contains($html,'player-assignment-tag player-assignment-1">L1</span>')&&str_contains($html,'player-assignment-tag player-assignment-1">PP1</span>'),'Forwards must show the same colored L/PP stickers.');
verifySeason(substr_count($html,'data-player-id=')===25&&str_contains($html,'&lt;unsafe&gt;, Player')&&!str_contains($html,'Player <unsafe>'),'SSR row count / escaped names failed.');
verifySeason(str_contains($html,'ECFHL*')&&str_contains($html,'71:11')&&str_contains($html,'/teams/current/beta')&&str_contains($html,'Free Agent'),'Stats / ownership / custom projection rendering failed.');
verifySeason(str_contains($html,'aria-pressed="true" href="/players?positions=D')&&str_contains($html,'aria-pressed="true" href="/players?positions=F')&&str_contains($html,'aria-pressed="false" href="/players?positions=F%2CD%2CG'),'Default filter button states failed.');
verifySeason(!str_contains($html,'href="/daily-targets"'),'Retired Daily Targets shortcut must be absent.');
preg_match('/<thead>(.*?)<\/thead>/s',$html,$tableHead);
preg_match_all('/<th scope="col"[^>]*>(.*?)<\/th>/s',$tableHead[1],$headCells);
$headerLabels=array_map(fn($v)=>rtrim(trim(strip_tags($v)), ' ↑↓↕'),$headCells[1]);
verifySeason($headerLabels===['Rank','Player','Team','ECFHL*','FPts','FPts/gp','TodayAtlantic time','TomorrowAtlantic time','GP','G','A','Pts','PPG','SHG','GWG','SOG','TOI'],'Column order and labels must match the requested stats exactly.');
verifySeason(preg_match('/<td class="myproj">6\.25<\/td>\s*<td>99<\/td>\s*<td>9\.90<\/td>\s*<td class="player-game-cell">.*?<\/td>\s*<td class="player-game-cell">.*?<\/td>\s*<td>10<\/td>\s*<td>2<\/td>\s*<td>9<\/td>/', $html), 'Row values must follow ECFHL Score, FPts, FPts/gp, GP, G and A header order.');
$nameSearch=$service->data(Request::create('/players?availability=all&q=02%2C%20Player'));
verifySeason($nameSearch['players']->total()===1 && $nameSearch['players'][0]->player_id==='p2','Displayed Lastname, Firstname must work in player search.');
verifySeason(str_contains($html,'Reset filters'),'Reset filters must be visible above results.');
$availableChip=seasonRequest('/players')->getContent();verifySeason(str_contains($availableChip,'Available only'),'Default availability must be visible above results.');
$sortHtml=seasonRequest('/players?availability=all&rookies=1&sort=A&direction=asc')->getContent();
verifySeason(str_contains($sortHtml,'aria-sort="ascending"')&&str_contains($sortHtml,'sort=A&amp;direction=desc')&&str_contains($sortHtml,'name="sort" value="A"'),'Sort arrows / toggle links / search preservation failed.');
// Column headers provide sorting; dropdown controls are removed.
$sortDoc=new DOMDocument(); @$sortDoc->loadHTML($sortHtml); $sortPath=new DOMXPath($sortDoc);
verifySeason($sortPath->query('//select[@name="sort" or @name="direction"]')->length===0,'Sort and Order dropdowns must be removed.');
$scoreHtml=seasonRequest('/players?availability=all&sort=ec_proj&direction=desc')->getContent();
verifySeason(!str_contains($scoreHtml,'Sorted by')&&str_contains($scoreHtml,'aria-label="Sort ECFHL* ascending"'),'Score header must use the compact ECFHL label and remain sortable.');
$json=json_decode(seasonRequest('/players?availability=all&positions=F,D&page=2',true)->getContent(),true);
verifySeason(substr_count($json['html'],'data-player-id=')===25&&$json['shown']===50&&$json['total']===60&&str_contains($json['next_url'],'positions=F%2CD'),'Show More must return next rows with preserved filters.');
$final=json_decode(seasonRequest('/players?availability=all&positions=G&rookies=1',true)->getContent(),true);verifySeason($final['shown']===1&&$final['total']===1&&$final['next_url']===null,'Filtered Show More termination failed.');
$admin=view('admin.index')->render();foreach(['/admin/projections','/job-status','/admin/advisors','/admin/teams'] as $url)verifySeason(str_contains($admin,'class="card admin-menu-card" href="'.$url.'"'),'Admin card missing: '.$url);
// Ownership filters include every roster slot and ignore released players' old teams.
for($i=3;$i<=40;$i++) DB::table('active_fantasy_rosters')->insert(['game_date'=>'2026-10-04','fantasy_team_id'=>$i<=35?'beta':'gamma','fantasy_team_name'=>$i<=35?'Beta':'Gamma','player_id'=>'p'.$i,'player_name'=>'Player '.$i,'position'=>'F','roster_status'=>['ACTIVE','BENCH','MINORS','INJURED_RESERVE'][$i%4]]);
$taken=$service->data(Request::create('/players?availability=taken&sort=A&direction=desc'));
verifySeason($taken['players']->total()===39&&$taken['players']->getCollection()->every(fn($p)=>$p->fantasy_team_id!==null),'Taken must include all current roster slots, without duplicate players.');
$available=$service->data(Request::create('/players?availability=available'));
verifySeason($available['players']->total()===21&&$available['players']->getCollection()->contains('player_id','p2')&&$available['players']->getCollection()->every(fn($p)=>$p->fantasy_team_id===null),'Available must include released players and exclude all currently owned players.');
$beta=$service->data(Request::create('/players?team=beta&availability=taken&sort=G&direction=asc'));
verifySeason($beta['players']->total()===34&&$beta['players']->getCollection()->every(fn($p)=>$p->fantasy_team_id==='beta'),'Team and Taken filters must combine with numeric sorting.');
verifySeason(str_contains($beta['players']->nextPageUrl(),'team=beta')&&str_contains($beta['players']->nextPageUrl(),'availability=taken')&&str_contains($beta['players']->nextPageUrl(),'sort=G'),'Show More must preserve team, availability and sorting.');
verifySeason(!$beta['teamOptions']->contains('fantasy_team_id','old')&&$beta['teamOptions']->contains('fantasy_team_id','gamma'),'Team slicer must use the current snapshot, not old owners.');
$betaRookies=$service->data(Request::create('/players?team=beta&availability=taken&rookies=1'));
verifySeason($betaRookies['players']->total()===11&&$betaRookies['players']->getCollection()->every(fn($p)=>(bool)$p->rookie&&$p->fantasy_team_id==='beta'),'Team and availability filters must preserve rookie filtering.');
$invalidFilters=$service->data(Request::create('/players?team=unknown&availability=bad'));verifySeason($invalidFilters['selectedTeam']===''&&$invalidFilters['availability']==='available','Unknown filters must fall back to their defaults.');
$betaHtml=seasonRequest('/players?team=beta&availability=taken&sort=G&direction=asc')->getContent();
verifySeason(str_contains($betaHtml,'value="beta" selected')&&str_contains($betaHtml,'name="team" value="beta"')&&str_contains($betaHtml,'name="availability" value="taken"'),'Team selection and search preservation failed.');
verifySeason(str_contains($betaHtml,'class="player-team-logo"')&&str_contains($betaHtml,'width="32" height="32"')&&str_contains($betaHtml,'<span class="player-team-name">Beta</span>'),'Team thumbnail must appear before the team name.');
$betaMore=json_decode(seasonRequest('/players?team=beta&availability=taken&sort=G&direction=asc&page=2',true)->getContent(),true);
verifySeason($betaMore['shown']===34&&$betaMore['total']===34&&substr_count($betaMore['html'],'data-player-id=')===9&&substr_count($betaMore['html'],'class="player-team-logo"')===9,'Filtered Show More must append the right players and their logos.');
// Filter across the full data set, before sorting/pagination, and preserve every control.
$line=$service->data(Request::create('/players?line=1&pp=1&team=beta&availability=taken&sort=G&direction=asc'));
verifySeason($line['players']->total()===33&&$line['players']->count()===25&&$line['players']->getCollection()->every(fn($p)=>(int)$p->line_number===1&&(int)$p->pp_unit===1),'Line/PP filters must combine before pagination and normalize names/team aliases.');
verifySeason(str_contains($line['players']->nextPageUrl(),'line=1')&&str_contains($line['players']->nextPageUrl(),'pp=1'),'Show More lost line/PP filters.');
$lineMore=json_decode(seasonRequest('/players?line=1&pp=1&team=beta&availability=taken&sort=G&direction=asc&page=2',true)->getContent(),true);
verifySeason($lineMore['total']===33&&$lineMore['shown']===33&&substr_count($lineMore['html'],'data-player-id=')===8,'Assignment pagination lost rows or added duplicates.');
$pair=$service->data(Request::create('/players?availability=all&positions=D&line=1&pp=2'));
verifySeason($pair['players']->total()===1&&$pair['players'][0]->player_id==='p4','Defense pairs must use the D assignments.');
$none=$service->data(Request::create('/players?availability=all&positions=F,D,G&line=none&pp=none'));
verifySeason($none['players']->total()===25&&$none['players']->getCollection()->every(fn($p)=>$p->position!=='G'&&$p->line_number===null&&$p->pp_unit===null),'No listed assignment filters must include unassigned skaters and exclude goalies.');
$bad=$service->data(Request::create('/players?availability=all&line=bad&pp=3'));verifySeason($bad['selectedLine']==='1,2,3,4,none'&&$bad['selectedPp']==='1,2,none','Invalid assignment filters must fall back to All.');
$advancedHtml=seasonRequest('/players?availability=all&line=1&pp=1&rookies=1')->getContent();
verifySeason(str_contains($advancedHtml,'class="player-advanced"  open')&&str_contains($advancedHtml,'id="season-player-line"')&&str_contains($advancedHtml,'id="season-player-pp"')&&str_contains($advancedHtml,'name="line" value="1"')&&str_contains($advancedHtml,'line=1&amp;pp=1'),'Active advanced filters must remain visible and survive search, sorting and positions.');
verifySeason(preg_match('/class="player-name-link"[^>]*>[^<]+<span class="rookie-tag">Rookie<\/span><\/a>/', $advancedHtml),'Rookie sticker must be beside the name, not on the metadata line.');
verifySeason(str_contains($html,'class="player-position-f"')&&str_contains($html,'class="player-position-d"')&&str_contains(seasonRequest('/players?availability=all&positions=G')->getContent(),'class="player-position-g"'),'Every row must have its position shade, including goalie rows.');
verifySeason(str_contains($defaultHtml,'class="player-advanced" >')&&!str_contains($defaultHtml,'class="player-advanced"  open'),'Advanced filters should start collapsed without active settings.');
// Frozen panes must include both identity columns and the complete sortable header.
verifySeason(str_contains($html,'<col class="player-col"><col class="team-col">')&&str_contains($html,'scope="row" class="player-frozen-player"')&&str_contains($html,'class="player-owner player-frozen-team"'),'Frozen player/team columns need explicit classes and bounded column widths.');
verifySeason(str_contains($html,'thead th{position:sticky;top:0;z-index:3')&&str_contains($html,'overflow-x:auto;')&&!str_contains($html,'72dvh')&&str_contains($html,'id="season-player-fixed-header"')&&str_contains($html,'border-collapse:separate')&&str_contains($html,'thead .player-frozen-team{z-index:5}'),'The header and corner cells must stay above page-scrolling rows and horizontally scrolling stats.');
verifySeason(str_contains($html,'-webkit-line-clamp:2')&&str_contains($betaHtml,'title="Beta"'),'Team names must be limited to two lines with their full name in a tooltip.');
$availableHtml=seasonRequest('/players?availability=available')->getContent();
verifySeason(substr_count($availableHtml,'class="player-add-icon"')===21&&str_contains($availableHtml,'searchName=Player%2002;statusOrTeamFilter=ALL_AVAILABLE;positionOrGroup=ALL;pageNumber=1;'),'Every unowned player needs an encoded Fantrax search link that includes free agents and waivers.');
verifySeason(!str_contains($betaHtml,'class="player-add-icon"'),'Owned players must not have an add/claim icon.');
verifySeason(str_contains($advancedHtml,'id="season-player-line" class="player-buttons" role="group"')&&str_contains($advancedHtml,'id="season-player-pp" class="player-buttons" role="group"')&&!str_contains($advancedHtml,'<select id="season-player-line"')&&!str_contains($advancedHtml,'<select id="season-player-pp"'),'Line and power-play slicers must use accessible buttons.');
preg_match('/id="season-player-line".*?<\/div>/s',$advancedHtml,$lineButtons);preg_match('/id="season-player-pp".*?<\/div>/s',$advancedHtml,$ppButtons);
verifySeason(str_contains($lineButtons[0],'aria-pressed="true"')&&str_contains($lineButtons[0],'rookies=1')&&str_contains($lineButtons[0],'line=1%2C2&amp;pp=1')&&str_contains($ppButtons[0],'line=1&amp;pp=1%2C2'),'Assignment buttons must indicate selection and preserve the other filters.');
verifySeason(str_contains($betaHtml,'aria-label="Beta"')&&str_contains($betaHtml,'class="player-team-name"')&&str_contains($betaHtml,'--team-column-width:44px')&&str_contains($betaHtml,'.player-team-link .player-team-name{display:none}'),'Mobile must keep accessible team logos while hiding team names in a narrow frozen column.');
DB::table('season_player_stats')->where('player_id','p1')->update(['season_fpts'=>1234.5]);
$roundedHtml=seasonRequest('/players?availability=all&q=Player%20%3Cunsafe%3E')->getContent();
verifySeason(preg_match('/<td class="myproj">6\.25<\/td>\s*<td>1,235<\/td>\s*<td>9\.90<\/td>/', $roundedHtml),'FPts must display whole numbers with grouping while ECFHL Score and FPts/gp retain decimals.');
// Dataset values must come from the selected source, including sort and paging.
for($i=1;$i<=62;$i++) {
 $row=$projection;$row['player_id']='p'.$i;$row['projected_fpts_per_game']=$i===1?6.25:9-$i/100;
 foreach([7,14,21] as $days) { $row['fpts_'.$days.'d']=$days*100-$i;$row['gp_'.$days.'d']=$i%5+1;$row['fpts_per_game_'.$days.'d']=$row['fpts_'.$days.'d']/$row['gp_'.$days.'d']; }
 DB::table('player_projections')->updateOrInsert(['player_id'=>'p'.$i],$row);
 DB::table('player_projection_baselines')->insert(['player_id'=>'p'.$i,'season_id'=>'2026-27','player_name'=>'Baseline '.$i,'nhl_team'=>'MTL','position'=>$i>60?'G':'F','source_rank'=>$i,'fantrax_fpts_per_game'=>5+$i/10,'fantrax_season_fpts'=>1000-$i*2,'season_start'=>'2026-09-13','captured_at'=>'2026-10-01 04:00:00']);
}
foreach([7,14,21] as $days) {
 $window=$service->data(Request::create('/players?availability=all&dataset='.$days.'d&sort=fpts&direction=desc'));
 verifySeason($window['dataset']===$days.'d'&&$window['players']->total()===60&&$window['columns']===[]&&array_keys($window['headers'])===['rank','player','team','ec_proj','fpts','fpts_gp','today','tomorrow','gp'],'Recent datasets must use tracked players and their available columns.');
 $first=$window['players'][0];verifySeason($first->player_id==='p1'&&(float)$first->dataset_fpts===$days*100.0-1&&(int)$first->dataset_gp===2&&abs((float)$first->dataset_fpts_per_game-($days*100-1)/2)<.00001,'Recent values or SQL sort used season stats: '.$days);
 verifySeason(str_contains($window['players']->nextPageUrl(),'dataset='.$days.'d'),'Show More lost dataset: '.$days);
}
$fantrax=$service->data(Request::create('/players?availability=all&dataset=fantrax&sort=fpts_gp&direction=desc'));
verifySeason($fantrax['players']->total()===60&&$fantrax['players'][0]->player_id==='p60'&&(float)$fantrax['players'][0]->dataset_fpts===880.0&&(float)$fantrax['players'][0]->dataset_fpts_per_game===11.0&&!isset($fantrax['headers']['gp'])&&$fantrax['columns']===[],'Fantrax dataset must show frozen totals/rates and omit unrecorded GP/category stats.');
verifySeason($fantrax['statsThrough']==='2026-10-01 04:00:00','Fantrax date must use baseline capture time.');
$windowGoalies=$service->data(Request::create('/players?availability=all&dataset=7d&positions=G'));
verifySeason($windowGoalies['players']->total()===2&&$windowGoalies['columns']===[],'Recent dataset must exclude untracked goalies and season goalie categories.');
$combo=$service->data(Request::create('/players?dataset=14d&team=beta&availability=taken&line=1&pp=1&sort=fpts_gp&direction=asc'));
verifySeason($combo['players']->total()===33&&$combo['players']->getCollection()->every(fn($p)=>$p->fantasy_team_id==='beta'&&(int)$p->line_number===1&&(int)$p->pp_unit===1),'Dataset lost current ownership or assignment filters.');
$comboMore=json_decode(seasonRequest('/players?dataset=14d&team=beta&availability=taken&line=1&pp=1&sort=fpts_gp&direction=asc&page=2',true)->getContent(),true);
verifySeason($comboMore['shown']===33&&$comboMore['html']!==''&&!str_contains($comboMore['html'],'<td>1,235</td>'),'Recent Show More must render the selected dataset.');
preg_match_all('/class="player-frozen-rank">(\d+)<\/td>/',$html,$ranks);verifySeason(array_map('intval',$ranks[1])===range(1,25),'Initial rank must start at 1 and follow current order.');
preg_match_all('/class="player-frozen-rank">(\d+)<\/td>/',$json['html'],$ranks);verifySeason(array_map('intval',$ranks[1])===range(26,50),'Ranks must continue across Show More pages.');
preg_match_all('/class="player-frozen-rank">(\d+)<\/td>/',$comboMore['html'],$ranks);verifySeason(array_map('intval',$ranks[1])===range(26,33),'Filtered ranks must follow pagination, without gaps.');
$datasetHtml=seasonRequest('/players?dataset=7d&team=beta&availability=taken&line=1&pp=1&sort=fpts_gp&direction=asc')->getContent();
verifySeason(str_contains($datasetHtml,'id="season-player-dataset"')&&str_contains($datasetHtml,'name="dataset" value="7d"')&&str_contains($datasetHtml,'dataset=21d')&&str_contains($datasetHtml,'remaining weights scale to 100%')&&str_contains($datasetHtml,'actual FPts'),'Dataset buttons, source scope and form preservation failed.');
verifySeason(str_contains($datasetHtml,'<col class="rank-col">')&&str_contains($datasetHtml,'class="player-frozen-rank"')&&str_contains($datasetHtml,'padding:6px 7px')&&str_contains($datasetHtml,'--player-stat-width:72px'),'Rank pane and compact table spacing missing.');
$invalidDataset=$service->data(Request::create('/players?availability=all&dataset=invalid&sort=rank'));verifySeason($invalidDataset['dataset']==='season'&&$invalidDataset['sort']==='fpts','Unknown dataset and positional rank sort must safely fall back.');
$categoryFallback=$service->data(Request::create('/players?availability=all&dataset=7d&sort=G'));verifySeason($categoryFallback['sort']==='fpts','A category sort must reset when it is unavailable in the new dataset.');
// Ownership highlighting follows the signed-in account, never the selected team filter.
$owner = new \App\Models\User(['name'=>'Beta owner']);
$owner->setRelation('claim', new \App\Models\TeamClaim(['fantasy_team_id'=>'beta','team_name'=>'Beta']));
\Illuminate\Support\Facades\Auth::guard()->setUser($owner);
$ownedHtml=seasonRequest('/players?availability=all&team=beta')->getContent();
verifySeason(substr_count($ownedHtml,' player-on-my-team"')===25&&str_contains($ownedHtml,'legend-own'),'Signed-in team must highlight every owned player and show its legend.');
$ownedMore=seasonRequest('/players?availability=all&team=beta&page=2',true);
$ownedJson=json_decode($ownedMore->getContent(),true);
verifySeason(substr_count($ownedJson['html'],' player-on-my-team"')===9&&str_contains($ownedMore->headers->get('Cache-Control'),'no-store'),'Show More must preserve personalized row highlights without shared caching.');
$otherHtml=seasonRequest('/players?availability=all&team=gamma')->getContent();
verifySeason(!str_contains($otherHtml,' player-on-my-team"'),'Selecting a different ECFHL team must not highlight that roster as your own.');
verifySeason(str_contains($ownedHtml,'data-team-icon-viewer data-team-slug="beta" data-team-name="Beta"')&&str_contains($ownedHtml,'data-full-src="/team-icons/beta"'),'Player logos must open the full-size team viewer.');
preg_match('/<nav class="mobile-primary-nav".*?<\/nav>/s',$ownedHtml,$mobileNav);
verifySeason(str_contains($mobileNav[0],'mobile-nav-players active')&&!str_contains($mobileNav[0],'/daily-targets')&&!str_contains($ownedHtml,'href="/daily-targets"'),'Players must replace the bottom Targets shortcut and Targets must be absent from navigation.');
verifySeason(str_contains($ownedHtml,'id="team-icon-modal-title"')&&str_contains($ownedHtml,'id="team-icon-modal-view-team"')&&str_contains($ownedHtml,'/team-image-viewer.js?v=10'),'Shared titled viewer and View Team action missing.');
$unclaimed = new \App\Models\User(['name'=>'Unclaimed']);$unclaimed->setRelation('claim',null);
\Illuminate\Support\Facades\Auth::guard()->setUser($unclaimed);
verifySeason(!str_contains(seasonRequest('/players?availability=all')->getContent(),' player-on-my-team"'),'Unclaimed accounts must not highlight free agents.');
\Illuminate\Support\Facades\Auth::guard()->forgetUser();
verifySeason(!str_contains(seasonRequest('/players?availability=all')->getContent(),' player-on-my-team"'),'Guests must not inherit an owner highlight.');
// Today follows the same Pacific fantasy date as Daily Targets, including midnight in Atlantic time.
\Carbon\CarbonImmutable::setTestNow(\Carbon\CarbonImmutable::parse('2026-10-05T05:00:00Z'));
foreach(['p4'=>'SJ','p5'=>'NJD','p6'=>'VGK','p7'=>'SEA','p8'=>'OTT','p41'=>'SJS','p42'=>'SEA'] as $id=>$team)DB::table('season_player_stats')->where('player_id',$id)->update(['nhl_team'=>$team]);
foreach([['2026-10-04','MTL','@TOR'],['2026-10-05','SJ','LA'],['2026-10-03','SEA','OTT'],['2026-10-04','SEA',''],['2026-10-04','SEA','Final']] as [$date,$team,$opponent])DB::table('active_daily_players')->insert(['game_date'=>$date,'team'=>$team,'opponent'=>$opponent,'player_name'=>$date.$team.$opponent,'position'=>'F','availability'=>'FA']);
DB::table('active_fantasy_rosters')->where('player_id','p5')->update(['nhl_team'=>'NJD','opponent'=>'NYR']);
DB::table('active_starting_goalies')->insert(['game_date'=>'2026-10-04','team'=>'VGK','opponent'=>'ANA','player_name'=>'Starter','source_url'=>'https://example.com','checked_at'=>now()]);
DB::table('todays_odds')->insert(['game_date'=>'2026-10-05','team'=>'SEA','opponent'=>'OTT']);
$today=$service->data(Request::create('/players?playing=today'));
verifySeason($today['playingDate']==='2026-10-04'&&$today['players']->total()===19&&$today['players']->getCollection()->every(fn($p)=>$p->nhl_team==='MTL'&&$p->fantasy_team_id===null),'Playing today must combine with Available and ignore wrong-day, blank and invalid opponent rows.');
$tomorrow=$service->data(Request::create('/players?playing=tomorrow'));
verifySeason($tomorrow['playingDate']==='2026-10-05'&&$tomorrow['players']->total()===2&&$tomorrow['players']->getCollection()->pluck('player_id')->sort()->values()->all()===['p41','p42'],'Tomorrow must use the next Pacific date and both sides of stored NHL matchups.');
$bothDays=$service->data(Request::create('/players?playing=both'));
verifySeason($bothDays['players']->total()===21&&$bothDays['players']->getCollection()->pluck('player_id')->unique()->count()===21,'Both game-day buttons must use a deduplicated OR of today and tomorrow.');
$bothHtml=seasonRequest('/players?playing=both')->getContent();
$bothDoc=new DOMDocument();@$bothDoc->loadHTML($bothHtml);$bothPath=new DOMXPath($bothDoc);
verifySeason($bothPath->query('//div[@id="season-player-playing"]/a[@aria-pressed="true"]')->length===2,'Both selected days must show their selected states.');
$takenTomorrow=$service->data(Request::create('/players?playing=tomorrow&availability=taken'));
verifySeason($takenTomorrow['players']->getCollection()->pluck('player_id')->sort()->values()->all()===['p3','p4','p7','p8'],'Game-day slicer must work for owned players and normalize NHL aliases.');
$allToday=$service->data(Request::create('/players?playing=today&availability=all&sort=ec_proj&direction=asc'));
verifySeason($allToday['players']->total()===54&&str_contains($allToday['players']->nextPageUrl(),'playing=today'),'Filter games before SQL sorting/pagination and preserve day on Show More.');
$todayMore=json_decode(seasonRequest('/players?playing=today&availability=all&sort=ec_proj&direction=asc&page=2',true)->getContent(),true);
verifySeason($todayMore['shown']===50&&$todayMore['total']===54&&str_contains($todayMore['next_url'],'playing=today'),'Game-day pagination lost ranks or filter scope.');
foreach(['season','7d','14d','21d','fantrax'] as $source){$window=$service->data(Request::create('/players?playing=tomorrow&dataset='.$source));verifySeason($window['players']->total()===2,'Game-day/Available combination failed for dataset '.$source);}
$dayHtml=seasonRequest('/players?playing=tomorrow&dataset=14d&dfo_sort=1')->getContent();
verifySeason(str_contains($dayHtml,'id="season-player-playing"')&&substr_count($dayHtml,'name="playing" value="tomorrow"')===2&&str_contains($dayHtml,'playing=tomorrow')&&str_contains($dayHtml,'playing=all')&&str_contains($dayHtml,'availability=available'),'Day buttons, search/team/sort forms and resetting advanced filters must preserve defaults.');
$reset=$service->data(Request::create('/players?playing=invalid&availability=invalid'));verifySeason($reset['playing']==='all'&&$reset['availability']==='available','Invalid slicers must fall back to All game days and Available.');
$defaultHtml=seasonRequest('/players')->getContent();
verifySeason(str_contains($html,'player-assignment-tag player-assignment-2">L2</span>')&&str_contains($html,'player-assignment-tag player-assignment-2">PP2</span>')&&!str_contains($html,'Pair2'),'Defensemen and PP assignments must use colored L/PP stickers.');preg_match('/id="season-player-playing".*?<\/div>/s',$defaultHtml,$dayButtons);preg_match('/aria-label="Player availability".*?<\/div>/s',$defaultHtml,$availabilityButtons);
verifySeason(str_contains($defaultHtml,'id="season-player-playing"')&&preg_match('/aria-pressed="true"[^>]*>Available<\/a>/',$availabilityButtons[0]),'Day buttons must always be visible; Available remains the default.');
// Daily Targets mode ranks the entire pool before slicing pages, independent of manual direction.
foreach (['active_line_combinations','active_pp_lines','active_daily_players','active_available_goalies','active_starting_goalies','player_projections'] as $table) DB::table($table)->delete();
$date=app(\App\Support\FantasyDay::class)->today()->toDateString();
foreach (['p1'=>100,'p2'=>200,'p3'=>3,'p4'=>5,'p6'=>0,'p7'=>5,'p60'=>10] as $id=>$score) {
 DB::table('player_projections')->insert(array_merge($projection,['player_id'=>$id,'projected_fpts_per_game'=>$score]));
}
foreach (['p1'=>[1,2],'p2'=>[2,1],'p3'=>[1,1],'p4'=>[1,1],'p5'=>[1,1],'p6'=>[1,1],'p7'=>[1,1],'p60'=>[1,1]] as $id=>[$unit,$line]) {
 $row=DB::table('season_player_stats')->where('player_id',$id)->first();
 DB::table('active_line_combinations')->insert(['team'=>$row->nhl_team,'player_name'=>$row->player_name,'position_group'=>$row->position,'line_number'=>$line,'source_url'=>'https://example.com','last_update'=>now()]);
 DB::table('active_pp_lines')->insert(['team'=>$row->nhl_team,'player_name'=>$row->player_name,'pp_unit'=>$unit,'source_url'=>'https://example.com','last_update'=>now()]);
 if (in_array($id,['p4','p7'])) DB::table('active_daily_players')->insert(['game_date'=>$date,'team'=>$row->nhl_team,'player_name'=>$row->player_name,'source_rank'=>$id==='p7'?1:2]);
}
$dfoUrl='/players?availability=all&positions=F,D&sort=ec_proj&direction=desc&dfo_sort=1';
$dfo=$service->data(Request::create($dfoUrl));
verifySeason($dfo['dailyTargetsSort']&&$dfo['players']->getCollection()->take(8)->pluck('player_id')->all()===['p1','p60','p7','p4','p3','p6','p5','p2'],'Daily Targets skaters must prioritize PP, projection (null last), source rank, then name before pagination.');
$descending=$service->data(Request::create(str_replace('direction=desc','direction=asc',$dfoUrl)));
verifySeason($descending['players']->getCollection()->take(8)->pluck('player_id')->all()===['p6','p3','p7','p4','p60','p1','p5','p2'],'Ascending score must apply within the same PP priority groups.');
$allIds=[];
foreach ([1,2,3] as $page) $allIds=array_merge($allIds,$service->data(Request::create($dfoUrl.'&page='.$page))['players']->getCollection()->pluck('player_id')->all());
verifySeason(count($allIds)===60&&count(array_unique($allIds))===60&&str_contains($dfo['players']->nextPageUrl(),'dfo_sort=1'),'Daily Targets pagination must preserve mode with no missing or duplicated players.');
$off=$service->data(Request::create(str_replace('dfo_sort=1','dfo_sort=0',$dfoUrl)));
verifySeason(!$off['dailyTargetsSort']&&$off['sort']==='ec_proj'&&$off['direction']==='desc'&&$off['players'][0]->player_id!=='p60','Turning the toggle off must restore the chosen column sort.');
$dfoFiltered=$service->data(Request::create($dfoUrl.'&rookies=1&line=1&pp=1'));
verifySeason($dfoFiltered['players']->total()===3&&$dfoFiltered['players']->getCollection()->pluck('player_id')->all()===['p60','p3','p6'],'Daily Targets mode must preserve and apply existing filters.');
foreach (['p61'=>['MTL','confirmed'],'p62'=>['TOR','likely'],'p63'=>['BOS','unconfirmed'],'p64'=>['SEA',null],'p65'=>['MTL',null]] as $id=>[$team,$status]) {
 DB::table('season_player_stats')->where('player_id',$id)->update(['nhl_team'=>$team]);
 $row=DB::table('season_player_stats')->where('player_id',$id)->first();
 DB::table('player_projections')->insert(array_merge($projection,['player_id'=>$id,'projected_fpts_per_game'=>(int)substr($id,1)]));
 if($status) DB::table('active_starting_goalies')->insert(['game_date'=>$date,'team'=>$team,'player_name'=>$row->player_name,'starting_status'=>$status,'source_url'=>'https://example.com','checked_at'=>now()]);
}
$dfoGoalies=$service->data(Request::create('/players?availability=all&positions=G&dfo_sort=1'));
verifySeason($dfoGoalies['players']->getCollection()->pluck('player_id')->all()===['p61','p62','p63','p64','p65'],'Goalies must follow confirmed, likely, not confirmed, NA, not starting, with ECFHL Score within each group.');
$dfoHtml=seasonRequest($dfoUrl)->getContent();
$dfoDoc=new DOMDocument;@$dfoDoc->loadHTML($dfoHtml);$dfoPath=new DOMXPath($dfoDoc);
$toggle=$dfoPath->query('//a[@aria-label="Daily Faceoff priority sorting"]')->item(0);
verifySeason($toggle&&$toggle->getAttribute('aria-pressed')==='true'&&str_contains($toggle->getAttribute('href'),'dfo_sort=0')&&$dfoPath->query('.//img[contains(@src,"dailyfaceoff-icon")]',$toggle)->length===1&&!str_contains($dfoHtml,'Daily Targets priority'),'Logo toggle must show its state, offer off, and summarize the active sort.');
$dayButtons=$dfoPath->query('//div[@id="season-player-playing"]/a');
verifySeason($dayButtons->length===2&&$dayButtons->item(0)->getAttribute('aria-pressed')==='false'&&$dayButtons->item(1)->getAttribute('aria-pressed')==='false','Enabling Daily Faceoff shows two initially unselected day buttons.');
verifySeason($dayButtons->item(0)->textContent==='Playing Today'&&$dayButtons->item(1)->textContent==='Playing Tomorrow','Day buttons must have the requested labels.');
verifySeason(str_contains($dayHtml,'playing=all')&&str_contains($dayHtml,'dfo_sort=1'),'Selected game day can be cleared while keeping Daily Faceoff mode.');
verifySeason($dfoPath->query('//th[@class="myproj" and @aria-sort="descending"]')->length===1,'Daily Targets mode must show the active secondary column sort.');
verifySeason($dfoPath->query('//form[@class="player-search"]//input[@name="dfo_sort" and @value="1"]')->length===1&&$dfoPath->query('//form[@class="player-slicers"]//input[@name="dfo_sort" and @value="1"]')->length===1&&$dfoPath->query('//form[@aria-label="Player sorting"]//input[@name="dfo_sort"]')->length===0,'Search/team must retain Daily Targets mode; explicit column Sort must clear it.');
verifySeason(str_contains($dfoHtml,'filter:grayscale(1);opacity:.35')&&str_contains($dfoHtml,'img{filter:none;opacity:1}'),'Logo states must use CSS grayscale/opacity and restore full color.');
\Carbon\CarbonImmutable::setTestNow();
echo "Season players checks passed: Available/All defaults, both game-day filters and Pacific rollover, four schedule sources, aliases, combined datasets/sorts/pagination, compact panes and owner highlighting.\n";
