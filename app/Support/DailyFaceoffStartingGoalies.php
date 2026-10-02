<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DOMDocument;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DailyFaceoffStartingGoalies
{
    private const TEAMS = ['Anaheim Ducks','Boston Bruins','Buffalo Sabres','Calgary Flames','Carolina Hurricanes','Chicago Blackhawks','Colorado Avalanche','Columbus Blue Jackets','Dallas Stars','Detroit Red Wings','Edmonton Oilers','Florida Panthers','Los Angeles Kings','Minnesota Wild','Montreal Canadiens','Nashville Predators','New Jersey Devils','New York Islanders','New York Rangers','Ottawa Senators','Philadelphia Flyers','Pittsburgh Penguins','San Jose Sharks','Seattle Kraken','St. Louis Blues','Tampa Bay Lightning','Toronto Maple Leafs','Utah Mammoth','Vancouver Canucks','Vegas Golden Knights','Washington Capitals','Winnipeg Jets'];

    public function fetch(CarbonImmutable $date): array
    {
        $day = $date->format('Y-m-d');
        $url = 'https://www.dailyfaceoff.com/starting-goalies/'.$day;
        try {
            $response = Http::connectTimeout(10)->timeout(45)->retry(2, 1000)
                ->withHeaders(['Accept' => 'text/html', 'Cache-Control' => 'no-cache'])
                ->get($url)->throw();
            $rows = $this->parse($response->body(), $day);
        } catch (\Throwable $e) {
            throw new RuntimeException('Could not retrieve/parse Daily Faceoff starting goalies: '.$e->getMessage().'. Existing data preserved.', 0, $e);
        }

        return ['url' => $url, 'source' => 'next-data', 'rows' => $rows];
    }

    /** Decode the same Next.js pageProps.data records used by the matchup cards. */
    public function parse(string $html, string $day): array
    {
        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        try {
            if (! $document->loadHTML($html, LIBXML_NONET)) {
                throw new RuntimeException('Invalid page document');
            }
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $scripts = (new DOMXPath($document))->query('//script[@id="__NEXT_DATA__"]');
        if ($scripts->length !== 1) {
            throw new RuntimeException('Missing unique Next.js data payload');
        }
        $payload = json_decode($scripts->item(0)->textContent, true, 512, JSON_THROW_ON_ERROR);
        $props = $payload['props']['pageProps'] ?? null;
        if (($payload['page'] ?? null) !== '/starting-goalies/[[...date]]'
            || ! is_array($props) || ($props['date'] ?? null) !== $day
            || ! isset($props['data']) || ! is_array($props['data']) || ! array_is_list($props['data'])) {
            throw new RuntimeException('Unexpected starting-goalies payload or date');
        }

        // A validated, date-specific empty array means no published matchups.
        // Missing/invalid payloads must never be mistaken for an empty schedule.
        $rows = [];
        $seen = [];
        $fantasyToday = CarbonImmutable::now('America/Halifax')->subHours(4)->startOfDay()->toDateString();
        $isFutureDate = $day > $fantasyToday;
        foreach ($props['data'] as $game) {
            if (! is_array($game) || ($game['date'] ?? null) !== $day) {
                throw new RuntimeException('Invalid matchup date');
            }
            $awayTeam = $game['awayTeamName'] ?? null;
            $homeTeam = $game['homeTeamName'] ?? null;
            $normalizeTeam = static fn($name) => is_string($name)
                ? preg_replace('/[^a-z0-9]+/', '', strtolower($name))
                : '';
            $canonicalTeams = [];
            foreach (self::TEAMS as $canonicalTeam) {
                $canonicalTeams[$normalizeTeam($canonicalTeam)] = $canonicalTeam;
            }
            // DFO occasionally changes punctuation/spacing (for example St Louis vs St. Louis).
            // Canonicalize those harmless display-name differences before validation.
            $awayTeam = $canonicalTeams[$normalizeTeam($awayTeam)] ?? $awayTeam;
            $homeTeam = $canonicalTeams[$normalizeTeam($homeTeam)] ?? $homeTeam;
            $teamsValid = in_array($awayTeam, self::TEAMS, true)
                && in_array($homeTeam, self::TEAMS, true)
                && $awayTeam !== $homeTeam;

            if (! $teamsValid) {
                // One malformed/unpublished DFO matchup must not make the entire
                // day's collector fail. Skip only that matchup; valid games still refresh.
                continue;
            }

            foreach (['away', 'home'] as $side) {
                // Use the canonicalized team names validated above. DFO sometimes
                // changes display punctuation (for example "St Louis Blues"), and
                // passing the raw display value downstream makes the abbreviation
                // lookup fail even though the matchup itself was valid.
                $team = $side === 'away' ? $awayTeam : $homeTeam;
                $opponent = $side === 'away' ? $homeTeam : $awayTeam;
                $name = $game[$side.'GoalieName'] ?? null;

                // Future Daily Faceoff matchups are often published before one or both
                // goalies have been named. That is valid unavailable data, not a parse
                // failure. Store only the goalie sides that have actually been published.
                if (! is_string($name) || trim($name) === '') {
                    continue;
                }

                if (! array_key_exists($side.'NewsStrengthName', $game)) {
                    throw new RuntimeException('Missing goalie starting-status field');
                }
                // The page renders null NewsStrengthName as Unconfirmed (verified in browser).
                $status = $game[$side.'NewsStrengthName'] ?? 'Unconfirmed';
                if (! in_array($status, ['Confirmed', 'Likely', 'Unconfirmed'], true)) {
                    // DFO can briefly publish internal/unknown status labels.
                    // Treat them as Unconfirmed rather than failing the full refresh.
                    $status = 'Unconfirmed';
                }
                $key = $team.'|'.mb_strtolower(trim($name));
                if (isset($seen[$key])) {
                    throw new RuntimeException('Duplicate matchup goalie');
                }
                $seen[$key] = true;
                $updated = $game[$side.'NewsCreatedAt'] ?? null;
                $rows[] = [
                    'player_name' => trim($name),
                    'team_name' => $team,
                    'opponent_name' => $opponent,
                    'home_away' => strtoupper($side),
                    'starting_status' => $status,
                    'source_updated_at' => $updated === null ? null : CarbonImmutable::parse($updated)->utc(),
                ];
            }
        }
        return $rows;
    }
}
