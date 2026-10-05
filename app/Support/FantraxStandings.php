<?php

namespace App\Support;

use App\Support\LiveScoring\FantraxClient;
use RuntimeException;

class FantraxStandings
{
    public const LEAGUE_ID = '092zcn40molvao69';

    public function fetch(): array
    {
        // Fantrax owns rank, tie-breaking, finalized records and season FPts.
        // Reconstructing them from daily scores omits corrections and current points.
        $data=app(FantraxClient::class)->request('getStandings',[
            'leagueId'=>self::LEAGUE_ID,'view'=>'REGULAR_SEASON','proj'=>false,'optimal'=>false,
        ]);
        $rows=$this->parse($data);
        return [
            'rows'=>$rows,
            'url'=>'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/standings',
            'as_of_date'=>(new FantasyDay)->today()->toDateString(),
        ];
    }

    public function parse(array $data): array
    {
        $selections=$data['displayedSelections']??[];
        if(($selections['view']??null)!=='REGULAR_SEASON'||!array_key_exists('proj',$selections)
            ||$selections['proj']!==false||($selections['optimal']??false)!==false
            ||($selections['timeframeType']??null)!=='YEAR_TO_DATE'){
            throw new RuntimeException('Fantrax did not return actual regular-season standings. Existing standings preserved.');
        }
        $rows=[];
        foreach($data['tableList']??[] as $table){
            $fixed=$this->columns($table['fixedHeader']['cells']??[]);
            $columns=$this->columns($table['header']['cells']??[]);
            if(!isset($fixed['rank'],$fixed['team'])||!isset($columns['win'],$columns['loss'],$columns['tie'],$columns['points'],$columns['pointsFor']))continue;
            foreach($table['rows']??[] as $row){
                $identity=$row['fixedCells'][$fixed['team']]??[];
                $id=trim((string)($identity['teamId']??''));
                $name=$this->text($identity['content']??'');
                if($id===''||$name===''||isset($rows[$id]))throw new RuntimeException('Missing or duplicate Fantrax standings team. Existing standings preserved.');
                $value=fn($key)=>$this->numeric($row['cells'][$columns[$key]]['content']??null);
                $rank=$this->numeric($row['fixedCells'][$fixed['rank']]['content']??null);
                $win=$value('win');$loss=$value('loss');$tie=$value('tie');
                $points=$value('points');$fpts=$value('pointsFor');
                foreach([$rank,$win,$loss,$tie,$points,$fpts] as $number){
                    if($number===null)throw new RuntimeException('Missing official Fantrax standings value. Existing standings preserved.');
                }
                foreach([$rank,$win,$loss,$tie] as $integer){
                    if($integer<0||floor($integer)!==$integer)throw new RuntimeException('Invalid official standings record. Existing standings preserved.');
                }
                if($rank<1)throw new RuntimeException('Invalid official standings rank. Existing standings preserved.');
                $rows[$id]=[
                    'team_id'=>$id,'team_name'=>$name,'rank'=>(int)$rank,
                    'w'=>(int)$win,'l'=>(int)$loss,'t'=>(int)$tie,
                    'standings_points'=>$points,'fantasy_points_for'=>$fpts,
                ];
            }
        }
        if(count($rows)!==14)throw new RuntimeException('Fantrax returned '.count($rows).' of 14 regular-season standings teams. Existing standings preserved.');
        $rows=array_values($rows);
        usort($rows,fn($a,$b)=>$a['rank']<=>$b['rank']);
        return $rows;
    }

    private function columns(array $cells): array
    {
        $columns=[];
        foreach($cells as $index=>$cell)if(isset($cell['key']))$columns[(string)$cell['key']]=$index;
        return $columns;
    }

    private function text(mixed $value): string
    {
        return trim(preg_replace('/\s+/u',' ',html_entity_decode(strip_tags((string)$value))));
    }

    private function numeric(mixed $value): ?float
    {
        if($value===null||$value==='')return null;
        $text=$this->text($value);
        if(!preg_match('/^-?(?:\d{1,3}(?:,\d{3})+|\d+)(?:\.\d+)?$/',$text))return null;
        return (float)str_replace(',','',$text);
    }
}
