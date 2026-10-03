<?php

namespace App\Support;

use App\Support\LiveScoring\FantraxClient;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxProjectionSource
{
    public const SEASON_ID = '2026-27';
    public const PROJECTION = 'PROJECTION_0_31n_SEASON';
    public const ACTUAL = 'SEASON_31n_BY_DATE';

    public function baseline(): array
    {
        $args = ['statusOrTeamFilter'=>'ALL', 'positionOrGroup'=>'ALL', 'seasonOrProjection'=>self::PROJECTION,
            'timeframeTypeCode'=>'PROJECTED_SEASON', 'maxResultsPerPage'=>500];
        $first = $this->pages($args, [1])[1];
        $this->validate($first, self::PROJECTION);
        $second = $this->pages($args, [2])[2];
        $this->validate($second, self::PROJECTION);
        foreach ([1=>$first, 2=>$second] as $page=>$data) {
            if (($data['displayedPosOrGroup'] ?? '') !== 'ALL' || (int)$data['paginatedResultSet']['pageNumber'] !== $page) throw new RuntimeException('Fantrax returned a different projection group or page.');
        }
        $start = CarbonImmutable::createFromTimestampMs($first['displayedSelections']['displayedSeasonOrProjection']['startDate'], 'America/New_York')->toDateString();
        $rows = array_merge($this->parse($first, false), $this->parse($second, false));
        if (count($rows) !== 1000 || count(array_unique(array_column($rows, 'player_id'))) !== 1000) throw new RuntimeException('Expected 1,000 distinct Fantrax projection players.');
        usort($rows, fn($a, $b)=>$a['source_rank'] <=> $b['source_rank']);
        foreach ($rows as $i => &$row) {
            if ($row['source_rank'] !== $i + 1) throw new RuntimeException('Fantrax did not return the top 1,000 projection ranks in order.');
            $row['season_id'] = self::SEASON_ID;
            $row['season_start'] = $start;
        }
        unset($row);
        return $rows;
    }

    public function actual(string $start, string $end): array
    {
        $rows = [];
        foreach (['HOCKEY_SKATING', 'POS_201'] as $group) {
            $args = ['statusOrTeamFilter'=>'ALL', 'positionOrGroup'=>$group, 'seasonOrProjection'=>self::ACTUAL,
                'timeframeTypeCode'=>'BY_DATE', 'scoringCategoryType'=>'1', 'startDate'=>$start, 'endDate'=>$end, 'maxResultsPerPage'=>500];
            $first = $this->pages($args, [1])[1];
            $this->validate($first, self::ACTUAL, $start, $end);
            $pagination = $first['paginatedResultSet'];
            $pages = (int)$pagination['totalNumPages'];
            $total = (int)$pagination['totalNumResults'];
            if ($pages < 1 || $pages > 50 || $total < 1) throw new RuntimeException('Invalid Fantrax actual-stat pagination.');
            $count = 0;
            $groupSeen = [];
            // Parse each small batch immediately instead of retaining all 8,000+
            // rich Fantrax rows in a PHP web request with a 128 MB memory limit.
            $consume = function (array $batch) use ($group, $start, $end, $total, &$rows, &$count, &$groupSeen) {
                foreach ($batch as $page => $data) {
                    $this->validate($data, self::ACTUAL, $start, $end);
                    if (($data['displayedPosOrGroup'] ?? '') !== $group) throw new RuntimeException('Fantrax returned a different actual-stat position group.');
                    if ((int)$data['paginatedResultSet']['pageNumber'] !== $page || (int)$data['paginatedResultSet']['totalNumResults'] !== $total) throw new RuntimeException('Fantrax actual-stat pagination changed during collection.');
                    foreach ($this->parse($data, true) as $row) {
                        if (isset($groupSeen[$row['player_id']])) throw new RuntimeException('Duplicate player in Fantrax actual-stat pagination.');
                        $groupSeen[$row['player_id']] = true;
                        // Fantrax lists a few dual-position players in both groups.
                        if (isset($rows[$row['player_id']]) && $rows[$row['player_id']] !== $row) throw new RuntimeException('Conflicting skater/goalie stats for '.$row['player_id'].'.');
                        $rows[$row['player_id']] = $row;
                        $count++;
                    }
                }
            };
            $consume([1=>$first]);
            unset($first);
            foreach (array_chunk($pages > 1 ? range(2, $pages) : [], 3) as $batch) $consume($this->pages($args, $batch));
            if ($count !== $total) throw new RuntimeException('Fantrax actual-stat pages were incomplete.');
        }
        return $rows;
    }

    protected function pages(array $args, array $pages): array
    {
        $responses = Http::pool(function (Pool $pool) use ($args, $pages) {
            $requests = [];
            foreach ($pages as $page) $requests[] = $pool->as((string)$page)->timeout(45)->retry(2, 1000)->withHeaders([
                'User-Agent'=>'Mozilla/5.0', 'Accept'=>'application/json', 'Referer'=>FantraxClient::URL,
            ])->post('https://www.fantrax.com/fxpa/req?leagueId='.FantraxClient::LEAGUE_ID, [
                'msgs'=>[['method'=>'getPlayerStats', 'data'=>$args + ['pageNumber'=>(string)$page]]],
                'uiv'=>3, 'refUrl'=>FantraxClient::URL, 'dt'=>0, 'at'=>0, 'av'=>'0.0', 'tz'=>'America/Halifax', 'v'=>'186.1.9',
            ]);
            return $requests;
        });
        $result = [];
        foreach ($responses as $page => $response) {
            if ($response instanceof \Throwable) throw $response;
            $response->throw();
            $json = $response->json();
            $data = $json['responses'][0]['data'] ?? null;
            if (!is_array($data) || !empty($json['pageError']) || !empty($json['responses'][0]['error'])) throw new RuntimeException('Fantrax projection request returned invalid data.');
            $result[(int)$page] = $data;
        }
        return $result;
    }

    private function validate(array $data, string $provider, ?string $start = null, ?string $end = null): void
    {
        $selection = $data['displayedSelections'] ?? [];
        if (($data['displayedStatusOrTeam'] ?? '') !== 'ALL' || ($selection['displayedSeasonOrProjection']['code'] ?? '') !== $provider
            || ($selection['datePlaying'] ?? '') !== 'ALL' || ($selection['searchName'] ?? '') !== '') throw new RuntimeException('Fantrax returned a different projection provider or player filter.');
        if ($start !== null) {
            foreach (['displayedStartDate'=>$start, 'displayedEndDate'=>$end] as $field => $expected) {
                $actual = isset($selection[$field]) ? CarbonImmutable::createFromTimestampMs($selection[$field], 'America/New_York')->toDateString() : null;
                if ($actual !== $expected) throw new RuntimeException('Fantrax returned a different actual-stat date range.');
            }
        }
        if (!isset($data['statsTable'], $data['tableHeader']['cells'], $data['paginatedResultSet'])) throw new RuntimeException('Incomplete Fantrax projection response.');
    }

    private function parse(array $data, bool $actual): array
    {
        $columns = [];
        foreach ($data['tableHeader']['cells'] as $i => $column) {
            foreach (['key', 'shortName'] as $field) if (isset($column[$field])) $columns[$column[$field]] = $i;
        }
        foreach ($actual ? ['fpts', 'GP'] : ['fpts', 'fptsPerGame'] as $column) if (!isset($columns[$column])) throw new RuntimeException('Missing Fantrax projection column: '.$column);
        $rows = [];
        foreach ($data['statsTable'] as $entry) {
            $scorer = $entry['scorer'] ?? [];
            $id = (string)($scorer['scorerId'] ?? '');
            if ($id === '') throw new RuntimeException('Fantrax projection player has no ID.');
            $cell = fn($key)=>$entry['cells'][$columns[$key]]['content'] ?? null;
            $points = $this->number($cell('fpts'));
            if ($actual) {
                $games = $this->number($cell('GP'));
                if ($games < 0 || floor($games) !== $games) throw new RuntimeException('Invalid Fantrax games played.');
                $rows[] = ['player_id'=>$id, 'fpts'=>$points, 'gp'=>(int)$games];
            } else {
                $position = (string)($scorer['posShortNames'] ?? '');
                $rows[] = ['player_id'=>$id, 'player_name'=>(string)$scorer['name'], 'nhl_team'=>$scorer['teamShortName'] ?? null,
                    'position'=>$position, 'source_rank'=>(int)($scorer['rank'] ?? 0),
                    'fantrax_fpts_per_game'=>$this->number($cell('fptsPerGame')), 'fantrax_season_fpts'=>$points];
            }
        }
        return $rows;
    }

    private function number(mixed $value): float
    {
        $value = trim(html_entity_decode(strip_tags((string)$value)));
        if (in_array($value, ['', '-', '—'], true)) return 0.0;
        $value = str_replace(',', '', $value);
        if (!is_numeric($value) || !is_finite((float)$value)) throw new RuntimeException('Invalid numeric Fantrax projection value.');
        return (float)$value;
    }
}
