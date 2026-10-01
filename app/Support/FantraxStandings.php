<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxStandings
{
    public const LEAGUE_ID = '092zcn40molvao69';
    private const API_VERSION = '186.1.9';

    public function fetch(): array
    {
        $url='https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/standings';
        $requestData=['leagueId'=>self::LEAGUE_ID,'view'=>'SCHEDULE'];
        $payload=[
            'msgs'=>[['method'=>'getStandings','data'=>$requestData]],
            'uiv'=>3,
            'refUrl'=>$url.';view=SCHEDULE',
            'dt'=>0,
            'at'=>0,
            'av'=>'0.0',
            'tz'=>'America/Halifax',
            'v'=>self::API_VERSION,
        ];

        $response=Http::timeout(45)->retry(2,1000)->withHeaders([
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept'=>'application/json',
            'Content-Type'=>'application/json',
            'Referer'=>$url,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID,$payload);

        $response->throw();
        $json=$response->json();
        $data=$json['responses'][0]['data']??null;
        if(!is_array($data))throw new RuntimeException('Fantrax standings schedule returned no response data.');

        $today=CarbonImmutable::now('America/Halifax')->startOfDay();
        $teams=[];

        foreach(($data['tableList']??[]) as $table){
            $range=$this->dateRange((string)($table['subCaption']??''));
            if(!$range)continue;
            [$start,$end]=$range;

            // Ignore future scoring periods entirely.
            if($start->gt($today))continue;
            $completed=$end->lt($today);

            foreach(($table['rows']??[]) as $row){
                $cells=$row['cells']??[];
                if(!is_array($cells)||count($cells)<4)continue;

                $awayId=trim((string)($cells[0]['teamId']??''));
                $homeId=trim((string)($cells[2]['teamId']??''));
                $awayName=$this->text($cells[0]['content']??'');
                $homeName=$this->text($cells[2]['content']??'');
                $awayScore=$this->numeric($cells[1]['content']??null);
                $homeScore=$this->numeric($cells[3]['content']??null);

                if($awayId===''||$homeId===''||$awayName===''||$homeName==='')continue;

                foreach([[$awayId,$awayName],[$homeId,$homeName]] as [$id,$name]){
                    if(!isset($teams[$id])){
                        $teams[$id]=[
                            'team_id'=>$id,
                            'team_name'=>$name,
                            'w'=>0,'l'=>0,'t'=>0,
                            'standings_points'=>0,
                            'fantasy_points_for'=>0.0,
                        ];
                    }
                }

                if($awayScore!==null)$teams[$awayId]['fantasy_points_for']+=$awayScore;
                if($homeScore!==null)$teams[$homeId]['fantasy_points_for']+=$homeScore;

                // Current-period scores are live FPts, but W/L/T are only official
                // after the scoring period is complete.
                if(!$completed||$awayScore===null||$homeScore===null)continue;

                if(abs($awayScore-$homeScore)<0.0001){
                    $teams[$awayId]['t']++;
                    $teams[$homeId]['t']++;
                }elseif($awayScore>$homeScore){
                    $teams[$awayId]['w']++;
                    $teams[$homeId]['l']++;
                }else{
                    $teams[$homeId]['w']++;
                    $teams[$awayId]['l']++;
                }
            }
        }

        if(count($teams)<10){
            throw new RuntimeException('Fantrax schedule returned fewer than 10 standings teams.');
        }

        foreach($teams as &$team){
            $team['standings_points']=2*$team['w']+$team['t'];
        }
        unset($team);

        $rows=array_values($teams);
        usort($rows,function($a,$b){
            return ($b['standings_points']<=>$a['standings_points'])
                ?:($b['fantasy_points_for']<=>$a['fantasy_points_for'])
                ?:strnatcasecmp($a['team_name'],$b['team_name']);
        });
        foreach($rows as $i=>&$row)$row['rank']=$i+1;
        unset($row);

        return ['rows'=>$rows,'url'=>$url];
    }

    private function dateRange(string $value): ?array
    {
        $text=trim($value," \t\n\r\0\x0B()");
        if(!preg_match('/([A-Z][a-z]{2}\s+[A-Z][a-z]{2}\s+\d{1,2},\s+\d{4})\s+-\s+([A-Z][a-z]{2}\s+[A-Z][a-z]{2}\s+\d{1,2},\s+\d{4})/',$text,$m)){
            return null;
        }

        try{
            $start=CarbonImmutable::createFromFormat('!D M j, Y',$m[1],'America/Halifax');
            $end=CarbonImmutable::createFromFormat('!D M j, Y',$m[2],'America/Halifax');
            return ($start&&$end)?[$start,$end]:null;
        }catch(\Throwable){
            return null;
        }
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags((string)$value))));
    }

    private function numeric(mixed $value): ?float
    {
        if($value===null||$value==='')return null;
        $clean=preg_replace('/[^0-9.\-]/','',html_entity_decode(strip_tags((string)$value)));
        return is_numeric($clean)?(float)$clean:null;
    }
}
