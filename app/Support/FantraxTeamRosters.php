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
        foreach ($stats as $row) {
            $key = $this->key($row['player_name'] ?? '', $row['nhl_team'] ?? '');
            if ($key !== '|') $statsByKey[$key] = $row;
        }

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
                $stat = $statsByKey[$this->key($name, $nhlTeam)] ?? null;
                $oppRaw = trim((string)($stat['opponent'] ?? ''));
                $away = str_starts_with($oppRaw, '@');
                $opponent = ltrim($oppRaw, '@');

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
                    'opponent'=>$opponent !== '' ? $opponent : null,
                    'home_away'=>$opponent !== '' ? ($away ? 'AWAY' : 'HOME') : null,
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
        $url = 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;maxResultsPerPage=500;pageNumber=1;positionOrGroup=ALL;seasonOrProjection=PROJECTION_0_31n_SEASON;timeframeTypeCode=PROJECTED_SEASON;startDate='.$day.';endDate='.$day.';datePlaying='.$day.';statusOrTeamFilter=ALL_TAKEN';
        $payload = [
            'msgs'=>[['method'=>'getPlayerStats','data'=>[
                'statusOrTeamFilter'=>'ALL_TAKEN',
                'maxResultsPerPage'=>500,
                'pageNumber'=>'1',
                'seasonOrProjection'=>'PROJECTION_0_31n_SEASON',
                'timeframeTypeCode'=>'PROJECTED_SEASON',
                'startDate'=>$day,
                'endDate'=>$day,
                'datePlaying'=>$day,
                'positionOrGroup'=>'ALL',
            ]]],
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
            $opp = trim(html_entity_decode(strip_tags(str_replace(['<br>','<br/>','<br />'], ' ', (string)($oppCell['content'] ?? '')))));
            if ($opp !== '') $opp = preg_split('/\s+/', $opp)[0];
            $fptsCell = $cell($entry, ['fpts','SCORE','FPts']);
            $rows[] = [
                'player_name'=>$name,
                'nhl_team'=>$team,
                'opponent'=>$opp,
                'projected_fpts'=>$this->numeric($fptsCell['content'] ?? null),
                'injury_status'=>$this->injury($scorer['icons'] ?? []),
            ];
        }
        return $rows;
    }

    private function key(string $name, string $team): string
    {
        $norm = preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($name))) ?? '';
        return $norm.'|'.strtoupper(trim($team));
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
