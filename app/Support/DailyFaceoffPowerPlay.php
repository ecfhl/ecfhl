<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DailyFaceoffPowerPlay
{
    public const TEAMS = [
        'ANA'=>'anaheim-ducks','BOS'=>'boston-bruins','BUF'=>'buffalo-sabres','CGY'=>'calgary-flames',
        'CAR'=>'carolina-hurricanes','CHI'=>'chicago-blackhawks','COL'=>'colorado-avalanche','CBJ'=>'columbus-blue-jackets',
        'DAL'=>'dallas-stars','DET'=>'detroit-red-wings','EDM'=>'edmonton-oilers','FLA'=>'florida-panthers',
        'LAK'=>'los-angeles-kings','MIN'=>'minnesota-wild','MTL'=>'montreal-canadiens','NSH'=>'nashville-predators',
        'NJD'=>'new-jersey-devils','NYI'=>'new-york-islanders','NYR'=>'new-york-rangers','OTT'=>'ottawa-senators',
        'PHI'=>'philadelphia-flyers','PIT'=>'pittsburgh-penguins','SJS'=>'san-jose-sharks','SEA'=>'seattle-kraken',
        'STL'=>'st-louis-blues','TBL'=>'tampa-bay-lightning','TOR'=>'toronto-maple-leafs','UTA'=>'utah-mammoth',
        'VAN'=>'vancouver-canucks','VGK'=>'vegas-golden-knights','WSH'=>'washington-capitals','WPG'=>'winnipeg-jets',
    ];

    public function fetch(string $team, string $slug): array
    {
        $url = "https://www.dailyfaceoff.com/teams/{$slug}/line-combinations";
        $response = Http::withHeaders([
            'User-Agent' => 'ECFHL/1.0 (+https://ecfhl.win)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->timeout(30)->retry(2, 1000)->get($url);
        $response->throw();
        $html = $response->body();

        $plain = html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5);
        if (!preg_match('/Last updated:\s*(\d{4}-\d{2}-\d{2}T[^\s<]+)/i', $plain, $match)) {
            throw new RuntimeException("Could not find Daily Faceoff last-updated value for {$team}");
        }
        $lastUpdate = CarbonImmutable::parse(trim($match[1]))->utc();

        $players = [];
        foreach ([1 => '1st Powerplay Unit', 2 => '2nd Powerplay Unit'] as $unit => $heading) {
            $start = stripos($html, $heading);
            if ($start === false) throw new RuntimeException("Could not find {$team} PP{$unit}");

            $endMarkers = $unit === 1 ? ['2nd Powerplay Unit'] : ['1st Penalty Kill Unit', 'Goalies', 'Injuries'];
            $end = strlen($html);
            foreach ($endMarkers as $marker) {
                $candidate = stripos($html, $marker, $start + strlen($heading));
                if ($candidate !== false) $end = min($end, $candidate);
            }
            $segment = substr($html, $start, $end - $start);

            preg_match_all('/<a\b[^>]*href=["\'][^"\']*\/players\/[^"\']+["\'][^>]*>(.*?)<\/a>/is', $segment, $matches);
            $names = [];
            foreach ($matches[1] ?? [] as $label) {
                $name = trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5)));
                if ($name !== '' && !in_array($name, $names, true)) $names[] = $name;
                if (count($names) === 5) break;
            }
            if (count($names) !== 5) {
                throw new RuntimeException("Expected 5 players on {$team} PP{$unit}, found ".count($names));
            }
            foreach ($names as $position => $name) {
                $players[] = ['player_name'=>$name, 'pp_unit'=>$unit, 'unit_position'=>$position + 1];
            }
        }

        return compact('team', 'url', 'lastUpdate', 'players');
    }
}
