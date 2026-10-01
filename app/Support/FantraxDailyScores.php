<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxDailyScores
{
    public const LEAGUE_ID = '092zcn40molvao69';
    private const API_VERSION = '186.1.9';

    public function url(CarbonImmutable $date): string
    {
        $day = $date->format('Y-m-d');

        return 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID
            .'/players;reload=1;datePlaying=ALL'
            .';pageNumber=1;statusOrTeamFilter=ALL'
            .';startDate='.$day.';endDate='.$day
            .';timeframeTypeCode=BY_DATE;maxResultsPerPage=500';
    }

    public function fetch(CarbonImmutable $date): array
    {
        $day = $date->format('Y-m-d');
        $url = $this->url($date);

        $requestData = [
            'statusOrTeamFilter' => 'ALL',
            'positionOrGroup' => 'ALL',
            'pageNumber' => '1',
            'maxResultsPerPage' => 500,
            'datePlaying' => 'ALL',
            'startDate' => $day,
            'endDate' => $day,
            'timeframeTypeCode' => 'BY_DATE',
        ];

        $payload = [
            'msgs' => [[
                'method' => 'getPlayerStats',
                'data' => $requestData,
            ]],
            'uiv' => 3,
            'refUrl' => $url,
            'dt' => 0,
            'at' => 0,
            'av' => '0.0',
            'tz' => 'America/Halifax',
            'v' => self::API_VERSION,
        ];

        $response = Http::timeout(60)->retry(2, 1200)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Referer' => $url,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID, $payload);

        $response->throw();

        $json = $response->json();
        if (!is_array($json)) {
            throw new RuntimeException('Fantrax live player page returned invalid JSON.');
        }
        if (!empty($json['pageError']['code'])) {
            throw new RuntimeException(
                'Fantrax API error '.$json['pageError']['code'].': '.($json['pageError']['text'] ?? 'unknown error')
            );
        }

        $data = $json['responses'][0]['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('Fantrax live player page returned no response data.');
        }

        $columns = [];
        $normalizedColumns = [];

        $registerColumn = static function (mixed $value, int $index) use (&$columns, &$normalizedColumns): void {
            if (is_array($value)) {
                array_walk_recursive($value, function ($nested) use ($index, &$columns, &$normalizedColumns) {
                    if (!is_scalar($nested)) return;
                    $text = trim(html_entity_decode(strip_tags((string)$nested)));
                    if ($text === '') return;

                    if (!isset($columns[$text])) $columns[$text] = $index;

                    $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($text));
                    if ($normalized !== '' && !isset($normalizedColumns[$normalized])) {
                        $normalizedColumns[$normalized] = $index;
                    }
                });
                return;
            }

            if (!is_scalar($value)) return;
            $text = trim(html_entity_decode(strip_tags((string)$value)));
            if ($text === '') return;

            if (!isset($columns[$text])) $columns[$text] = $index;

            $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower($text));
            if ($normalized !== '' && !isset($normalizedColumns[$normalized])) {
                $normalizedColumns[$normalized] = $index;
            }
        };

        foreach (($data['tableHeader']['cells'] ?? []) as $i => $col) {
            if (!is_array($col)) continue;
            foreach ($col as $value) $registerColumn($value, $i);
        }

        $cell = static function (array $entry, array $ids) use ($columns, $normalizedColumns): ?array {
            foreach ($ids as $id) {
                if (isset($columns[$id])) {
                    return $entry['cells'][$columns[$id]] ?? null;
                }

                $normalized = preg_replace('/[^a-z0-9]+/', '', strtolower((string)$id));
                if ($normalized !== '' && isset($normalizedColumns[$normalized])) {
                    return $entry['cells'][$normalizedColumns[$normalized]] ?? null;
                }
            }
            return null;
        };

        $rows = [];
        foreach (($data['statsTable'] ?? []) as $entry) {
            $scorer = $entry['scorer'] ?? [];
            $name = trim((string)($scorer['name'] ?? ''));
            $team = strtoupper(trim((string)($scorer['teamShortName'] ?? '')));
            if ($name === '' || $team === '') {
                continue;
            }

            $fptsCell = $cell($entry, ['fpts','SCORE','FPts','Fantasy Points']);
            $statusCell = $cell($entry, ['status','STATUS','Sta']);
            $gpCell = $cell($entry, ['GP','gp','Games Played','gamesPlayed','Games']);
            $gCell = $cell($entry, ['G','g','Goals','goals']);
            $aCell = $cell($entry, ['A','a','Assists','assists']);
            $ppgCell = $cell($entry, ['PPG','ppg','Power Play Goals','Power-Play Goals','powerPlayGoals']);
            $shgCell = $cell($entry, ['SHG','shg','Short Handed Goals','Short-Handed Goals','shortHandedGoals']);
            $gwgCell = $cell($entry, ['GWG','gwg','Game Winning Goals','Game-Winning Goals','gameWinningGoals']);
            $wCell = $cell($entry, ['W','w','Wins','wins']);
            $soCell = $cell($entry, ['SO','so','Shutouts','shutouts']);

            $posValue = $scorer['posShortNames'] ?? $entry['multiPositions'] ?? '';
            $posText = is_array($posValue)
                ? implode(',', $posValue)
                : html_entity_decode(strip_tags((string)$posValue));

            $status = trim(html_entity_decode(strip_tags((string)($statusCell['content'] ?? ''))));

            $rows[] = [
                'player_name' => $name,
                'nhl_team' => $team,
                'position' => $this->position($posText),
                'fantasy_status' => $status !== '' ? $status : null,
                'today_fpts' => $this->numeric($fptsCell['content'] ?? null) ?? 0.0,
                'gp' => (int)($this->numeric($gpCell['content'] ?? null) ?? 0),
                'g' => (int)($this->numeric($gCell['content'] ?? null) ?? 0),
                'a' => (int)($this->numeric($aCell['content'] ?? null) ?? 0),
                'ppg' => (int)($this->numeric($ppgCell['content'] ?? null) ?? 0),
                'shg' => (int)($this->numeric($shgCell['content'] ?? null) ?? 0),
                'gwg' => (int)($this->numeric($gwgCell['content'] ?? null) ?? 0),
                'w' => (int)($this->numeric($wCell['content'] ?? null) ?? 0),
                'so' => (int)($this->numeric($soCell['content'] ?? null) ?? 0),
            ];
        }

        if (!$rows) {
            $total = $data['paginatedResultSet']['totalNumResults']
                ?? $data['paginatedResultSet']['totalResults']
                ?? null;

            if ((int)$total === 0) {
                return ['url'=>$url, 'rows'=>[]];
            }

            throw new RuntimeException('Fantrax live player page returned no parseable player rows.');
        }

        return ['url'=>$url, 'rows'=>$rows];
    }

    private function position(string $value): ?string
    {
        $v = strtoupper($value);

        if (preg_match('/(^|[,\/ ])G($|[,\/ ])/',$v)) return 'G';
        if (preg_match('/(^|[,\/ ])D($|[,\/ ])/',$v)) return 'D';
        if (preg_match('/\b(C|LW|RW|F)\b/',$v)) return 'F';

        return $value !== '' ? $value : null;
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;

        $clean = preg_replace(
            '/[^0-9.\-]/',
            '',
            html_entity_decode(strip_tags((string)$value))
        );

        return is_numeric($clean) ? (float)$clean : null;
    }
}
