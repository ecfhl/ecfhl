<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxDailyScores
{
    public const LEAGUE_ID = '092zcn40molvao69';

    public function url(CarbonImmutable $date): string
    {
        $day = $date->format('Y-m-d');
        return 'https://www.fantrax.com/fxpa/downloadPlayerStats?'.http_build_query([
            'leagueId' => self::LEAGUE_ID,
            'view' => 'STATS',
            'positionOrGroup' => 'ALL',
            'pageNumber' => 1,
            'maxResultsPerPage' => 500,
            'datePlaying' => $day,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function fetch(CarbonImmutable $date): array
    {
        $url = $this->url($date);
        $response = Http::timeout(60)->retry(2, 1000)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept' => 'text/csv,text/plain,*/*',
            'Referer' => 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/players;reload=1;datePlaying='.$date->format('Y-m-d').';pageNumber=1;maxResultsPerPage=500',
        ])->get($url);

        $response->throw();
        $body = ltrim($response->body(), "\xEF\xBB\xBF");
        if ($body === '' || str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[')) {
            throw new RuntimeException('Fantrax CSV export returned no CSV data.');
        }

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $body);
        rewind($handle);

        $header = fgetcsv($handle);
        if (!is_array($header) || !$header) {
            fclose($handle);
            throw new RuntimeException('Fantrax CSV export returned no header row.');
        }

        $header = array_map(fn($v) => trim((string)$v), $header);
        $normalized = [];
        foreach ($header as $i => $name) {
            $key = preg_replace('/[^a-z0-9]+/', '', strtolower($name));
            if ($key !== '') $normalized[$key] = $i;
        }

        $column = function(array $names) use ($normalized): ?int {
            foreach ($names as $name) {
                $key = preg_replace('/[^a-z0-9]+/', '', strtolower($name));
                if (array_key_exists($key, $normalized)) return $normalized[$key];
            }
            return null;
        };

        $playerCol = $column(['Player','Name']);
        $teamCol = $column(['Team']);
        $positionCol = $column(['Position','Pos']);
        $statusCol = $column(['Status','Owner']);
        $fptsCol = $column(['FPts','FPTS','Fantasy Points','FantasyPoints','Score']);

        if ($playerCol === null || $fptsCol === null) {
            fclose($handle);
            throw new RuntimeException('Fantrax CSV is missing Player or FPts. Headers: '.implode(', ', $header));
        }

        $rows = [];
        while (($csv = fgetcsv($handle)) !== false) {
            $name = trim((string)($csv[$playerCol] ?? ''));
            if ($name === '') continue;

            $team = $teamCol === null ? null : strtoupper(trim((string)($csv[$teamCol] ?? '')));
            $rawPoints = trim((string)($csv[$fptsCol] ?? ''));
            $points = $this->numeric($rawPoints);

            $rows[] = [
                'player_name' => $name,
                'nhl_team' => $team !== '' ? $team : null,
                'position' => $positionCol === null ? null : trim((string)($csv[$positionCol] ?? '')),
                'fantasy_status' => $statusCol === null ? null : trim((string)($csv[$statusCol] ?? '')),
                'today_fpts' => $points ?? 0.0,
            ];
        }
        fclose($handle);

        if (!$rows) throw new RuntimeException('Fantrax CSV export contained no player rows.');
        return ['url'=>$url, 'headers'=>$header, 'rows'=>$rows];
    }

    private function numeric(?string $value): ?float
    {
        if ($value === null || trim($value) === '') return null;
        $clean = preg_replace('/[^0-9.\-]/', '', $value);
        return is_numeric($clean) ? (float)$clean : null;
    }
}
