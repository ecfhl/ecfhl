<?php
// Runs after archive-smoke.php has populated its disposable SQLite database.
use App\Support\TradeContracts;

$records=TradeContracts::records();
check(count($records)===604,'Expected all 604 observed numeric trade/player contracts');
$keys=[];
foreach($records as $c){
    check(is_int($c['contract_years_at_trade']) && $c['status']==='verified_numeric','Unverified contract in backfill');
    $key=$c['trade_id'].'|'.$c['source_side'].'|'.$c['player_name'];
    check(!isset($keys[$key]),'Duplicate contract identity');$keys[$key]=true;
}
$migration=require __DIR__.'/../database/migrations/2026_09_26_000004_backfill_verified_trade_contracts.php';
$migration->up();
check((int)\Illuminate\Support\Facades\DB::table('trade_assets')->where('trade_asset_id','TR0261_from_1')->value('contract_years_at_trade')===4,'Historical asset was not backfilled');
$count=\Illuminate\Support\Facades\DB::table('trade_assets')->whereNotNull('contract_years_at_trade')->count();
$migration->up();
check($count===\Illuminate\Support\Facades\DB::table('trade_assets')->whereNotNull('contract_years_at_trade')->count(),'Backfill is not idempotent');
check(\Illuminate\Support\Facades\DB::table('trade_assets')->where('trade_asset_id','TR0001_from_1')->value('contract_years_at_trade')===null,'Missing historical contract was inferred');

$history=['trades_by_season'=>[]];
foreach(['TR0261'=>['2017-18',['Nico Hischier'],['Unknown Player']], 'TR0482'=>['2025-26',['Cutter Gauthier','2026 Draft Pick round 1 (Brasse Camarade)'],['Matt Boldy']]] as $id=>[$season,$from,$to]){
    $sourceId=\Illuminate\Support\Facades\DB::table('trades')->where('trade_id',$id)->value('source_trade_id');
    $history['trades_by_season'][$season][]=['id'=>$sourceId?:$id,'from_items'=>$from,'to_items'=>$to];
}
\Illuminate\Support\Facades\DB::table('source_cache')->updateOrInsert(['source_key'=>'history'],['source_url'=>'https://ecfhl.win/trades','payload'=>json_encode($history)]);
$app->forgetScopedInstances();$app->instance('request',\Illuminate\Http\Request::create('/trades?type=all'));
$data=$app->make(\App\Support\Archive::class);
$trades=array_column($data->trades(),null,'id');
check($trades['TR0261']['from_items']===['Nico Hischier (4 Years)'],'Source description erased the DB contract');
check($trades['TR0482']['to_items']===['Matt Boldy (3 Years)'],'Source-only player contract missing');
check($trades['TR0482']['from_items']===['Cutter Gauthier','2026 Draft Pick round 1 (Brasse Camarade)'],'Unknown contract or draft pick changed');
check(TradeContracts::label('Player (2 Years)',1)==='Player (1 Year)','Repeated contract suffix or singular label incorrect');
$html=view('partials.trade-card',['t'=>$trades['TR0482']])->render();
check(str_contains($html,'Matt Boldy (3 Years)'),'Contract not rendered in trade card');
echo "Trade contracts passed: numeric evidence, backfill, idempotence, missing contracts, source merge, and rendered labels.\n";
