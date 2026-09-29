<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DailyFaceoffStartingGoalies
{
    private const TEAMS = ['Anaheim Ducks','Boston Bruins','Buffalo Sabres','Calgary Flames','Carolina Hurricanes','Chicago Blackhawks','Colorado Avalanche','Columbus Blue Jackets','Dallas Stars','Detroit Red Wings','Edmonton Oilers','Florida Panthers','Los Angeles Kings','Minnesota Wild','Montreal Canadiens','Nashville Predators','New Jersey Devils','New York Islanders','New York Rangers','Ottawa Senators','Philadelphia Flyers','Pittsburgh Penguins','San Jose Sharks','Seattle Kraken','St. Louis Blues','Tampa Bay Lightning','Toronto Maple Leafs','Utah Mammoth','Vancouver Canucks','Vegas Golden Knights','Washington Capitals','Winnipeg Jets'];

    public function fetch(CarbonImmutable $date): array
    {
        $url = 'https://www.dailyfaceoff.com/starting-goalies/'.$date->format('Y-m-d');
        $html = Http::timeout(45)->retry(2, 1000)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/153 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->get($url)->throw()->body();

        $rows = $this->parsePageText($html);
        if (!$rows) $rows = $this->parseCards($html);

        $plain = $this->clean(strip_tags($html));
        if (!$rows && !preg_match('/0\s+of\s+0\s+confirmed|no games scheduled/i', $plain)) {
            throw new RuntimeException('Could not parse Daily Faceoff starting goalies: no matchup/goalie records found.');
        }
        return ['url'=>$url, 'rows'=>$rows];
    }

    private function parsePageText(string $html): array
    {
        $text = html_entity_decode($html, ENT_QUOTES | ENT_HTML5);
        $text = str_replace(['\\u0026','\\u003c','\\u003e','\\u0027','\\u0022'], ['&','<','>','\'','"'], $text);
        $text = preg_replace('/<(?:br|\/p|\/div|\/section|\/article|\/h[1-6]|\/li|\/span)>/i', "\n", $text);
        $text = strip_tags($text);
        $text = str_replace(['\\n','\\r','\\t'], "\n", $text);
        $text = preg_replace('/[\t\r ]+/u', ' ', $text);
        $text = preg_replace('/\n+/u', "\n", $text);

        $teamPattern = implode('|', array_map(fn($t)=>preg_quote($t,'/'), self::TEAMS));
        preg_match_all('/('.$teamPattern.')\s+at\s+('.$teamPattern.')/iu', $text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) return [];

        $rows=[];
        $count=count($matches[0]);
        for($i=0;$i<$count;$i++){
            $away=$matches[1][$i][0]; $home=$matches[2][$i][0];
            $start=$matches[0][$i][1]+strlen($matches[0][$i][0]);
            $end=$i+1<$count ? $matches[0][$i+1][1] : strlen($text);
            $segment=substr($text,$start,$end-$start);
            $goalies=$this->goaliesFromText($segment);
            if(count($goalies)>=2) $rows=array_merge($rows,$this->matchupRows($away,$home,$goalies[0],$goalies[1]));
        }
        return $this->unique($rows);
    }

    private function goaliesFromText(string $segment): array
    {
        $goalies=[];
        // Keep punctuation outside character classes. The previous separator class
        // contained an incorrectly escaped bracket/backslash combination which PCRE
        // interpreted as an invalid range.
        $separator='(?:\\s|["\' :,{}]|\\[|\\]|\\\\)*?';
        $pattern='/([\p{L}][\p{L} .\'’\-]{2,68}?)'.$separator.'\b(Confirmed|Unconfirmed|Probable)\b(?:'.$separator.'(\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z?))?/imu';
        preg_match_all($pattern, $segment, $gm, PREG_SET_ORDER);
        foreach($gm as $g){
            $name=$this->clean($g[1]);
            $name=preg_replace('/^.*\b(?:Image|Goalie|Starter)\s*:?\s*/iu','',$name) ?? $name;
            if(!$this->looksLikeName($name)) continue;
            $updated=null;
            if(!empty($g[3])) { try{$updated=CarbonImmutable::parse(trim($g[3]));}catch(\Throwable){} }
            $goalies[]=['name'=>$name,'status'=>ucfirst(strtolower($g[2])),'updated'=>$updated];
            if(count($goalies)===2) break;
        }
        return $goalies;
    }

    private function parseCards(string $html): array
    {
        if (!class_exists(\DOMDocument::class)) return [];
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($doc);
        $rows = [];
        foreach ($xpath->query('//article|//section') as $article) {
            $articleText = $this->clean($article->textContent);
            [$away,$home] = $this->matchTeams($articleText);
            if (!$away || !$home) continue;
            $goalies=$this->goaliesFromText($article->textContent);
            if(count($goalies)>=2) $rows=array_merge($rows,$this->matchupRows($away,$home,$goalies[0],$goalies[1]));
        }
        return $this->unique($rows);
    }

    private function matchTeams(string $text): array
    {
        foreach(self::TEAMS as $away) foreach(self::TEAMS as $home) {
            if($away!==$home && preg_match('/'.preg_quote($away,'/').'\s+at\s+'.preg_quote($home,'/').'/i',$text)) return [$away,$home];
        }
        return [null,null];
    }

    private function matchupRows(string $away,string $home,array $awayGoalie,array $homeGoalie): array
    {
        return [
            ['player_name'=>$awayGoalie['name'],'starting_status'=>$awayGoalie['status'],'source_updated_at'=>$awayGoalie['updated'],'team_name'=>$away,'opponent_name'=>$home,'home_away'=>'AWAY'],
            ['player_name'=>$homeGoalie['name'],'starting_status'=>$homeGoalie['status'],'source_updated_at'=>$homeGoalie['updated'],'team_name'=>$home,'opponent_name'=>$away,'home_away'=>'HOME'],
        ];
    }

    private function looksLikeName(string $value): bool
    {
        if (strlen($value) < 4 || strlen($value) > 70) return false;
        if (preg_match('/starting goalies|projected|confirmed|unconfirmed|probable|stats|season|previous|show more|line combos|schedule|length|expires|cap\$|source|image/i',$value)) return false;
        return (bool)preg_match('/^[\p{L} .\'’\-]+$/u',$value);
    }

    private function clean(string $value): string
    {
        return trim(preg_replace('/\s+/u',' ',html_entity_decode($value,ENT_QUOTES|ENT_HTML5)));
    }

    private function unique(array $rows): array
    {
        $out=[];
        foreach($rows as $r) $out[mb_strtolower($r['team_name'].'|'.$r['player_name'])]=$r;
        return array_values($out);
    }
}
