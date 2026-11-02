<?php
namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxTransactions
{
    public const VIEWS=['CLAIM_DROP','TRADE','LINEUP_CHANGE'];

    public function fingerprints(): array
    {
        $league=FantraxDailyMoves::LEAGUE_ID;
        $url='https://www.fantrax.com/fantasy/league/'.$league.'/transactions/history';
        $response=Http::timeout(35)->retry(2,1000)->withHeaders(['Accept'=>'application/json','Referer'=>$url])->post('https://www.fantrax.com/fxpa/req?leagueId='.$league,[
            'msgs'=>array_map(fn($view)=>['method'=>'getTransactionDetailsHistory','data'=>[
                'leagueId'=>$league,'pageNumber'=>1,'team'=>'ALL','positionOrGroup'=>'ALL','view'=>$view,'executedOnly'=>true,'includeDeleted'=>false,
            ]],self::VIEWS),
            'uiv'=>3,'refUrl'=>$url,'dt'=>0,'at'=>0,'av'=>'0.0','tz'=>'America/Vancouver','v'=>'186.1.9',
        ]);
        $response->throw();
        return $this->parse($response->json()??[]);
    }

    public function parse(array $payload): array
    {
        if(!empty($payload['pageError'])||count($payload['responses']??[])!==3)throw new RuntimeException('Fantrax transaction response incomplete; prior state preserved.');
        $hashes=[];
        foreach(self::VIEWS as $i=>$view){
            $data=$payload['responses'][$i]['data']??[];
            $rows=$data['table']['rows']??null;
            if(($data['displayedSelections']['view']??null)!==$view || !is_array($rows))throw new RuntimeException('Fantrax transaction table missing: '.$view);
            $canonical=[];
            foreach($rows as $row){
                if(empty($row['txSetId']))throw new RuntimeException('Fantrax transaction identifier missing.');
                // Player news, headshots and injury icons change without a transaction.
                $cells=[];
                foreach($row['cells']??[] as $cell)$cells[]=[
                    'key'=>$cell['key']??null,'content'=>$cell['content']??null,'teamId'=>$cell['teamId']??null,
                ];
                $canonical[]=['id'=>$row['txSetId'],'player'=>$row['scorer']['scorerId']??null,
                    'type'=>$row['transactionCode']??null,'result'=>$row['resultCode']??null,
                    'executed'=>$row['executed']??null,'deleted'=>$row['deleted']??false,'cells'=>$cells];
            }
            $hashes[$view]=hash('sha256',json_encode($canonical,JSON_THROW_ON_ERROR));
        }
        return $hashes;
    }
}
