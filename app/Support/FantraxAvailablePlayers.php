<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxAvailablePlayers
{
    public const LEAGUE_ID = '092zcn40molvao69';
    private const API_VERSION = '186.1.9';

    public function url(CarbonImmutable $date, string $positionGroup = 'ALL'): string
    {
        $day = $date->format('Y-m-d');
        return 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;maxResultsPerPage=500;pageNumber=1;positionOrGroup='.$positionGroup.';seasonOrProjection=PROJECTION_0_31n_SEASON;timeframeTypeCode=PROJECTED_SEASON;startDate='.$day.';endDate='.$day.';datePlaying='.$day;
    }

    public function fetch(CarbonImmutable $date, string $positionGroup = 'ALL'): array
    {
        $day = $date->format('Y-m-d');
        $url = $this->url($date, $positionGroup);
        $requestData = [
            'statusOrTeamFilter' => 'ALL_AVAILABLE',
            'maxResultsPerPage' => 500,
            'pageNumber' => '1',
            'seasonOrProjection' => 'PROJECTION_0_31n_SEASON',
            'timeframeTypeCode' => 'PROJECTED_SEASON',
            'startDate' => $day,
            'endDate' => $day,
            'datePlaying' => $day,
        ];
        if ($positionGroup !== 'ALL') $requestData['positionOrGroup'] = $positionGroup;

        $payload = [
            'msgs' => [['method' => 'getPlayerStats', 'data' => $requestData]],
            'uiv' => 3, 'refUrl' => $url, 'dt' => 0, 'at' => 0, 'av' => '0.0',
            'tz' => 'America/Vancouver', 'v' => self::API_VERSION,
        ];

        $response = Http::timeout(60)->retry(2, 1500)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/153 Safari/537.36',
            'Accept' => 'application/json', 'Content-Type' => 'application/json', 'Referer' => $url,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID, $payload);
        $response->throw();
        $json = $response->json();
        if (!is_array($json)) throw new RuntimeException('Fantrax player pool returned invalid JSON.');
        if (!empty($json['pageError']['code'])) throw new RuntimeException('Fantrax API error '.$json['pageError']['code'].': '.($json['pageError']['text'] ?? 'unknown error'));
        $data = $json['responses'][0]['data'] ?? null;
        if (!is_array($data)) throw new RuntimeException('Fantrax player pool returned no response data.');
        $stats = $data['statsTable'] ?? [];
        if (!$stats) {
            $total = $data['paginatedResultSet']['totalNumResults'] ?? $data['paginatedResultSet']['totalResults'] ?? null;
            if ((int)$total === 0) return ['url'=>$url, 'rows'=>[]];
            throw new RuntimeException('Fantrax player pool returned no player rows.');
        }

        $columns = [];
        foreach (($data['tableHeader']['cells'] ?? []) as $i => $col) foreach (['key','sortType','shortName'] as $field) {
            $key = trim((string)($col[$field] ?? '')); if ($key !== '' && !isset($columns[$key])) $columns[$key] = $i;
        }
        $cell = function(array $entry, array $ids) use ($columns): ?array { foreach ($ids as $id) if (isset($columns[$id])) return $entry['cells'][$columns[$id]] ?? null; return null; };

        $rows = [];
        foreach ($stats as $rank => $entry) {
            $scorer = $entry['scorer'] ?? [];
            $name = trim((string)($scorer['name'] ?? '')); $team = strtoupper(trim((string)($scorer['teamShortName'] ?? '')));
            if ($name === '' || $team === '') continue;
            $statusCell = $cell($entry, ['status','STATUS','Sta']);
            $statusRaw = trim(html_entity_decode(strip_tags((string)($statusCell['content'] ?? '')))); $statusUpper = strtoupper($statusRaw);
            if ($statusUpper !== 'FA' && !str_starts_with($statusUpper, 'W')) continue;
            $oppCell = $cell($entry, ['opponent','Opp']);
            $oppText = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'], ' ', (string)($oppCell['content'] ?? ''))))));
            $opp = '';
            $gameTime = null;
            if ($oppText !== '') {
                $parts = preg_split('/\s+/', $oppText);
                $opp = (string)($parts[0] ?? '');
                if (preg_match('/(?:(?:Mon|Tue|Wed|Thu|Fri|Sat|Sun)\s+)?(\d{1,2}:\d{2}\s*(?:AM|PM))/i', $oppText, $m)) {
                    $gameTime = $this->atlanticGameTime($m[1], $day);
                }
            }
            $gameStarted = $gameTime === null && (
                preg_match('/\b\d+\b.*\b\d+\b/', $oppText)
                || preg_match('/\b(?:FINAL|F|OT|SO|1ST|2ND|3RD|P1|P2|P3|LIVE)\b/i', $oppText)
            );
            $fptsCell = $cell($entry, ['fpts','SCORE','FPts']); $fpts = $this->numeric($fptsCell['content'] ?? null);
            $posValue = $scorer['posShortNames'] ?? $entry['multiPositions'] ?? '';
            $posText = is_array($posValue) ? implode(',', $posValue) : html_entity_decode(strip_tags((string)$posValue));
            $position = $this->position($posText);

            // Never trust the requested Fantrax position filter by itself. Fantrax can
            // return the unfiltered player pool even when positionOrGroup=G is sent.
            // The goalie collector must validate each returned player's real position.
            if ($positionGroup === 'G' && $position !== 'G') continue;

            $injury = $this->injury($scorer['icons'] ?? []); $waiverDay = null;
            if (preg_match('/W\s*\(([^)]+)\)/i', $statusRaw, $m)) $waiverDay = trim($m[1]);
            $rows[] = ['player_name'=>$name,'team'=>$team,'position'=>$position,'opponent'=>$opp,'game_time'=>$gameTime,'game_started'=>(bool)$gameStarted,'availability'=>str_starts_with($statusUpper,'W')?'W':'FA','waiver_day'=>$waiverDay,'injury_status'=>$injury,'projected_fpts'=>$fpts,'source_rank'=>(int)($scorer['rank']??($rank+1)),'fantrax_url'=>'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;searchName='.rawurlencode($name).';positionOrGroup=ALL;'];
        }
        if (!$rows && $positionGroup !== 'G') throw new RuntimeException('Fantrax returned player rows but none were parseable as available players.');
        return ['url'=>$url, 'rows'=>$rows];
    }

    private function position(string $value): ?string { $v=strtoupper($value); if(preg_match('/(^|[,\/ ])G($|[,\/ ])/',$v))return 'G'; if(preg_match('/(^|[,\/ ])D($|[,\/ ])/',$v))return 'D'; if(preg_match('/\b(C|LW|RW|F)\b/',$v))return 'F'; return $value!==''?$value:null; }
    private function injury(array $icons): ?string { foreach($icons as $icon){$type=(string)($icon['typeId']??'');$tip=trim((string)($icon['tooltip']??''));if(in_array($type,['1','2','30'],true)||preg_match('/injur|\bIR\b|day-to-day|out indefinitely/i',$tip))return preg_match('/injured reserve|injured list|\bIR\b/i',$tip)?'IR':($tip!==''?$tip:'INJ');}return null; }
    private function atlanticGameTime(string $value, string $date): ?string
    {
        $value = trim($value);
        if ($value === '' || $date === '') return null;
        try {
            $eastern = CarbonImmutable::createFromFormat('!Y-m-d g:i A', $date.' '.strtoupper(preg_replace('/\s+/', ' ', $value)), 'America/New_York');
            if (!$eastern) return null;
            return $eastern->setTimezone('America/Halifax')->format('D g:iA');
        } catch (\Throwable) {
            return null;
        }
    }

    private function numeric(mixed $value): ?float { if($value===null||$value==='')return null;$value=preg_replace('/[^0-9.\-]/','',html_entity_decode(strip_tags((string)$value)));return is_numeric($value)?(float)$value:null; }
}
