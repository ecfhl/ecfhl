<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxTeamRosters
{
    public const LEAGUE_ID = '092zcn40molvao69';
    private const API_VERSION = '186.1.9';

    public function fetch(CarbonImmutable $date): array
    {
        $league = $this->get('https://www.fantrax.com/fxea/general/getLeagueInfo', ['leagueId'=>self::LEAGUE_ID]);
        $rosters = $this->get('https://www.fantrax.com/fxea/general/getTeamRosters', ['leagueId'=>self::LEAGUE_ID]);
        $players = $this->get('https://www.fantrax.com/fxea/general/getPlayerIds', ['sport'=>'NHL']);
        $stats = $this->fetchStats($date);

        if (!is_array($rosters['rosters'] ?? null)) {
            throw new RuntimeException('Fantrax team rosters returned no rosters.');
        }
        if (!is_array($players)) {
            throw new RuntimeException('Fantrax player IDs returned invalid data.');
        }

        $teamInfo = is_array($league['teamInfo'] ?? null) ? $league['teamInfo'] : [];
        $statsByKey = [];
        $statsByName = [];
        $duplicateNames = [];
        foreach ($stats as $row) {
            $key = $this->key($row['player_name'] ?? '', $row['nhl_team'] ?? '');
            if ($key !== '|') $statsByKey[$key] = $row;
            $nameKey = $this->nameKey($row['player_name'] ?? '');
            if ($nameKey !== '') {
                if (isset($statsByName[$nameKey])) $duplicateNames[$nameKey] = true;
                else $statsByName[$nameKey] = $row;
            }
        }
        foreach (array_keys($duplicateNames) as $nameKey) unset($statsByName[$nameKey]);

        $rows = [];
        foreach ($rosters['rosters'] as $teamId => $team) {
            $teamName = trim((string)($team['teamName'] ?? ($teamInfo[$teamId]['name'] ?? $teamId)));
            foreach (($team['rosterItems'] ?? []) as $item) {
                $playerId = trim((string)($item['id'] ?? ''));
                if ($playerId === '') continue;
                $p = $players[$playerId] ?? null;
                if (!is_array($p)) continue;

                $name = trim((string)($p['name'] ?? ''));
                $nhlTeam = strtoupper(trim((string)($p['team'] ?? '')));
                if ($name === '') continue;

                $position = $this->position((string)($p['position'] ?? ($item['position'] ?? '')));
                $status = strtoupper(trim((string)($item['status'] ?? 'ACTIVE')));
                $stat = $statsByKey[$this->key($name, $nhlTeam)] ?? ($statsByName[$this->nameKey($name)] ?? null);
                $oppRaw = trim((string)($stat['opponent'] ?? ''));
                $away = strtoupper((string)($stat['home_away'] ?? '')) === 'AWAY' || str_starts_with($oppRaw, '@');
                $opponent = trim((string)($stat['opponent'] ?? ''));

                $rows[] = [
                    'fantasy_team_id'=>(string)$teamId,
                    'fantasy_team_name'=>$teamName,
                    'player_id'=>$playerId,
                    'player_name'=>$name,
                    'nhl_team'=>$nhlTeam ?: null,
                    'position'=>$position,
                    'roster_status'=>$status,
                    'is_bench'=>in_array($status, ['RESERVE','BENCH'], true),
                    'is_ir'=>$status === 'INJURED_RESERVE' || !empty($stat['injury_status']),
                    'injury_status'=>$stat['injury_status'] ?? null,
                    'is_playing'=>(bool)($stat['is_playing'] ?? false),
                    'opponent'=>$opponent !== '' ? $opponent : null,
                    'home_away'=>$opponent !== '' ? ($away ? 'AWAY' : 'HOME') : null,
                    'game_time'=>$stat['game_time'] ?? null,
                    'projected_fpts'=>$stat['projected_fpts'] ?? null,
                ];
            }
        }

        if (!$rows) throw new RuntimeException('Fantrax team rosters contained no parseable players.');
        return ['rows'=>$rows, 'period'=>$rosters['period'] ?? null];
    }

    private function get(string $url, array $query): array
    {
        $response = Http::timeout(45)->retry(2, 1200)->withHeaders([
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept'=>'application/json',
        ])->get($url, $query);
        $response->throw();
        $json = $response->json();
        if (!is_array($json)) throw new RuntimeException('Fantrax external API returned invalid JSON.');
        return $json;
    }

    private function fetchStats(CarbonImmutable $date): array
    {
        $day = $date->format('Y-m-d');

        // Fantrax's datePlaying filter is the reliable way to identify players
        // actually scheduled to play on a given day. Keep projections separate
        // so players without a game still retain their season projected FPts.
        $playing = $this->fetchStatsPage([
            'reload'=>'1',
            'statusOrTeamFilter'=>'ALL_TAKEN',
            'pageNumber'=>'1',
            'datePlaying'=>$day,
            'sortType'=>'STATUS',
            'maxResultsPerPage'=>500,
            'positionOrGroup'=>'ALL',
        ]);

        $projections = $this->fetchStatsPage([
            'statusOrTeamFilter'=>'ALL_TAKEN',
            'pageNumber'=>'1',
            'seasonOrProjection'=>'PROJECTION_0_31n_SEASON',
            'timeframeTypeCode'=>'PROJECTED_SEASON',
            'maxResultsPerPage'=>500,
            'positionOrGroup'=>'ALL',
        ]);

        $projectionByKey = [];
        foreach ($projections as $row) {
            $row['is_playing'] = false;
            $projectionByKey[$this->key($row['player_name'] ?? '', $row['nhl_team'] ?? '')] = $row;
        }

        $rows = $projectionByKey;
        foreach ($playing as $row) {
            $row['is_playing'] = true;
            $key = $this->key($row['player_name'] ?? '', $row['nhl_team'] ?? '');
            $base = $rows[$key] ?? $row;
            $base['is_playing'] = true;
            $base['opponent'] = $row['opponent'] ?? null;
            $base['home_away'] = $row['home_away'] ?? null;
            $base['game_time'] = $row['game_time'] ?? null;
            $base['injury_status'] = $row['injury_status'] ?? ($base['injury_status'] ?? null);
            if (($base['projected_fpts'] ?? null) === null && ($row['projected_fpts'] ?? null) !== null) {
                $base['projected_fpts'] = $row['projected_fpts'];
            }
            $rows[$key] = $base;
        }

        return array_values($rows);
    }

    private function fetchStatsPage(array $requestData): array
    {
        $segments = [];
        foreach ($requestData as $key=>$value) $segments[] = $key.'='.rawurlencode((string)$value);
        $url = 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;'.implode(';',$segments);

        $payload = [
            'msgs'=>[['method'=>'getPlayerStats','data'=>$requestData]],
            'uiv'=>3,'refUrl'=>$url,'dt'=>0,'at'=>0,'av'=>'0.0','tz'=>'America/Halifax','v'=>self::API_VERSION,
        ];

        $response = Http::timeout(60)->retry(2, 1500)->withHeaders([
            'User-Agent'=>'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept'=>'application/json','Content-Type'=>'application/json','Referer'=>$url,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID, $payload);
        $response->throw();
        $json = $response->json();
        $data = $json['responses'][0]['data'] ?? null;
        if (!is_array($data)) throw new RuntimeException('Fantrax roster player stats returned no response data.');

        $columns = [];
        foreach (($data['tableHeader']['cells'] ?? []) as $i => $col) {
            foreach (['key','sortType','shortName'] as $field) {
                $key = trim((string)($col[$field] ?? ''));
                if ($key !== '' && !isset($columns[$key])) $columns[$key] = $i;
            }
        }
        $cell = function(array $entry, array $ids) use ($columns): ?array {
            foreach ($ids as $id) if (isset($columns[$id])) return $entry['cells'][$columns[$id]] ?? null;
            return null;
        };

        $rows = [];
        foreach (($data['statsTable'] ?? []) as $entry) {
            $scorer = $entry['scorer'] ?? [];
            $name = trim((string)($scorer['name'] ?? ''));
            $team = strtoupper(trim((string)($scorer['teamShortName'] ?? '')));
            if ($name === '' || $team === '') continue;

            $oppCell = $cell($entry, ['opponent','Opp']);
            $oppText = trim(preg_replace('/\s+/',' ',html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'], ' ', (string)($oppCell['content'] ?? ''))))));
            $opponent = null;
            $homeAway = null;
            $gameTime = null;
            if ($oppText !== '') {
                if (preg_match('/^(?:vs\.?\s*)?@?([A-Z]{2,4})\b\s*(.*)$/i', $oppText, $m)) {
                    $opponent = strtoupper($m[1]);
                    $homeAway = str_contains($oppText, '@') ? 'AWAY' : 'HOME';
                    $gameTime = trim($m[2]) !== '' ? trim($m[2]) : null;
                } else {
                    $opponent = $oppText;
                }
            }

            $fptsCell = $cell($entry, ['fpts','SCORE','FPts']);
            $rows[] = [
                'player_name'=>$name,
                'nhl_team'=>$team,
                'opponent'=>$opponent,
                'home_away'=>$homeAway,
                'game_time'=>$gameTime,
                'projected_fpts'=>$this->numeric($fptsCell['content'] ?? null),
                'injury_status'=>$this->injury($scorer['icons'] ?? []),
                'is_playing'=>false,
            ];
        }
        return $rows;
    }

    private function key(string $name, string $team): string
    {
        return $this->nameKey($name).'|'.$this->teamKey($team);
    }

    private function nameKey(string $name): string
    {
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($name))) ?? '';
    }

    private function teamKey(string $team): string
    {
        $team = strtoupper(trim($team));
        return match($team) {
            'LA' => 'LAK',
            'NJ' => 'NJD',
            'SJ' => 'SJS',
            'TB' => 'TBL',
            default => $team,
        };
    }

    private function position(string $value): ?string
    {
        $v = strtoupper($value);
        if (preg_match('/(^|[,\/ ])G($|[,\/ ])/',$v)) return 'G';
        if (preg_match('/(^|[,\/ ])D($|[,\/ ])/',$v)) return 'D';
        if (preg_match('/\b(C|LW|RW|F)\b/',$v)) return 'F';
        return $value !== '' ? $value : null;
    }

    private function injury(array $icons): ?string
    {
        foreach ($icons as $icon) {
            $type=(string)($icon['typeId']??'');
            $tip=trim((string)($icon['tooltip']??''));
            if (in_array($type,['1','2','30'],true) || preg_match('/injur|\bIR\b|day-to-day|out indefinitely/i',$tip)) {
                return preg_match('/injured reserve|injured list|\bIR\b/i',$tip) ? 'IR' : ($tip !== '' ? $tip : 'INJ');
            }
        }
        return null;
    }

    private function numeric(mixed $value): ?float
    {
        if ($value===null || $value==='') return null;
        $value=preg_replace('/[^0-9.\-]/','',html_entity_decode(strip_tags((string)$value)));
        return is_numeric($value) ? (float)$value : null;
    }
}
