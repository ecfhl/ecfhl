<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxDailyMoves
{
    public const LEAGUE_ID='092zcn40molvao69';
    private const API_VERSION='186.1.9';

    public function fetch(CarbonImmutable $date): array
    {
        $url='https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/transactions/history;maxResultsPerPage=250;view=CLAIM_DROP;pageNumber=1;executedOnly=true;includeDeleted=false';
        $payload=[
            'msgs'=>[[
                'method'=>'getTransactionDetailsHistory',
                'data'=>[
                    'leagueId'=>self::LEAGUE_ID,
                    'maxResultsPerPage'=>'250',
                    'executedOnly'=>true,
                    'includeDeleted'=>false,
                    'view'=>'CLAIM_DROP',
                    'pageNumber'=>'1',
                ],
            ]],
            'uiv'=>3,
            'refUrl'=>$url,
            'dt'=>0,
            'at'=>0,
            'av'=>'0.0',
            'tz'=>'America/Halifax',
            'v'=>self::API_VERSION,
        ];

        $response=Http::timeout(45)->retry(2,1200)->withHeaders([
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept'=>'application/json',
            'Content-Type'=>'application/json',
            'Referer'=>$url,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID,$payload);
        $response->throw();

        $data=$response->json('responses.0.data');
        if(!is_array($data))throw new RuntimeException('Fantrax transaction history returned no response data.');

        $rows=$data['table']['rows']??[];
        if(!is_array($rows))throw new RuntimeException('Fantrax transaction history returned no rows.');

        $teamNames=[];
        foreach(($data['displayedLists']['teams']??[]) as $team){
            $id=trim((string)($team['id']??''));
            $name=trim((string)($team['name']??''));
            if($id!=='')$teamNames[$id]=$name;
        }

        $target=$date->toDateString();
        $counts=[];
        $groupTeam=[];
        $groupDate=[];

        foreach($rows as $row){
            if(!is_array($row) || empty($row['executed']) || !empty($row['deleted']))continue;
            if(strtoupper(trim((string)($row['transactionCode']??'')))!=='CLAIM')continue;

            $txSetId=(string)($row['txSetId']??'');
            $teamId='';
            $teamName='';
            $processedDate=null;

            foreach(($row['cells']??[]) as $cell){
                if(!is_array($cell))continue;
                $key=(string)($cell['key']??'');

                if($key==='team'){
                    $teamId=trim((string)($cell['teamId']??''));
                    $teamName=trim(strip_tags((string)($cell['content']??'')));
                    if($txSetId!=='' && $teamId!=='')$groupTeam[$txSetId]=[$teamId,$teamName];
                } elseif($key==='date'){
                    $processedDate=$this->parseDate((string)($cell['content']??''),(string)($cell['toolTip']??''));
                    if($txSetId!=='' && $processedDate)$groupDate[$txSetId]=$processedDate;
                }
            }

            if(($teamId==='' || $teamName==='') && $txSetId!=='' && isset($groupTeam[$txSetId])){
                [$teamId,$teamName]=$groupTeam[$txSetId];
            }
            if(!$processedDate && $txSetId!=='' && isset($groupDate[$txSetId]))$processedDate=$groupDate[$txSetId];

            if(!$processedDate || $processedDate->setTimezone('America/Halifax')->toDateString()!==$target)continue;
            if($teamId==='')continue;

            if($teamName==='')$teamName=$teamNames[$teamId]??$teamId;
            if(!isset($counts[$teamId]))$counts[$teamId]=['fantasy_team_id'=>$teamId,'fantasy_team_name'=>$teamName,'moves_used'=>0];
            $counts[$teamId]['moves_used']++;
        }

        foreach($teamNames as $teamId=>$teamName){
            if(!isset($counts[$teamId])){
                $counts[$teamId]=['fantasy_team_id'=>$teamId,'fantasy_team_name'=>$teamName,'moves_used'=>0];
            }
        }

        foreach($counts as &$row)$row['moves_left']=max(0,7-(int)$row['moves_used']);
        unset($row);

        return array_values($counts);
    }

    private function parseDate(string $content,string $tooltip): ?CarbonImmutable
    {
        $candidates=[];
        $text=trim(html_entity_decode(strip_tags($content)));
        if($text!=='')$candidates[]=$text;

        if($tooltip!==''){
            $plain=trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'],' ', $tooltip))));
            if(preg_match('/Processed\s+(.+)/i',$plain,$m))$candidates[]=trim($m[1]);
        }

        foreach($candidates as $value){
            $value=preg_replace('/^(Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+/i','',$value);
            foreach(['M j, Y, g:iA','M j, Y, g:i A','M j, Y, g:i:s A','F j, Y, g:iA','F j, Y, g:i:s A'] as $format){
                try{
                    $dt=CarbonImmutable::createFromFormat('!'.$format,$value,'America/Halifax');
                    if($dt)return $dt;
                }catch(\Throwable){}
            }
        }
        return null;
    }
}
