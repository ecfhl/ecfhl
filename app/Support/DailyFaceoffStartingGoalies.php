<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DailyFaceoffStartingGoalies
{
    public function fetch(CarbonImmutable $date): array
    {
        $url = 'https://www.dailyfaceoff.com/starting-goalies/'.$date->format('Y-m-d');
        $html = Http::timeout(45)->retry(2, 1000)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/153 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->get($url)->throw()->body();

        $rows = $this->parseJson($html);
        if (!$rows) $rows = $this->parseMarkup($html);

        $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html)));
        if (!$rows && !preg_match('/0\s+of\s+0\s+confirmed|no games scheduled/i', $text)) {
            throw new RuntimeException('Could not parse Daily Faceoff starting goalies.');
        }

        return ['url' => $url, 'rows' => $rows];
    }

    private function parseJson(string $html): array
    {
        $candidates = [];
        preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $html, $scripts);
        foreach ($scripts[1] ?? [] as $script) {
            $script = trim(html_entity_decode($script));
            if ($script === '') continue;
            $decoded = json_decode($script, true);
            if (is_array($decoded)) $this->walk($decoded, $candidates);
        }

        $rows = [];
        foreach ($candidates as $node) {
            $away = $this->teamName($node, ['awayTeam','away_team','away']);
            $home = $this->teamName($node, ['homeTeam','home_team','home']);
            if (!$away || !$home) continue;

            $awayGoalie = $this->goalie($node, ['awayGoalie','away_goalie','awayStarter','away_starter']);
            $homeGoalie = $this->goalie($node, ['homeGoalie','home_goalie','homeStarter','home_starter']);
            if ($awayGoalie) $rows[] = $this->row($awayGoalie, $away, $home, 'AWAY');
            if ($homeGoalie) $rows[] = $this->row($homeGoalie, $home, $away, 'HOME');
        }
        return $this->unique($rows);
    }

    private function parseMarkup(string $html): array
    {
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html, LIBXML_NOERROR | LIBXML_NOWARNING);
        $xpath = new \DOMXPath($doc);
        $rows = [];

        // DFO cards expose goalie status as visible text. Work upward from each
        // status element so this survives CSS/class-name changes.
        foreach ($xpath->query("//*[contains(normalize-space(.),'Confirmed') or contains(normalize-space(.),'Unconfirmed') or contains(normalize-space(.),'Probable')]") as $statusNode) {
            $statusText = trim(preg_replace('/\s+/', ' ', $statusNode->textContent));
            if (!preg_match('/\b(Confirmed|Unconfirmed|Probable)\b/i', $statusText, $sm)) continue;
            $status = ucfirst(strtolower($sm[1]));

            $container = $statusNode;
            for ($i=0; $i<6 && $container->parentNode; $i++) {
                $container = $container->parentNode;
                $text = trim(preg_replace('/\s+/', ' ', $container->textContent));
                if (strlen($text) > 40 && strlen($text) < 1200) break;
            }
            $text = trim(preg_replace('/\s+/', ' ', $container->textContent));
            if (!preg_match('/([A-Z][A-Za-z .\'’-]{2,50})\s+(?:\([A-Z]{2,4}\)\s*)?(?:Confirmed|Unconfirmed|Probable)/u', $text, $gm)) continue;
            $name = trim($gm[1]);
            if (preg_match('/starting goalies|goalies|updated|projected/i', $name)) continue;

            // Team/opponent are resolved later from the matchup block if present.
            $team = $this->findTeamAround($container, true);
            $opp = $this->findTeamAround($container, false);
            if (!$team || !$opp) continue;
            $rows[] = ['player_name'=>$name,'starting_status'=>$status,'source_updated_at'=>$this->timestamp($text),'team_name'=>$team,'opponent_name'=>$opp,'home_away'=>$this->isAway($text)?'AWAY':'HOME'];
        }
        return $this->unique($rows);
    }

    private function walk(array $node, array &$out): void
    {
        $keys = array_keys($node);
        if (array_intersect($keys, ['awayTeam','away_team','homeTeam','home_team','awayGoalie','homeGoalie','awayStarter','homeStarter'])) $out[] = $node;
        foreach ($node as $value) if (is_array($value)) $this->walk($value, $out);
    }

    private function teamName(array $node, array $keys): ?string
    {
        foreach ($keys as $key) if (isset($node[$key])) {
            $v=$node[$key];
            if (is_string($v)) return trim($v);
            if (is_array($v)) foreach (['name','fullName','teamName'] as $n) if (!empty($v[$n])) return trim((string)$v[$n]);
        }
        return null;
    }

    private function goalie(array $node, array $keys): ?array
    {
        foreach ($keys as $key) if (!empty($node[$key])) {
            $v=$node[$key]; if (is_string($v)) return ['name'=>$v]; if (is_array($v)) return $v;
        }
        return null;
    }

    private function row(array $g, string $team, string $opp, string $side): array
    {
        $name=$g['name']??$g['playerName']??$g['fullName']??($g['player']['name']??null);
        $status=$g['status']??$g['startingStatus']??$g['confirmationStatus']??'Unconfirmed';
        $updated=$g['updatedAt']??$g['updated_at']??$g['lastUpdated']??null;
        return ['player_name'=>trim((string)$name),'starting_status'=>ucfirst(strtolower((string)$status)),'source_updated_at'=>$updated?CarbonImmutable::parse($updated):null,'team_name'=>$team,'opponent_name'=>$opp,'home_away'=>$side];
    }

    private function unique(array $rows): array
    {
        $out=[]; foreach($rows as $r){if(empty($r['player_name'])||empty($r['team_name']))continue;$out[mb_strtolower($r['team_name'].'|'.$r['player_name'])]=$r;} return array_values($out);
    }

    private function timestamp(string $text): ?CarbonImmutable
    {
        if (preg_match('/20\d{2}-\d{2}-\d{2}T[0-9:.+-]+Z?/i',$text,$m)) try{return CarbonImmutable::parse($m[0]);}catch(\Throwable){}
        return null;
    }

    private function findTeamAround(\DOMNode $node, bool $primary): ?string
    {
        $known=['Anaheim Ducks','Boston Bruins','Buffalo Sabres','Calgary Flames','Carolina Hurricanes','Chicago Blackhawks','Colorado Avalanche','Columbus Blue Jackets','Dallas Stars','Detroit Red Wings','Edmonton Oilers','Florida Panthers','Los Angeles Kings','Minnesota Wild','Montreal Canadiens','Nashville Predators','New Jersey Devils','New York Islanders','New York Rangers','Ottawa Senators','Philadelphia Flyers','Pittsburgh Penguins','San Jose Sharks','Seattle Kraken','St. Louis Blues','Tampa Bay Lightning','Toronto Maple Leafs','Utah Mammoth','Vancouver Canucks','Vegas Golden Knights','Washington Capitals','Winnipeg Jets'];
        $container=$node; for($i=0;$i<7&&$container->parentNode;$i++){$container=$container->parentNode;$text=$container->textContent;$found=[];foreach($known as $team)if(str_contains($text,$team))$found[]=$team;if(count($found)>=2)return $found[$primary?0:1];}
        return null;
    }

    private function isAway(string $text): bool { return (bool)preg_match('/\baway\b|\bat\b/i',$text); }
}
