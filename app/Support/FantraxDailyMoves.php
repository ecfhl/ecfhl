<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxDailyMoves
{
    public const LEAGUE_ID='092zcn40molvao69';
    private const API_VERSION='186.1.9';

    public function fetch(CarbonImmutable $date, ?CarbonImmutable $periodStart=null): array
    {
        $rostersResponse=Http::timeout(45)->retry(2,1200)->withHeaders([
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept'=>'application/json',
        ])->get('https://www.fantrax.com/fxea/general/getTeamRosters',[
            'leagueId'=>self::LEAGUE_ID,
        ]);
        $rostersResponse->throw();
        $rosters=$rostersResponse->json('rosters');
        if(!is_array($rosters))throw new RuntimeException('Fantrax team rosters returned no teams while reading Claims Remaining.');

        $rows=[];
        foreach($rosters as $teamId=>$team){
            $teamId=(string)$teamId;
            $teamName=trim((string)($team['teamName']??$teamId));
            if($teamId==='')continue;

            $data=$this->teamRosterInfo($teamId);
            $remaining=$this->extractClaimsRemaining($data);

            $rows[]=[
                'fantasy_team_id'=>$teamId,
                'fantasy_team_name'=>$teamName,
                'moves_used'=>$remaining===null?null:max(0,7-$remaining),
                'moves_left'=>$remaining,
            ];
        }

        if(!$rows)throw new RuntimeException('Fantrax returned no team Claims Remaining rows.');
        return $rows;
    }

    private function teamRosterInfo(string $teamId): array
    {
        $refUrl='https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/team/roster;teamId='.rawurlencode($teamId);
        $payload=[
            'msgs'=>[[
                'method'=>'getTeamRosterInfo',
                'data'=>[
                    'leagueId'=>self::LEAGUE_ID,
                    'teamId'=>$teamId,
                    'view'=>'STATS',
                ],
            ]],
            'uiv'=>3,
            'refUrl'=>$refUrl,
            'dt'=>0,
            'at'=>0,
            'av'=>'0.0',
            'tz'=>'America/Vancouver',
            'v'=>self::API_VERSION,
        ];

        $response=Http::timeout(45)->retry(2,1200)->withHeaders([
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept'=>'application/json',
            'Content-Type'=>'application/json',
            'Referer'=>$refUrl,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID,$payload);
        $response->throw();

        $data=$response->json('responses.0.data');
        if(!is_array($data))throw new RuntimeException('Fantrax team roster page returned no data for team '.$teamId.'.');
        return $data;
    }

    private function extractClaimsRemaining(array $data): ?int
    {
        $directKeys=[
            'claimsRemaining','claimRemaining','remainingClaims','claims_remaining',
            'claim_remaining','remaining_claims'
        ];
        foreach($directKeys as $key){
            $found=$this->findKeyRecursive($data,$key);
            $number=$this->integerFromValue($found);
            if($number!==null)return max(0,$number);
        }

        $found=$this->findLabelledValue($data);
        if($found!==null)return max(0,$found);

        $json=json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        if(is_string($json) && preg_match('/Claims?\\s+Remaining[^0-9]{0,30}(\\d+)/i',$json,$m)){
            return (int)$m[1];
        }

        return null;
    }

    private function findKeyRecursive(mixed $node,string $wanted): mixed
    {
        if(!is_array($node))return null;
        foreach($node as $key=>$value){
            if(is_string($key) && strcasecmp($key,$wanted)===0)return $value;
            if(is_array($value)){
                $found=$this->findKeyRecursive($value,$wanted);
                if($found!==null)return $found;
            }
        }
        return null;
    }

    private function findLabelledValue(mixed $node): ?int
    {
        if(!is_array($node))return null;

        $label='';
        foreach(['label','name','title','heading','key','description','text'] as $key){
            if(isset($node[$key]) && is_scalar($node[$key])){
                $candidate=trim(html_entity_decode(strip_tags((string)$node[$key])));
                if(preg_match('/claims?\\s+remaining/i',$candidate)){
                    $label=$candidate;
                    break;
                }
            }
        }

        if($label!==''){
            foreach(['value','content','total','remaining','count','number','amount'] as $key){
                if(array_key_exists($key,$node)){
                    $number=$this->integerFromValue($node[$key]);
                    if($number!==null)return $number;
                }
            }
            if(preg_match('/(\\d+)/',$label,$m))return (int)$m[1];
        }

        foreach($node as $value){
            if(is_scalar($value)){
                $text=trim(html_entity_decode(strip_tags((string)$value)));
                if(preg_match('/claims?\\s+remaining[^0-9]*(\\d+)/i',$text,$m))return (int)$m[1];
            } elseif(is_array($value)){
                $found=$this->findLabelledValue($value);
                if($found!==null)return $found;
            }
        }

        return null;
    }

    private function integerFromValue(mixed $value): ?int
    {
        if(is_int($value))return $value;
        if(is_float($value))return (int)$value;
        if(is_string($value)){
            $text=trim(html_entity_decode(strip_tags($value)));
            if(preg_match('/-?\\d+/',$text,$m))return (int)$m[0];
        }
        if(is_array($value)){
            foreach(['value','content','total','remaining','count','number','amount'] as $key){
                if(array_key_exists($key,$value)){
                    $number=$this->integerFromValue($value[$key]);
                    if($number!==null)return $number;
                }
            }
        }
        return null;
    }
}
