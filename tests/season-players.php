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
$d=$service->data(Request::create('/players'));
verifySeason($d['positions']===['F','D']&&$d['players']->total()===60&&$d['players']->count()===25,'Defaults must show F/D only and 25 players.');
verifySeason($d['players'][0]->fantasy_team_name==='Beta'&&$d['players'][1]->fantasy_team_name===null,'Latest snapshot ownership / deduplicated roster join failed.');
verifySeason((float)$d['players'][0]->projected_fpts_per_game===6.25&&$d['players'][1]->projected_fpts_per_game===null,'MyProj must use saved custom rates and preserve untracked nulls.');
verifySeason(array_keys($d['columns'])===['G','A','Pts','PPG','SHG','GWG','SOG','TOI']&&$d['players'][0]->stats['TOI']==='71:11','Full stat columns / time values were lost.');
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
// Use values with different digit lengths, negatives, missing rates and tied minutes.
foreach(['p1'=>['A'=>'9','G'=>'2','Pt'=>'11','PPG'=>'2','SHG'=>'1','GWG'=>'2','SOG'=>'999','TOI'=>'71:11'], 'p2'=>['A'=>'100','G'=>'10','Pt'=>'110','PPG'=>'10','SHG'=>'3','GWG'=>'10','SOG'=>'1,000','TOI'=>'71:59'], 'p60'=>['A'=>'-2','G'=>'0','Pt'=>'-2','PPG'=>'0','SHG'=>'0','GWG'=>'0','SOG'=>'0','TOI'=>'105:01']] as $id=>$stats)DB::table('season_player_stats')->where('player_id',$id)->update(['stats_json'=>json_encode($stats)]);
DB::table('season_player_stats')->where('player_id','p2')->update(['season_gp'=>25,'season_fpts_per_game'=>20]);
foreach(['gp'=>'p2','fpts_gp'=>'p2','PPG'=>'p2','GWG'=>'p2','A'=>'p2','G'=>'p2','Pts'=>'p2','SHG'=>'p2','SOG'=>'p2','TOI'=>'p60','ec_proj'=>'p1'] as $key=>$first){
 $sorted=$service->data(Request::create('/players?sort='.$key.'&direction=desc'));
 verifySeason($sorted['players'][0]->player_id===$first, 'Descending full-dataset numeric sorting failed: '.$key);
 verifySeason(str_contains($sorted['players']->nextPageUrl(),'sort='.$key)&&str_contains($sorted['players']->nextPageUrl(),'direction=desc'), 'Show More lost sorting: '.$key);
}
$names=$service->data(Request::create('/players?sort=player&direction=asc'));verifySeason($names['players'][0]->player_id==='p2','Player sorting must use names alphabetically.');
$ascending=$service->data(Request::create('/players?sort=A&direction=asc'));verifySeason($ascending['players'][0]->player_id==='p60','Negative stats must sort before zero/positive stats.');
$minutes=$service->data(Request::create('/players?sort=TOI&direction=desc'));verifySeason($minutes['players'][1]->player_id==='p2'&&$minutes['players'][2]->player_id==='p1','TOI must compare seconds when minutes tie.');
$missing=$service->data(Request::create('/players?sort=ec_proj&direction=asc'));verifySeason($missing['players'][0]->player_id==='p1','Unavailable EC Proj must stay last when ascending.');
$teams=$service->data(Request::create('/players?sort=team&direction=asc'));verifySeason($teams['players'][0]->fantasy_team_name==='Beta','Team sorting must use displayed ECFHL team.');
$invalid=$service->data(Request::create('/players?sort=DROP%20TABLE&direction=INVALID'));verifySeason($invalid['sort']==='fpts'&&$invalid['direction']==='desc','Reject unknown SQL sort fields and directions.');
$sortedRookies=$service->data(Request::create('/players?positions=F,D&rookies=1&sort=A&direction=asc'));verifySeason($sortedRookies['players'][0]->player_id==='p60'&&$sortedRookies['players']->total()===20,'Sorting must preserve rookie/position filters.');
$kernel=$app->make(Illuminate\Contracts\Http\Kernel::class);
function seasonRequest($url,$json=false){global $app,$kernel;$app->forgetScopedInstances();$r=Request::create($url,'GET',[],[],[],['HTTP_ACCEPT'=>$json?'application/json':'text/html']);$response=$kernel->handle($r);$kernel->terminate($r,$response);verifySeason($response->getStatusCode()===200,'Players response failed: '.$response->getContent());return $response;}
$html=seasonRequest('/players')->getContent();
verifySeason(substr_count($html,'data-player-id=')===25&&str_contains($html,'Player &lt;unsafe&gt;')&&!str_contains($html,'Player <unsafe>'),'SSR row count / escaped names failed.');
verifySeason(str_contains($html,'EC Proj')&&str_contains($html,'71:11')&&str_contains($html,'/teams/current/beta')&&str_contains($html,'Free Agent'),'Stats / ownership / custom projection rendering failed.');
verifySeason(str_contains($html,'aria-pressed="true" href="/players?positions=D')&&str_contains($html,'aria-pressed="true" href="/players?positions=F')&&str_contains($html,'aria-pressed="false" href="/players?positions=F%2CD%2CG'),'Default filter button states failed.');
preg_match_all('/<th scope="col"[^>]*>.*?<a[^>]*>(.*?) <span/s',$html,$headerMatches);
verifySeason($headerMatches[1]===['Player','Team','EC Proj','FPts','FPts/gp','GP','G','A','Pts','PPG','SHG','GWG','SOG','TOI'],'Column order and labels must match the requested stats exactly.');
verifySeason(preg_match('/<td class="myproj">6\.25<\/td>\s*<td>99<\/td>\s*<td>9\.90<\/td>\s*<td>10<\/td>\s*<td>2<\/td>\s*<td>9<\/td>/', $html), 'Row values must follow EC Proj, FPts, FPts/gp, GP, G and A header order.');
$sortHtml=seasonRequest('/players?rookies=1&sort=A&direction=asc')->getContent();
verifySeason(str_contains($sortHtml,'aria-sort="ascending"')&&str_contains($sortHtml,'sort=A&amp;direction=desc')&&str_contains($sortHtml,'name="sort" value="A"'),'Sort arrows / toggle links / search preservation failed.');
$json=json_decode(seasonRequest('/players?positions=F,D&page=2',true)->getContent(),true);
verifySeason(substr_count($json['html'],'data-player-id=')===25&&$json['shown']===50&&$json['total']===60&&str_contains($json['next_url'],'positions=F%2CD'),'Show More must return next rows with preserved filters.');
$final=json_decode(seasonRequest('/players?positions=G&rookies=1',true)->getContent(),true);verifySeason($final['shown']===1&&$final['total']===1&&$final['next_url']===null,'Filtered Show More termination failed.');
$admin=view('admin.index')->render();foreach(['/admin/projections','/job-status','/admin/advisors','/admin/team-images'] as $url)verifySeason(str_contains($admin,'class="card admin-menu-card" href="'.$url.'"'),'Admin card missing: '.$url);
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
$invalidFilters=$service->data(Request::create('/players?team=unknown&availability=bad'));verifySeason($invalidFilters['selectedTeam']===''&&$invalidFilters['availability']==='all','Unknown filters must fall back to All.');
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
$pair=$service->data(Request::create('/players?positions=D&line=1&pp=2'));
verifySeason($pair['players']->total()===1&&$pair['players'][0]->player_id==='p4','Defense pairs must use the D assignments.');
$none=$service->data(Request::create('/players?positions=F,D,G&line=none&pp=none'));
verifySeason($none['players']->total()===25&&$none['players']->getCollection()->every(fn($p)=>$p->position!=='G'&&$p->line_number===null&&$p->pp_unit===null),'No listed assignment filters must include unassigned skaters and exclude goalies.');
$bad=$service->data(Request::create('/players?line=bad&pp=3'));verifySeason($bad['selectedLine']===''&&$bad['selectedPp']==='','Invalid assignment filters must fall back to All.');
$advancedHtml=seasonRequest('/players?line=1&pp=1&rookies=1')->getContent();
verifySeason(str_contains($advancedHtml,'class="player-advanced"  open')&&str_contains($advancedHtml,'id="season-player-line"')&&str_contains($advancedHtml,'id="season-player-pp"')&&str_contains($advancedHtml,'name="line" value="1"')&&str_contains($advancedHtml,'line=1&amp;pp=1'),'Active advanced filters must remain visible and survive search, sorting and positions.');
verifySeason(preg_match('/class="player-name-link"[^>]*>[^<]+<span class="rookie-tag">Rookie<\/span><\/a>/', $advancedHtml),'Rookie sticker must be beside the name, not on the metadata line.');
verifySeason(str_contains($html,'class="player-position-f"')&&str_contains($html,'class="player-position-d"')&&str_contains(seasonRequest('/players?positions=G')->getContent(),'class="player-position-g"'),'Every row must have its position shade, including goalie rows.');
verifySeason(str_contains($html,'class="player-advanced" >')&&!str_contains($html,'class="player-advanced"  open'),'Advanced filters should start collapsed without active settings.');
// Frozen panes must include both identity columns and the complete sortable header.
verifySeason(str_contains($html,'<col class="player-col"><col class="team-col">')&&str_contains($html,'scope="row" class="player-frozen-player"')&&str_contains($html,'class="player-owner player-frozen-team"'),'Frozen player/team columns need explicit classes and bounded column widths.');
verifySeason(str_contains($html,'thead th{position:sticky;top:0;z-index:3')&&str_contains($html,'overflow:auto;max-height:min(72dvh,720px)')&&str_contains($html,'border-collapse:separate')&&str_contains($html,'thead .player-frozen-team{z-index:5}'),'The header and corner cells must stay above vertically/horizontally scrolling rows.');
verifySeason(str_contains($html,'-webkit-line-clamp:2')&&str_contains($betaHtml,'title="Beta"'),'Team names must be limited to two lines with their full name in a tooltip.');
$availableHtml=seasonRequest('/players?availability=available')->getContent();
verifySeason(substr_count($availableHtml,'class="player-add-icon"')===21&&str_contains($availableHtml,'searchName=Player%2002;statusOrTeamFilter=ALL_AVAILABLE;positionOrGroup=ALL;pageNumber=1;'),'Every unowned player needs an encoded Fantrax search link that includes free agents and waivers.');
verifySeason(!str_contains($betaHtml,'class="player-add-icon"'),'Owned players must not have an add/claim icon.');
verifySeason(str_contains($advancedHtml,'id="season-player-line" class="player-buttons" role="group"')&&str_contains($advancedHtml,'id="season-player-pp" class="player-buttons" role="group"')&&!str_contains($advancedHtml,'<select id="season-player-line"')&&!str_contains($advancedHtml,'<select id="season-player-pp"'),'Line and power-play slicers must use accessible buttons.');
preg_match('/id="season-player-line".*?<\/div>/s',$advancedHtml,$lineButtons);preg_match('/id="season-player-pp".*?<\/div>/s',$advancedHtml,$ppButtons);
verifySeason(str_contains($lineButtons[0],'aria-pressed="true"')&&str_contains($lineButtons[0],'rookies=1')&&str_contains($lineButtons[0],'line=2&amp;pp=1')&&str_contains($ppButtons[0],'line=1&amp;pp=2'),'Assignment buttons must indicate selection and preserve the other filters.');
verifySeason(str_contains($betaHtml,'aria-label="Beta"')&&str_contains($betaHtml,'class="player-team-name"')&&str_contains($betaHtml,'--team-column-width:64px')&&str_contains($betaHtml,'.player-team-link .player-team-name{display:none}'),'Mobile must keep accessible team logos while hiding team names in a narrow frozen column.');
DB::table('season_player_stats')->where('player_id','p1')->update(['season_fpts'=>1234.5]);
$roundedHtml=seasonRequest('/players?q=Player%20%3Cunsafe%3E')->getContent();
verifySeason(preg_match('/<td class="myproj">6\.25<\/td>\s*<td>1,235<\/td>\s*<td>9\.90<\/td>/', $roundedHtml),'FPts must display whole numbers with grouping while EC Proj and FPts/gp retain decimals.');
echo "Season players checks passed: stats, sorting, assignment buttons, pagination, frozen panes, integer FPts, mobile team logos and Fantrax add searches.\n";
