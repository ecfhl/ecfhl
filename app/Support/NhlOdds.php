<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class NhlOdds
{
    private const TEAMS = [
        'Anaheim Ducks'=>'ANA','Boston Bruins'=>'BOS','Buffalo Sabres'=>'BUF','Calgary Flames'=>'CGY',
        'Carolina Hurricanes'=>'CAR','Chicago Blackhawks'=>'CHI','Colorado Avalanche'=>'COL','Columbus Blue Jackets'=>'CBJ',
        'Dallas Stars'=>'DAL','Detroit Red Wings'=>'DET','Edmonton Oilers'=>'EDM','Florida Panthers'=>'FLA',
        'Los Angeles Kings'=>'LAK','Minnesota Wild'=>'MIN','Montreal Canadiens'=>'MTL','Nashville Predators'=>'NSH',
        'New Jersey Devils'=>'NJD','New York Islanders'=>'NYI','New York Rangers'=>'NYR','Ottawa Senators'=>'OTT',
        'Philadelphia Flyers'=>'PHI','Pittsburgh Penguins'=>'PIT','San Jose Sharks'=>'SJS','Seattle Kraken'=>'SEA',
        'St. Louis Blues'=>'STL','Tampa Bay Lightning'=>'TBL','Toronto Maple Leafs'=>'TOR','Utah Mammoth'=>'UTA',
        'Vancouver Canucks'=>'VAN','Vegas Golden Knights'=>'VGK','Washington Capitals'=>'WSH','Winnipeg Jets'=>'WPG',
    ];

    private function teamAbbreviation(string $name): ?string
    {
        $name=trim($name);
        if($name==='')return null;
        if(isset(self::TEAMS[$name]))return self::TEAMS[$name];

        // The Odds API/bookmakers are not completely consistent about punctuation
        // in team names (notably St. Louis / St Louis). Normalize both sides.
        $normalized=strtolower(preg_replace('/[^a-z0-9]+/i','',$name));
        foreach(self::TEAMS as $teamName=>$abbr){
            if(strtolower(preg_replace('/[^a-z0-9]+/i','',$teamName))===$normalized)return $abbr;
        }
        return null;
    }

    public function fetch(): array
    {
        $key = trim((string) env('THE_ODDS_API_KEY', ''));
        if ($key === '') throw new RuntimeException('THE_ODDS_API_KEY is not configured.');

        $region = trim((string) env('THE_ODDS_API_REGION', 'us')) ?: 'us';
        $url = 'https://api.the-odds-api.com/v4/sports/icehockey_nhl/odds';

        $response = Http::timeout(30)->retry(2, 1200)->get($url, [
            'apiKey'=>$key,
            'regions'=>$region,
            'markets'=>'h2h',
            'oddsFormat'=>'american',
            'dateFormat'=>'iso',
            'commenceTimeFrom'=>CarbonImmutable::now('UTC')->format('Y-m-d\TH:i:s\Z'),
        ]);
        $response->throw();
        $games = $response->json();
        if (!is_array($games)) throw new RuntimeException('The Odds API returned invalid JSON.');

        $rows = [];
        $sourceUpdated = null;
        foreach ($games as $game) {
            $homeName = trim((string)($game['home_team'] ?? ''));
            $awayName = trim((string)($game['away_team'] ?? ''));
            $home = $this->teamAbbreviation($homeName);
            $away = $this->teamAbbreviation($awayName);
            $commence = $game['commence_time'] ?? null;
            if (!$home || !$away || !$commence) continue;

            $startsAt = CarbonImmutable::parse($commence)->utc();
            // Never process live odds, including when the request straddles puck drop.
            if ($startsAt->lte(CarbonImmutable::now('UTC'))) continue;
            $gameDate = $startsAt->setTimezone(FantasyDay::TIMEZONE)->toDateString();
            // Key prices by NHL abbreviation instead of the provider's display
            // name. Some bookmakers use a different spelling/label for the same
            // team (for example St Louis vs St. Louis), which previously caused
            // one side of a game to be silently dropped.
            $prices = [$home=>[], $away=>[]];
            foreach (($game['bookmakers'] ?? []) as $bookmaker) {
                $bookUpdated = $bookmaker['last_update'] ?? null;
                if ($bookUpdated && (!$sourceUpdated || CarbonImmutable::parse($bookUpdated)->gt(CarbonImmutable::parse($sourceUpdated)))) {
                    $sourceUpdated = $bookUpdated;
                }
                foreach (($bookmaker['markets'] ?? []) as $market) {
                    if (($market['key'] ?? null) !== 'h2h') continue;
                    foreach (($market['outcomes'] ?? []) as $outcome) {
                        $name = trim((string)($outcome['name'] ?? ''));
                        $price = $outcome['price'] ?? null;
                        $outcomeTeam = $this->teamAbbreviation($name);
                        if ($outcomeTeam && isset($prices[$outcomeTeam]) && is_numeric($price) && (int)round($price) !== 0) {
                            $prices[$outcomeTeam][] = (int) round($price);
                        }
                    }
                }
            }

            foreach ([[$homeName,$home,$away,'HOME'],[$awayName,$away,$home,'AWAY']] as [$name,$team,$opponent,$homeAway]) {
                $teamPrices = $prices[$team] ?? [];
                if (!$teamPrices) continue;
                sort($teamPrices, SORT_NUMERIC);
                $count = count($teamPrices);
                $mid = intdiv($count, 2);
                $american = $count % 2 ? $teamPrices[$mid] : (int) round(($teamPrices[$mid-1] + $teamPrices[$mid]) / 2);
                $decimal = $american > 0 ? 1 + ($american / 100) : 1 + (100 / abs($american));
                $rows[] = [
                    'game_date'=>$gameDate,
                    'commence_at'=>$startsAt->format('Y-m-d H:i:s'),
                    'team'=>$team,
                    'opponent'=>$opponent,
                    'home_away'=>$homeAway,
                    'american_odds'=>$american,
                    'decimal_odds'=>round($decimal, 3),
                    'bookmaker_count'=>$count,
                ];
            }
        }

        return ['rows'=>$rows, 'source_updated_at'=>$sourceUpdated, 'url'=>$url];
    }

    public function publish(array $data, array $wanted): array
    {
        return DB::transaction(function () use ($data, $wanted) {
            $now = CarbonImmutable::now('UTC');
            $counts = array_fill_keys($wanted, 0);
            $sourceUpdated = $data['source_updated_at'] ? CarbonImmutable::parse($data['source_updated_at'])->utc() : null;
            foreach ($data['rows'] as $row) {
                if (!in_array($row['game_date'], $wanted, true)) continue;
                if (CarbonImmutable::parse($row['commence_at'], 'UTC')->lte($now)) continue;
                $key = ['game_date'=>$row['game_date'], 'team'=>$row['team']];
                $existing = DB::table('todays_odds')->where($key)->lockForUpdate()->first();
                if ($existing?->commence_at && CarbonImmutable::parse($existing->commence_at, 'UTC')->lte($now)) continue;
                DB::table('todays_odds')->updateOrInsert($key, $row + [
                    'source_updated_at'=>$sourceUpdated,
                    'checked_at'=>$now,
                    'created_at'=>$existing?->created_at ?? $now,
                    'updated_at'=>$now,
                ]);
                $counts[$row['game_date']]++;
            }
            // Keep the last pregame prices for started games, even when the
            // provider removes them. A new fantasy date uses its own records.
            return $counts;
        });
    }
}
