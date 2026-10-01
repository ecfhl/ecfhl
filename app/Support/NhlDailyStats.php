<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class NhlDailyStats
{
    public function fetch(CarbonImmutable $date): array
    {
        $day=$date->format('Y-m-d');
        $score=$this->get('https://api-web.nhle.com/v1/score/'.$day);
        $rows=[];

        foreach(($score['games']??[]) as $game){
            $gameId=$game['id']??null;
            if(!$gameId)continue;

            try{
                $box=$this->get('https://api-web.nhle.com/v1/gamecenter/'.$gameId.'/boxscore');
                $landing=$this->get('https://api-web.nhle.com/v1/gamecenter/'.$gameId.'/landing');
                $playByPlay=$this->get('https://api-web.nhle.com/v1/gamecenter/'.$gameId.'/play-by-play');
            }catch(\Throwable){
                continue;
            }

            $rosterNames=$this->rosterNames($playByPlay);
            $this->mergeBoxscore($rows,$box,$rosterNames);
            $this->mergeGoalTypes($rows,$landing);
            $this->mergeGameWinner($rows,$landing);
        }

        return array_values($rows);
    }

    private function mergeBoxscore(array &$rows,array $box,array $rosterNames): void
    {
        foreach(['awayTeam','homeTeam'] as $side){
            $teamStats=$box['playerByGameStats'][$side]??[];
            $teamAbbr=strtoupper(trim((string)($box[$side]['abbrev']??'')));

            foreach(['forwards','defense'] as $group){
                foreach(($teamStats[$group]??[]) as $p){
                    $playerId=(string)($p['playerId']??'');
                    $name=$rosterNames[$playerId]??$this->localized($p['name']??null);
                    if($name==='')continue;
                    $key=$this->key($name,$teamAbbr);
                    $rows[$key]=$rows[$key]??$this->emptyRow($name,$teamAbbr);
                    $rows[$key]['gp']=1;
                    $rows[$key]['g']=(int)($p['goals']??0);
                    $rows[$key]['a']=(int)($p['assists']??0);
                    $rows[$key]['ppg']=(int)($p['powerPlayGoals']??0);
                }
            }

            foreach(($teamStats['goalies']??[]) as $p){
                $playerId=(string)($p['playerId']??'');
                $name=$rosterNames[$playerId]??$this->localized($p['name']??null);
                if($name==='')continue;
                $toi=trim((string)($p['toi']??''));
                $key=$this->key($name,$teamAbbr);
                $rows[$key]=$rows[$key]??$this->emptyRow($name,$teamAbbr);
                $rows[$key]['gp']=$toi!==''&&$toi!=='00:00'?1:0;
                $rows[$key]['w']=strtoupper((string)($p['decision']??''))==='W'?1:0;
                $rows[$key]['so']=$rows[$key]['w']===1
                    && (int)($p['goalsAgainst']??0)===0
                    && (bool)($p['starter']??false)
                    ? 1 : 0;
            }
        }
    }

    private function rosterNames(array $playByPlay): array
    {
        $names=[];
        foreach(($playByPlay['rosterSpots']??[]) as $spot){
            $id=(string)($spot['playerId']??'');
            if($id==='')continue;

            $first=$this->localized($spot['firstName']??null);
            $last=$this->localized($spot['lastName']??null);
            $full=trim($first.' '.$last);

            if($full!=='')$names[$id]=$full;
        }
        return $names;
    }

    private function mergeGoalTypes(array &$rows,array $landing): void
    {
        foreach(($landing['summary']['scoring']??$landing['scoring']??[]) as $period){
            foreach(($period['goals']??[]) as $goal){
                $name=$this->localized($goal['name']??null);
                $team=$this->localized($goal['teamAbbrev']??null);
                if($name===''||$team==='')continue;
                $key=$this->key($name,$team);
                $rows[$key]=$rows[$key]??$this->emptyRow($name,$team);
                $strength=strtoupper(trim((string)($goal['strength']??'')));
                if($strength==='PPG'||$strength==='PP')$rows[$key]['ppg']++;
                if($strength==='SHG'||$strength==='SH')$rows[$key]['shg']++;
            }
        }
    }

    private function mergeGameWinner(array &$rows,array $landing): void
    {
        $state=strtoupper((string)($landing['gameState']??''));
        if(!in_array($state,['FINAL','OFF'],true))return;

        $awayAbbr=$this->localized($landing['awayTeam']['abbrev']??null);
        $homeAbbr=$this->localized($landing['homeTeam']['abbrev']??null);
        $awayScore=(int)($landing['awayTeam']['score']??0);
        $homeScore=(int)($landing['homeTeam']['score']??0);
        if($awayScore===$homeScore)return;

        $winner=$awayScore>$homeScore?$awayAbbr:$homeAbbr;
        $loserScore=min($awayScore,$homeScore);
        $targetWinnerGoal=$loserScore+1;
        $winnerGoals=0;

        foreach(($landing['summary']['scoring']??$landing['scoring']??[]) as $period){
            foreach(($period['goals']??[]) as $goal){
                $team=$this->localized($goal['teamAbbrev']??null);
                if($team!==$winner)continue;
                $winnerGoals++;
                if($winnerGoals!==$targetWinnerGoal)continue;
                $name=$this->localized($goal['name']??null);
                if($name==='')return;
                $key=$this->key($name,$winner);
                $rows[$key]=$rows[$key]??$this->emptyRow($name,$winner);
                $rows[$key]['gwg']=1;
                return;
            }
        }
    }

    private function get(string $url): array
    {
        $response=Http::timeout(30)->retry(2,700)->withHeaders([
            'User-Agent'=>'Mozilla/5.0',
            'Accept'=>'application/json',
        ])->get($url);
        $response->throw();
        $json=$response->json();
        if(!is_array($json))throw new RuntimeException('NHL API returned invalid JSON.');
        return $json;
    }

    private function localized(mixed $value): string
    {
        if(is_array($value))return trim((string)($value['default']??reset($value)??''));
        return trim((string)$value);
    }

    private function emptyRow(string $name,string $team): array
    {
        return [
            'player_name'=>$name,
            'nhl_team'=>strtoupper($team),
            'gp'=>0,'g'=>0,'a'=>0,'ppg'=>0,'shg'=>0,'gwg'=>0,'w'=>0,'so'=>0,
        ];
    }

    private function key(string $name,string $team): string
    {
        $name=trim($name);
        if(str_contains($name,',')){
            [$last,$first]=array_map('trim',explode(',',$name,2));
            if($first!==''&&$last!=='')$name=$first.' '.$last;
        }
        $name=preg_replace('/[^\pL\pN]+/u','',mb_strtolower($name))??'';
        $team=strtoupper(trim($team));
        $team=match($team){'LA'=>'LAK','NJ'=>'NJD','SJ'=>'SJS','TB'=>'TBL',default=>$team};
        return $team.'|'.$name;
    }
}
