<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
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

            $gameDate = CarbonImmutable::parse($commence)->setTimezone('America/Halifax')->toDateString();
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
                        if ($outcomeTeam && isset($prices[$outcomeTeam]) && is_numeric($price)) {
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
}
