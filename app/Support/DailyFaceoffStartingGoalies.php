<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DailyFaceoffStartingGoalies
{
    public function fetch(CarbonImmutable $date): array
    {
        $url = 'https://www.dailyfaceoff.com/starting-goalies/'.$date->format('Y-m-d');
        $html = Http::timeout(45)->retry(2, 1000)->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (compatible; ECFHL/1.0)',
            'Accept' => 'text/html,application/xhtml+xml',
        ])->get($url)->throw()->body();

        $text = html_entity_decode(strip_tags(preg_replace('/<(script|style)\b[^>]*>.*?<\/\1>/is', ' ', $html)));
        $text = preg_replace('/\s+/', ' ', $text);

        // DFO renders matchups as "Team at Team" followed by an ISO game time.
        // Team names can contain spaces, periods and apostrophes. Capture each game
        // block up to the next matchup instead of relying on the old timestamp-adjacent
        // goalie pattern.
        preg_match_all('/([A-Z][A-Za-z .\'’&-]+?)\s+at\s+([A-Z][A-Za-z .\'’&-]+?)\s+(20\d{2}-\d{2}-\d{2}T[^\s]+)\s+(.*?)(?=(?:[A-Z][A-Za-z .\'’&-]+?\s+at\s+[A-Z][A-Za-z .\'’&-]+?\s+20\d{2}-\d{2}-\d{2}T)|The Daily Fantasy Hockey Goalie Rankings|Daily Fantasy Toolkit|$)/u', $text, $games, PREG_SET_ORDER);

        $rows = [];
        foreach ($games as $game) {
            $away = trim($game[1]);
            $home = trim($game[2]);
            $block = $game[4];

            // A goalie status may optionally be followed by DFO's ISO update time.
            // Stop the name at the status keyword so profile text cannot be consumed.
            preg_match_all('/([A-Z][A-Za-zÀ-ÖØ-öø-ÿ .\'’\-]{1,60}?)\s+(Confirmed|Unconfirmed|Probable)(?:\s+(20\d{2}-\d{2}-\d{2}T[^\s]+))?/u', $block, $goalies, PREG_SET_ORDER);
            if (count($goalies) < 2) continue;

            foreach (array_slice($goalies, 0, 2) as $i => $goalie) {
                $rows[] = [
                    'player_name' => trim($goalie[1]),
                    'starting_status' => trim($goalie[2]),
                    'source_updated_at' => !empty($goalie[3]) ? CarbonImmutable::parse($goalie[3]) : null,
                    'team_name' => $i === 0 ? $away : $home,
                    'opponent_name' => $i === 0 ? $home : $away,
                    'home_away' => $i === 0 ? 'AWAY' : 'HOME',
                ];
            }
        }

        if (!$rows && !preg_match('/0 of 0 confirmed/i', $text)) {
            throw new RuntimeException('Could not parse Daily Faceoff starting goalies.');
        }

        return ['url' => $url, 'rows' => $rows];
    }
}
