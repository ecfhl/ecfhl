<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FantraxSchedule
{
    public const LEAGUE_ID = '092zcn40molvao69';
    private const API_VERSION = '186.1.9';

    public function forDate(CarbonImmutable $date, bool $fresh = false): array
    {
        $cacheKey = 'fantrax:schedule:'.self::LEAGUE_ID.':'.$date->format('Y-m-d');
        if ($fresh) Cache::forget($cacheKey);

        return Cache::remember($cacheKey, now()->addMinute(), function () use ($date) {
            $data = $this->fetch();

            foreach (($data['tableList'] ?? []) as $table) {
                $range = $this->dateRange((string)($table['subCaption'] ?? ''));
                if (!$range) continue;
                [$start, $end] = $range;
                if ($date->lt($start) || $date->gt($end)) continue;

                $matchups = [];
                foreach (($table['rows'] ?? []) as $row) {
                    $cells = $row['cells'] ?? [];
                    if (!is_array($cells) || count($cells) < 4) continue;

                    $awayId = trim((string)($cells[0]['teamId'] ?? ''));
                    $homeId = trim((string)($cells[2]['teamId'] ?? ''));
                    if ($awayId === '' || $homeId === '') continue;

                    $matchups[] = [
                        'away_team_id' => $awayId,
                        'away_name' => trim((string)($cells[0]['content'] ?? '')),
                        'away_score' => $this->numeric($cells[1]['content'] ?? null),
                        'home_team_id' => $homeId,
                        'home_name' => trim((string)($cells[2]['content'] ?? '')),
                        'home_score' => $this->numeric($cells[3]['content'] ?? null),
                    ];
                }

                if ($matchups) {
                    return [
                        'caption' => $table['caption'] ?? null,
                        'sub_caption' => $table['subCaption'] ?? null,
                        'start' => $start->toDateString(),
                        'end' => $end->toDateString(),
                        'matchups' => $matchups,
                    ];
                }
            }

            throw new RuntimeException('Fantrax schedule returned no matchup period for '.$date->toDateString().'.');
        });
    }

    public function periods(bool $fresh = false): array
    {
        $cacheKey = 'fantrax:schedule:periods:'.self::LEAGUE_ID;
        if ($fresh) Cache::forget($cacheKey);

        return Cache::remember($cacheKey, now()->addMinutes(5), function () {
            $data = $this->fetch();
            $periods = [];

            foreach (($data['tableList'] ?? []) as $table) {
                $range = $this->dateRange((string)($table['subCaption'] ?? ''));
                if (!$range) continue;
                [$start, $end] = $range;

                $matchups = [];
                foreach (($table['rows'] ?? []) as $row) {
                    $cells = $row['cells'] ?? [];
                    if (!is_array($cells) || count($cells) < 4) continue;

                    $awayId = trim((string)($cells[0]['teamId'] ?? ''));
                    $homeId = trim((string)($cells[2]['teamId'] ?? ''));
                    if ($awayId === '' || $homeId === '') continue;

                    $matchups[] = [
                        'away_team_id' => $awayId,
                        'away_name' => trim((string)($cells[0]['content'] ?? '')),
                        'away_score' => $this->numeric($cells[1]['content'] ?? null),
                        'home_team_id' => $homeId,
                        'home_name' => trim((string)($cells[2]['content'] ?? '')),
                        'home_score' => $this->numeric($cells[3]['content'] ?? null),
                    ];
                }

                if (!$matchups) continue;

                $periods[] = [
                    'caption' => trim((string)($table['caption'] ?? '')),
                    'sub_caption' => trim((string)($table['subCaption'] ?? '')),
                    'start' => $start->toDateString(),
                    'end' => $end->toDateString(),
                    'matchups' => $matchups,
                ];
            }

            usort($periods, fn($a,$b)=>strcmp($a['start'],$b['start']));
            return $periods;
        });
    }

    private function fetch(): array
    {
        $requestData = [
            'leagueId' => self::LEAGUE_ID,
            'view' => 'SCHEDULE',
        ];

        $url = 'https://www.fantrax.com/fantasy/league/'.self::LEAGUE_ID.'/standings;view=SCHEDULE';
        $payload = [
            'msgs' => [['method' => 'getStandings', 'data' => $requestData]],
            'uiv' => 3,
            'refUrl' => $url,
            'dt' => 0,
            'at' => 0,
            'av' => '0.0',
            'tz' => 'America/Vancouver',
            'v' => self::API_VERSION,
        ];

        $response = Http::timeout(45)->retry(2, 1000)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/154 Safari/537.36',
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Referer' => $url,
        ])->post('https://www.fantrax.com/fxpa/req?leagueId='.self::LEAGUE_ID, $payload);

        $response->throw();
        $json = $response->json();
        $data = $json['responses'][0]['data'] ?? null;
        if (!is_array($data)) {
            throw new RuntimeException('Fantrax schedule returned no response data.');
        }

        return $data;
    }

    private function dateRange(string $value): ?array
    {
        $text = trim($value, " \t\n\r\0\x0B()");

        if (!preg_match('/([A-Z][a-z]{2}\s+[A-Z][a-z]{2}\s+\d{1,2},\s+\d{4})\s+-\s+([A-Z][a-z]{2}\s+[A-Z][a-z]{2}\s+\d{1,2},\s+\d{4})/', $text, $m)) {
            return null;
        }

        try {
            $start = CarbonImmutable::createFromFormat('!D M j, Y', $m[1], 'America/Vancouver');
            $end = CarbonImmutable::createFromFormat('!D M j, Y', $m[2], 'America/Vancouver');
            return ($start && $end) ? [$start, $end] : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function numeric(mixed $value): ?float
    {
        if ($value === null || $value === '') return null;
        $clean = preg_replace('/[^0-9.\-]/', '', html_entity_decode(strip_tags((string)$value)));
        return is_numeric($clean) ? (float)$clean : null;
    }
}
