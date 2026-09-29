<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
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
        if (!preg_match('/Last updated:\s*([^<\r\n]+)/i', strip_tags($html), $match)) {
            throw new RuntimeException("Could not find Daily Faceoff last-updated value for {$team}");
        }
        $lastUpdate = CarbonImmutable::parse(trim($match[1]))->utc();

        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML($html);
        $xpath = new DOMXPath($dom);

        $players = [];
        foreach ([1 => '1st Powerplay Unit', 2 => '2nd Powerplay Unit'] as $unit => $heading) {
            $nodes = $xpath->query("//*[contains(normalize-space(.), '{$heading}')]");
            $anchorNames = [];
            foreach ($nodes as $node) {
                $container = $node;
                for ($i = 0; $i < 4 && $container; $i++, $container = $container->parentNode) {
                    $anchors = $xpath->query('.//a[contains(@href, "/players/")]', $container);
                    if ($anchors && $anchors->length >= 5) {
                        foreach ($anchors as $anchor) {
                            $name = trim(preg_replace('/\s+/', ' ', $anchor->textContent));
                            if ($name !== '' && !in_array($name, $anchorNames, true)) $anchorNames[] = $name;
                            if (count($anchorNames) === 5) break;
                        }
                        break;
                    }
                }
                if (count($anchorNames) === 5) break;
            }
            if (count($anchorNames) !== 5) {
                throw new RuntimeException("Expected 5 players on {$team} PP{$unit}, found ".count($anchorNames));
            }
            foreach ($anchorNames as $position => $name) {
                $players[] = ['player_name'=>$name, 'pp_unit'=>$unit, 'unit_position'=>$position + 1];
            }
        }

        return compact('team', 'url', 'lastUpdate', 'players');
    }
}
