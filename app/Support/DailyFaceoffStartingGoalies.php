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

        // Daily Faceoff is client-rendered. Try the origin first because it is fastest,
        // then use a rendered-reader response when the origin only returns the app shell.
        $sources = [];
        $origin = $this->request($url, 'text/html,application/xhtml+xml');
        if ($origin !== null) $sources[] = ['name'=>'dailyfaceoff', 'body'=>$origin];

        $readerUrl = 'https://r.jina.ai/http://www.dailyfaceoff.com/starting-goalies/'.$date->format('Y-m-d');
        $reader = null;

        foreach ($sources as $source) {
            $parsed = $this->parse($source['body'], $url);
            if ($parsed !== null) return $parsed + ['source'=>$source['name']];
        }

        // Only pay the extra request when the origin did not contain rendered matchups.
        $reader = $this->request($readerUrl, 'text/plain,text/markdown,text/html');
        if ($reader !== null) {
            $parsed = $this->parse($reader, $url);
            if ($parsed !== null) return $parsed + ['source'=>'rendered-reader'];
        }

        throw new RuntimeException('Could not parse Daily Faceoff starting goalies: Daily Faceoff returned no rendered matchup records from either source. Existing data preserved.');
    }

    private function request(string $url, string $accept): ?string
    {
        try {
            $response = Http::timeout(45)->retry(2, 1000)->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/153 Safari/537.36',
                'Accept' => $accept,
                'Cache-Control' => 'no-cache',
            ])->get($url);
            if (!$response->successful()) return null;
            $body = trim($response->body());
            return $body !== '' ? $body : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function parse(string $content, string $url): ?array
    {
        $text = $this->toLines($content);
        $matchups = $this->matchups($text);

        if (!$matchups) {
            if (preg_match('/0\s+of\s+0\s+confirmed|no games scheduled/i', $text)) {
                return ['url'=>$url, 'rows'=>[]];
            }
            return null;
        }

        $rows = [];
        foreach ($matchups as $matchup) {
            $goalies = $this->goalies($matchup['segment']);
            if (count($goalies) !== 2) {
                throw new RuntimeException('Daily Faceoff validation failed for '.$matchup['away'].' at '.$matchup['home'].': expected exactly 2 goalies, found '.count($goalies).'. Existing data preserved.');
            }
            $rows = array_merge($rows, $this->matchupRows($matchup['away'], $matchup['home'], $goalies[0], $goalies[1]));
        }

        if (count($rows) !== count($matchups) * 2) {
            throw new RuntimeException('Daily Faceoff validation failed: incomplete goalie data. Existing data preserved.');
        }

        foreach ($rows as $row) {
            if (!in_array($row['starting_status'], ['Confirmed','Probable','Unconfirmed'], true)) {
                throw new RuntimeException('Daily Faceoff validation failed: invalid starting status. Existing data preserved.');
            }
        }

        return ['url'=>$url, 'rows'=>$this->unique($rows)];
    }

    private function toLines(string $content): string
    {
        $text = html_entity_decode($content, ENT_QUOTES | ENT_HTML5);
        $text = str_replace(['\\n','\\r','\\t'], ["\n","\n"," "], $text);
        $text = preg_replace('/<(?:br\s*\/?>|\/p>|\/div>|\/section>|\/article>|\/h[1-6]>|\/li>)/i', "\n", $text) ?? $text;
        $text = strip_tags($text);

        // Also normalize Markdown returned by the rendered-reader fallback.
        $text = preg_replace('/!\[[^\]]*\]\([^)]*\)/u', '', $text) ?? $text;
        $text = preg_replace('/\[([^\]]+)\]\([^)]*\)/u', '$1', $text) ?? $text;
        $text = preg_replace('/^[#>*+\-]+\s*/mu', '', $text) ?? $text;
        $text = str_replace(['**','__','`'], '', $text);

        $text = preg_replace('/[\t\r ]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/ *\n */u', "\n", $text) ?? $text;
        $text = preg_replace('/\n{2,}/u', "\n", $text) ?? $text;
        return trim($text);
    }

    private function matchups(string $text): array
    {
        $teamPattern = implode('|', array_map(fn($t)=>preg_quote($t,'/'), self::TEAMS));
        preg_match_all('/^('.$teamPattern.')\s+at\s+('.$teamPattern.')\s*$/imu', $text, $matches, PREG_OFFSET_CAPTURE);
        if (empty($matches[0])) return [];

        $out=[];
        for ($i=0; $i<count($matches[0]); $i++) {
            $start=$matches[0][$i][1]+strlen($matches[0][$i][0]);
            $end=$i+1<count($matches[0]) ? $matches[0][$i+1][1] : strlen($text);
            $out[]=[
                'away'=>$matches[1][$i][0],
                'home'=>$matches[2][$i][0],
                'segment'=>substr($text,$start,$end-$start),
            ];
        }
        return $out;
    }

    private function goalies(string $segment): array
    {
        $lines=array_values(array_filter(array_map('trim', preg_split('/\n/u',$segment) ?: []), fn($v)=>$v!==''));
        $goalies=[];

        for ($i=0; $i<count($lines); $i++) {
            $status = ucfirst(strtolower($lines[$i]));
            if (!in_array($status, ['Confirmed','Probable','Unconfirmed'], true)) continue;

            $name=null;
            for ($j=$i-1; $j>=0 && $j>=$i-6; $j--) {
                if ($this->looksLikeName($lines[$j])) { $name=$lines[$j]; break; }
            }
            if (!$name) continue;

            $updated=null;
            for ($j=$i+1; $j<count($lines) && $j<=$i+4; $j++) {
                if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?Z$/', $lines[$j])) {
                    try { $updated=CarbonImmutable::parse($lines[$j]); } catch (\Throwable) {}
                    break;
                }
            }

            $goalies[]=['name'=>$name,'status'=>$status,'updated'=>$updated];
            if (count($goalies)===2) break;
        }
        return $goalies;
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
        if (mb_strlen($value) < 4 || mb_strlen($value) > 70) return false;
        if (preg_match('/^(Confirmed|Probable|Unconfirmed|Show More)$/i',$value)) return false;
        if (preg_match('/starting goalies|projected|stats|season|previous|line combos|schedule|length|expires|cap\$|source|image|W-L-OTL|GAA|SV%|SO:|http|www\./i',$value)) return false;
        if (preg_match('/^\d{4}-\d{2}-\d{2}T/', $value)) return false;
        return (bool)preg_match('/^[\p{L} .\'’\-]+$/u',$value);
    }

    private function unique(array $rows): array
    {
        $out=[];
        foreach($rows as $r) $out[mb_strtolower($r['team_name'].'|'.$r['player_name'])]=$r;
        return array_values($out);
    }
}
