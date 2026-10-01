<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class FantraxStandings
{
    public const LEAGUE_ID = '092zcn40molvao69';
    private const API_VERSION = '186.1.9';

    public function fetch(): array
    {
        $url='https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/standings';
        $requestData=['leagueId'=>self::LEAGUE_ID,'view'=>'STANDINGS'];
        $payload=[
            'msgs'=>[['method'=>'getStandings','data'=>$requestData]],
            'uiv'=>3,
            'refUrl'=>$url,
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
        if(!is_array($data)) throw new RuntimeException('Fantrax standings returned no response data.');

        $tables=$data['tableList']??$data['tables']??[];
        if(!is_array($tables))$tables=[];

        // Some Fantrax responses expose the primary standings table directly.
        if(isset($data['rows']) && is_array($data['rows'])){
            array_unshift($tables,$data);
        }

        $merged=[];
        foreach($tables as $table){
            foreach($this->parseTable($table) as $row){
                $key=(string)($row['team_id']??'');
                if($key==='')$key=mb_strtolower(trim((string)($row['team_name']??'')));
                if($key!=='')$merged[$key]=$row;
            }
        }
        $rows=array_values($merged);
        if(count($rows)>=10){
            return ['rows'=>$rows,'url'=>$url];
        }

        Log::warning('Fantrax standings parse diagnostic',[
            'data_keys'=>array_keys($data),
            'table_count'=>count($tables),
            'tables'=>array_map(function($table){
                return [
                    'keys'=>is_array($table)?array_keys($table):[],
                    'caption'=>is_array($table)?($table['caption']??null):null,
                    'subCaption'=>is_array($table)?($table['subCaption']??null):null,
                    'row_count'=>is_array($table)?count($table['rows']??$table['statsTable']??[]):0,
                    'header'=>$table['tableHeader']['cells']??$table['header']['cells']??$table['headers']??$table['columns']??null,
                    'first_row'=>($table['rows'][0]??$table['statsTable'][0]??null),
                ];
            },array_slice($tables,0,3)),
        ]);
        throw new RuntimeException('Fantrax standings returned no complete standings table.');
    }

    private function parseTable(array $table): array
    {
        $sourceRows=$table['rows']??$table['statsTable']??[];
        if(!is_array($sourceRows) || !$sourceRows)return [];

        $headers=$this->headers($table);
        $index=function(array $aliases)use($headers): ?int {
            foreach($aliases as $alias){
                $key=$this->normalizeHeader($alias);
                if(array_key_exists($key,$headers))return $headers[$key];
            }
            return null;
        };

        $rankIndex=$index(['Rank','Rk','#']);
        $wIndex=$index(['W','Wins']);
        $lIndex=$index(['L','Losses']);
        $tIndex=$index(['T','Ties']);
        $fptsIndex=$index(['FPts','FPTS','Fantasy Points','Fantasy Points For','FPts For','PF']);

        $out=[];
        foreach($sourceRows as $row){
            if(!is_array($row))continue;
            $cells=$row['cells']??[];
            if(!is_array($cells) || !$cells)continue;

            $teamIndex=null;
            $teamId=trim((string)($row['teamId']??$row['fantasyTeamId']??''));
            $teamName=$this->text($row['teamName']??$row['name']??'');

            foreach($cells as $i=>$cell){
                if(!is_array($cell))continue;
                $candidate=trim((string)($cell['teamId']??$cell['fantasyTeamId']??''));
                if($candidate!=='' || isset($cell['teamName'])){
                    $teamIndex=$i;
                    if($teamId==='')$teamId=$candidate;
                    if($teamName==='')$teamName=$this->text($cell['content']??$cell['teamName']??$cell['name']??'');
                    break;
                }
            }

            if($teamIndex===null){
                foreach($cells as $i=>$cell){
                    $text=$this->text(is_array($cell)?($cell['content']??$cell['value']??''):$cell);
                    if($text!=='' && !is_numeric(preg_replace('/[^0-9.\-]/','',$text))){
                        $teamIndex=$i;
                        if($teamName==='')$teamName=$text;
                        break;
                    }
                }
            }

            if($teamName==='')continue;
            if($teamId==='')$teamId='name:'.mb_strtolower($teamName);

            // Header names are preferred. Fantrax's standard standings layout is
            // rank, team, W, L, T, ... FPts; the relative fallbacks cover cases
            // where header metadata is omitted from the JSON response.
            $rank=$this->number($this->cellValue($cells,$rankIndex));
            if($rank===null && $teamIndex>0)$rank=$this->number($this->cellValue($cells,$teamIndex-1));

            $w=$this->number($row['w']??$row['wins']??$this->cellValue($cells,$wIndex));
            $l=$this->number($row['l']??$row['losses']??$this->cellValue($cells,$lIndex));
            $t=$this->number($row['t']??$row['ties']??$this->cellValue($cells,$tIndex));

            if($w===null || $l===null || $t===null){
                $numeric=[];
                for($i=$teamIndex+1;$i<count($cells);$i++){
                    $n=$this->number($this->cellValue($cells,$i));
                    if($n!==null)$numeric[]=['index'=>$i,'value'=>$n];
                }
                if(count($numeric)>=3){
                    $w??=$numeric[0]['value'];
                    $l??=$numeric[1]['value'];
                    $t??=$numeric[2]['value'];
                }
            }

            $fpts=$this->number(
                $row['fantasyPointsFor']??$row['fantasy_points_for']??$row['fpts']??$row['fPts']
                ??$this->cellValue($cells,$fptsIndex)
            );
            if($fpts===null){
                $numeric=[];
                for($i=($teamIndex??0)+1;$i<count($cells);$i++){
                    $raw=$this->cellValue($cells,$i);
                    if(is_string($raw) && str_contains($raw,'%'))continue;
                    $n=$this->number($raw);
                    if($n!==null)$numeric[]=$n;
                }
                // Standard Fantrax H2H order after Team is W, L, T, Pts, FPts.
                if(count($numeric)>=5)$fpts=$numeric[4];
                elseif(count($numeric)>=4)$fpts=$numeric[count($numeric)-1];
            }

            if($w===null || $l===null || $t===null || $fpts===null)continue;

            $out[]=[
                'team_id'=>$teamId,
                'team_name'=>$teamName,
                'rank'=>$rank!==null?(int)$rank:null,
                'w'=>(int)$w,
                'l'=>(int)$l,
                't'=>(int)$t,
                'standings_points'=>(int)(2*$w+$t),
                'fantasy_points_for'=>(float)$fpts,
            ];
        }

        return $out;
    }

    private function headers(array $table): array
    {
        $sets=[
            $table['tableHeader']['cells']??null,
            $table['header']['cells']??null,
            $table['headers']??null,
            $table['columns']??null,
        ];
        $map=[];
        foreach($sets as $set){
            if(!is_array($set))continue;
            foreach(array_values($set) as $i=>$cell){
                $values=[];
                if(is_array($cell)){
                    array_walk_recursive($cell,function($v)use(&$values){
                        if(is_scalar($v))$values[]=(string)$v;
                    });
                }elseif(is_scalar($cell))$values[]=(string)$cell;
                foreach($values as $value){
                    $key=$this->normalizeHeader($this->text($value));
                    if($key!=='' && !array_key_exists($key,$map))$map[$key]=$i;
                }
            }
        }
        return $map;
    }

    private function cellValue(array $cells, ?int $index): mixed
    {
        if($index===null || !array_key_exists($index,$cells))return null;
        $cell=$cells[$index];
        if(!is_array($cell))return $cell;
        return $cell['content']??$cell['value']??$cell['displayValue']??null;
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags((string)$value))));
    }

    private function number(mixed $value): ?float
    {
        if($value===null)return null;
        $text=$this->text($value);
        if($text==='')return null;
        $clean=preg_replace('/[^0-9.\-]/','',$text);
        return is_numeric($clean)?(float)$clean:null;
    }

    private function normalizeHeader(string $value): string
    {
        return preg_replace('/[^a-z0-9]+/','',strtolower($value))??'';
    }
}
