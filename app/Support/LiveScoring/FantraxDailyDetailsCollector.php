<?php

namespace App\Support\LiveScoring;

use Carbon\CarbonImmutable;
use RuntimeException;

final class FantraxDailyDetailsCollector
{
    public function fetch(FantraxClient $client, string $date, array $players = []): array
    {
        $sources = [];
        foreach (['HOCKEY_SKATING','POS_201'] as $group) {
            $pages = 1;
            for ($page = 1; $page <= $pages; $page++) {
                $data = $client->request('getPlayerStats', [
                    'statusOrTeamFilter'=>'ALL_TAKEN', 'positionOrGroup'=>$group,
                    'pageNumber'=>(string)$page, 'maxResultsPerPage'=>500, 'datePlaying'=>$date,
                    'startDate'=>$date, 'endDate'=>$date, 'timeframeTypeCode'=>'BY_DATE', 'scoringCategoryType'=>'1',
                ]);
                $pages = max(1, (int)($data['paginatedResultSet']['totalNumPages'] ?? 1));
                if ($pages > 10) throw new RuntimeException('Unexpected Fantrax daily detail page count.');
                $sources[$group.':'.$page] = $data;
            }
        }
        $known = [];
        foreach ($sources as $data) foreach (($data['statsTable'] ?? []) as $entry) $known[(string)($entry['scorer']['scorerId'] ?? '')] = true;
        foreach ($players as $player) {
            if (isset($known[$player['player_id']])) continue;
            // A player dropped since yesterday is absent from ALL_TAKEN. Search
            // narrows the fallback request, but identity is still verified by ID.
            $sources['player:'.$player['player_id']] = $client->request('getPlayerStats', [
                'statusOrTeamFilter'=>'ALL', 'searchName'=>$player['player_name'],
                'positionOrGroup'=>$player['position']==='G'?'POS_201':'HOCKEY_SKATING',
                'pageNumber'=>'1','maxResultsPerPage'=>500,'datePlaying'=>$date,
                'startDate'=>$date,'endDate'=>$date,'timeframeTypeCode'=>'BY_DATE','scoringCategoryType'=>'1',
            ]);
            $known[$player['player_id']] = true;
        }
        return $sources;
    }

    public function collect(array $sources, string $date): array
    {
        $details = [];
        foreach ($sources as $data) {
            $selection = $data['displayedSelections'] ?? [];
            if (($selection['datePlaying'] ?? null) !== $date
                || ($selection['displayedSeasonOrProjection']['timeframeTypeCode'] ?? null) !== 'BY_DATE') throw new RuntimeException('Invalid Fantrax daily detail timeframe.');
            foreach (['displayedStartDate','displayedEndDate'] as $key) {
                // Fantrax normalizes these date-picker timestamps to Eastern midnight,
                // regardless of the tz parameter. Verified against both Halifax and Vancouver.
                if (!isset($selection[$key]) || CarbonImmutable::createFromTimestampUTC($selection[$key]/1000)->setTimezone('America/New_York')->toDateString() !== $date) throw new RuntimeException('Fantrax daily detail source date mismatch.');
            }
            $headers = $data['tableHeader']['cells'] ?? [];
            $columns = [];
            foreach ($headers as $index=>$header) $columns[$header['shortName'] ?? $header['key'] ?? ''] = $index;
            $rosterStatusColumn = null;
            foreach ($headers as $index=>$header) if (($header['name'] ?? '') === 'Roster Status') $rosterStatusColumn = $index;
            if (!isset($columns['GP'])) throw new RuntimeException('Fantrax standard daily stats are missing GP.');
            $rows = $data['statsTable'] ?? [];
            if (($data['paginatedResultSet']['totalNumPages'] ?? 1) === 1 && ($data['paginatedResultSet']['totalNumResults'] ?? count($rows)) > count($rows)) throw new RuntimeException('Fantrax daily details were truncated.');
            foreach ($rows as $entry) {
                $id = (string)($entry['scorer']['scorerId'] ?? '');
                if ($id === '') throw new RuntimeException('Missing Fantrax detail player identity.');
                $cell = fn($key)=>isset($columns[$key]) ? ($entry['cells'][$columns[$key]]['content'] ?? null) : null;
                $details[$id] = ['gp'=>is_numeric($cell('GP')) ? (int)$cell('GP') : 0, 'contract'=>$cell('Con')];
                $details[$id]['source_roster_status'] = $rosterStatusColumn !== null ? ($entry['cells'][$rosterStatusColumn]['content'] ?? null) : null;
                foreach (['SV','GA','GAA','SV%','Min','SOGA'] as $stat) {
                    if (isset($columns[$stat])) $details[$id]['goalie_stats'][$stat] = $cell($stat);
                }
            }
        }
        return $details;
    }
}
