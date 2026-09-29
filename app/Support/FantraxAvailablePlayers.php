<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxAvailablePlayers
{
    public const LEAGUE_ID = '092zcn40molvao69';

    public function url(CarbonImmutable $date): string
    {
        $day = $date->format('Y-m-d');
        return 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;maxResultsPerPage=500;pageNumber=1;seasonOrProjection=PROJECTION_0_31n_SEASON;timeframeTypeCode=PROJECTED_SEASON;startDate='.$day.';endDate='.$day.';datePlaying='.$day;
    }

    public function fetch(CarbonImmutable $date): array
    {
        $url = $this->url($date);
        $response = Http::timeout(60)->retry(2, 1500)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/153 Safari/537.36',
            'Accept' => 'text/html,application/xhtml+xml,application/json',
        ])->get($url)->throw();
        $body = $response->body();

        // Fantrax may expose the rendered player grid as JSON embedded in the page.
        // Recursively walk decoded JSON objects and normalize player-looking records.
        $objects = [];
        if (preg_match_all('/<script[^>]*>(.*?)<\/script>/is', $body, $scripts)) {
            foreach ($scripts[1] as $script) {
                $script = html_entity_decode(trim($script));
                if ($script === '' || (!str_starts_with($script, '{') && !str_starts_with($script, '['))) continue;
                $decoded = json_decode($script, true);
                if (is_array($decoded)) $this->collectPlayerObjects($decoded, $objects);
            }
        }

        $rows = [];
        foreach ($objects as $rank => $p) {
            $name = $this->first($p, ['name','playerName','player_name','displayName']);
            $team = $this->first($p, ['team','teamAbbreviation','teamShortName','proTeam']);
            if (!$name || !$team) continue;
            $status = strtoupper((string)$this->first($p, ['status','rosterStatus','availability','fantasyStatus']));
            if ($status !== '' && !str_contains($status, 'FA') && !str_contains($status, 'WAIVER') && $status !== 'W') continue;
            $rows[] = [
                'player_name' => $name,
                'team' => strtoupper($team),
                'position' => $this->first($p, ['position','pos','positions']),
                'opponent' => $this->first($p, ['opponent','opp']),
                'availability' => str_contains($status, 'WAIVER') || $status === 'W' ? 'W' : 'FA',
                'waiver_day' => $this->first($p, ['waiverDay','waiver_day','waiverDate']),
                'injury_status' => $this->first($p, ['injuryStatus','injury_status']),
                'projected_fpts' => $this->numeric($this->first($p, ['projectedFantasyPoints','projectedFpts','projected_fpts','fpts','fantasyPoints'])),
                'source_rank' => $rank + 1,
                'fantrax_url' => 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;searchName='.rawurlencode($name).';positionOrGroup=ALL;',
            ];
        }

        $rows = array_values(array_unique($rows, SORT_REGULAR));
        if (!$rows) {
            if (preg_match('/no players|no results|0 results/i', strip_tags($body))) return ['url'=>$url, 'rows'=>[]];
            throw new RuntimeException('Fantrax returned no parseable player records; existing daily data preserved.');
        }
        return ['url'=>$url, 'rows'=>$rows];
    }

    private function collectPlayerObjects(array $node, array &$out): void
    {
        if ($this->first($node, ['name','playerName','player_name','displayName']) && $this->first($node, ['team','teamAbbreviation','teamShortName','proTeam'])) $out[] = $node;
        foreach ($node as $value) if (is_array($value)) $this->collectPlayerObjects($value, $out);
    }

    private function first(array $p, array $keys): mixed
    {
        foreach ($keys as $key) if (array_key_exists($key, $p) && $p[$key] !== null && $p[$key] !== '') return is_array($p[$key]) ? implode('/', $p[$key]) : $p[$key];
        return null;
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $value = preg_replace('/[^0-9.\-]/', '', (string)$value);
        return is_numeric($value) ? (float)$value : null;
    }
}
