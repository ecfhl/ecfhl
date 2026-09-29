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

        $rows = $this->parseCards($html);
        $plain = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5));
        if (!$rows && !preg_match('/0\s+of\s+0\s+confirmed|no games scheduled/i', $plain)) {
            throw new RuntimeException('Could not parse Daily Faceoff starting goalies: no matchup cards found.');
        }
        return ['url'=>$url, 'rows'=>$rows];
    }

    private function parseCards(string $html): array
    {
        if (!class_exists(\DOMDocument::class)) throw new RuntimeException('PHP DOM extension is not installed.');
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($doc);
        $rows = [];

        foreach ($xpath->query('//article') as $article) {
            $articleText = $this->clean($article->textContent);
            $teams = [];
            foreach (self::TEAMS as $team) if (str_contains($articleText, $team)) $teams[] = $team;
            if (count($teams) < 2) continue;
            $away = $teams[0];
            $home = $teams[1];

            $parts = [];
            foreach ($xpath->query('.//p', $article) as $p) {
                $text = $this->clean($p->textContent);
                if ($text !== '') $parts[] = $text;
            }
            $goalies = [];
            for ($i=1; $i<count($parts); $i++) {
                if (!preg_match('/^(Confirmed|Unconfirmed|Probable)$/i', $parts[$i], $m)) continue;
                $name = trim($parts[$i-1]);
                if (!$this->looksLikeName($name)) continue;
                $updated = null;
                for ($j=$i+1; $j<min(count($parts),$i+4); $j++) {
                    if (preg_match('/^\d{1,2}\/\d{1,2}\/\d{4}\s+\d{1,2}:\d{2}$/', $parts[$j])) {
                        try { $updated = CarbonImmutable::createFromFormat('n/j/Y G:i', $parts[$j], 'America/Halifax'); } catch (\Throwable) {}
                        break;
                    }
                }
                $goalies[] = ['name'=>$name,'status'=>ucfirst(strtolower($m[1])),'updated'=>$updated];
                if (count($goalies) === 2) break;
            }
            if (count($goalies) !== 2) continue;

            $rows[] = ['player_name'=>$goalies[0]['name'],'starting_status'=>$goalies[0]['status'],'source_updated_at'=>$goalies[0]['updated'],'team_name'=>$away,'opponent_name'=>$home,'home_away'=>'AWAY'];
            $rows[] = ['player_name'=>$goalies[1]['name'],'starting_status'=>$goalies[1]['status'],'source_updated_at'=>$goalies[1]['updated'],'team_name'=>$home,'opponent_name'=>$away,'home_away'=>'HOME'];
        }
        return $this->unique($rows);
    }

    private function looksLikeName(string $value): bool
    {
        if (strlen($value) < 4 || strlen($value) > 70) return false;
        if (preg_match('/starting goalies|projected|confirmed|unconfirmed|probable|stats|season|previous/i',$value)) return false;
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
