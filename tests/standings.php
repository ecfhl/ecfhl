<?php
// Real public Fantrax regular-season response; no network or production writes.
putenv('SESSION_DRIVER=array');putenv('CACHE_STORE=array');putenv('APP_ENV=testing');
putenv('APP_KEY=base64:'.base64_encode(str_repeat('x',32)));
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
set_exception_handler(function(Throwable $e){fwrite(STDERR,$e->getMessage()."\n".$e->getTraceAsString()."\n");exit(1);});

use App\Support\FantraxStandings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

function assertStanding(bool $ok,string $message):void{if(!$ok)throw new RuntimeException($message);}
$fixture=json_decode(file_get_contents(__DIR__.'/fixtures/fantrax-regular-standings.json'),true,512,JSON_THROW_ON_ERROR);
$collector=new FantraxStandings;
$rows=$collector->parse($fixture);
$teams=array_column($rows,null,'team_id');
assertStanding(count($rows)===14,'All 14 current teams must be collected.');
assertStanding($rows[0]['team_name']==='One Man Bang 💥'&&$rows[0]['fantasy_points_for']===40.0,'Official leader and FPts lost.');
assertStanding($teams['65yfc2nwmolvao6q']['rank']===5&&$teams['65yfc2nwmolvao6q']['fantasy_points_for']===34.0,'Lone Tsar regression: must use official rank 5 / 34 FPts, not reconstructed rank 9 / 29.');
assertStanding($rows[2]['team_name']==='Mullet Mafia'&&$rows[3]['team_name']==='North Shore Explorers'&&$rows[2]['fantasy_points_for']===$rows[3]['fantasy_points_for'],'Preserve Fantrax tie-breaking, not alphabetical ordering.');
foreach($rows as $row)assertStanding($row['w']===0&&$row['l']===0&&$row['t']===0&&$row['standings_points']===0.0,'Open-period results must remain the official unfinished 0–0–0.');

// Future/schedule tables cannot replace the primary regular-season standings.
$extra=$fixture;$extra['tableList'][]=['subCaption'=>'Future period','rows'=>[['cells'=>[['teamId'=>'future','content'=>'Future'],['content'=>'999999']]]]];
assertStanding($collector->parse($extra)===$rows,'Schedule rows entered regular-season standings.');
// Column indices are resolved by header keys, not their positions.
$reordered=$fixture;$table=&$reordered['tableList'][0];
$table['header']['cells']=array_reverse($table['header']['cells']);
$table['fixedHeader']['cells']=array_reverse($table['fixedHeader']['cells']);
foreach($table['rows'] as &$row){$row['cells']=array_reverse($row['cells']);$row['fixedCells']=array_reverse($row['fixedCells']);}unset($row,$table);
assertStanding($collector->parse($reordered)===$rows,'Fantrax column reorder changed values.');
$final=$fixture;
$final['tableList'][0]['rows'][0]['cells'][0]['content']='2';
$final['tableList'][0]['rows'][0]['cells'][1]['content']='1';
$final['tableList'][0]['rows'][0]['cells'][2]['content']='1';
$final['tableList'][0]['rows'][0]['cells'][3]['content']='5';
$final['tableList'][0]['rows'][0]['cells'][7]['content']='1,234.50';
$finalRows=$collector->parse($final);
assertStanding($finalRows[0]['w']===2&&$finalRows[0]['l']===1&&$finalRows[0]['t']===1&&$finalRows[0]['standings_points']===5.0&&$finalRows[0]['fantasy_points_for']===1234.5,'Official finalized W/L/T/Points and decimal/comma FPts lost.');
$zero=$fixture;$zero['tableList'][0]['rows'][0]['cells'][7]['content']='0';
assertStanding($collector->parse($zero)[0]['fantasy_points_for']===0.0,'True zero FPts must remain valid.');
$negative=$fixture;$negative['tableList'][0]['rows'][0]['cells'][7]['content']='-2.5';
assertStanding($collector->parse($negative)[0]['fantasy_points_for']===-2.5,'Negative point adjustment must remain valid.');
foreach(['projection','optimal','schedule','wrong-timeframe','missing-selection','missing-fpts','missing-header','incomplete','duplicate','invalid-record','invalid-rank'] as $case){
    $bad=$fixture;
    switch($case){
        case 'projection':$bad['displayedSelections']['proj']=true;break;
        case 'optimal':$bad['displayedSelections']['optimal']=true;break;
        case 'schedule':$bad['displayedSelections']['view']='SCHEDULE';break;
        case 'wrong-timeframe':$bad['displayedSelections']['timeframeType']='LAST_7_DAYS';break;
        case 'missing-selection':unset($bad['displayedSelections']['proj']);break;
        case 'missing-fpts':$bad['tableList'][0]['rows'][0]['cells'][7]['content']='—';break;
        case 'missing-header':unset($bad['tableList'][0]['header']['cells'][7]);break;
        case 'incomplete':array_pop($bad['tableList'][0]['rows']);break;
        case 'duplicate':$bad['tableList'][0]['rows'][1]['fixedCells'][1]['teamId']=$bad['tableList'][0]['rows'][0]['fixedCells'][1]['teamId'];break;
        case 'invalid-record':$bad['tableList'][0]['rows'][0]['cells'][0]['content']='1.5';break;
        case 'invalid-rank':$bad['tableList'][0]['rows'][0]['fixedCells'][0]['content']='0';break;
    }
    try{$collector->parse($bad);throw new LogicException('Invalid response accepted: '.$case);}catch(RuntimeException $e){assertStanding(str_contains($e->getMessage(),'Existing standings preserved'),'Wrong failure: '.$case);}
}
CarbonImmutable::setTestNow('2026-10-05T00:30:00Z');
Http::preventStrayRequests();$requests=0;
Http::fake(function($request)use($fixture,&$requests){
    $requests++;assertStanding(str_starts_with($request->url(),'https://www.fantrax.com/fxpa/req'),'Standings must use only Fantrax official data.');
    $message=$request->data()['msgs'][0];
    assertStanding($message['method']==='getStandings'&&$message['data']['view']==='REGULAR_SEASON'&&$message['data']['proj']===false&&$message['data']['optimal']===false,'Wrong upstream standings request.');
    return Http::response(['responses'=>[['data'=>$fixture]]]);
});
$result=$collector->fetch();
assertStanding($result['rows']===$rows&&$result['as_of_date']==='2026-10-04'&&$requests===1,'Publish official rows for the Pacific fantasy day without reconstructing NHL/daily scores.');
CarbonImmutable::setTestNow();
echo "Standings checks passed: real official 14-team fixture, Lone Tsar rank/FPts regression, Fantrax tie-breaking and finalized records, header reordering, future isolation, strict validation, Pacific date and one upstream request.\n";
