<?php
namespace App\Support;

/** Contracts observed in the player's own historical Fantrax season. */
final class TradeContracts
{
    public static function records(): array
    {
        return json_decode(file_get_contents(database_path('data/verified-trade-contracts.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function statusRecords(): array
    {
        return json_decode(file_get_contents(database_path('data/verified-trade-contract-statuses.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function playerName(string $text): string
    {
        return trim(preg_replace('/\s*\((?:\d+ Years?|MINORS?|FA|TBD)\)\s*$/i', '', $text));
    }

    /** Stable comparison key for names coming from Fantrax/imported trade text. */
    public static function playerKey(string $text): string
    {
        $name = self::playerName($text);
        $name = str_replace(["’", "‘", "`", "´"], "'", $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return mb_strtolower($name, 'UTF-8');
    }

    public static function label(string $text, int|string $years): string
    {
        if (is_string($years)) return self::playerName($text).' ('.$years.')';
        return self::playerName($text).' ('.$years.' '.($years === 1 ? 'Year' : 'Years').')';
    }

    /**
     * Fantrax duplicate-name lookup rule used when verifying missing contracts:
     * 1. Prefer a search result whose Sta value is NOT FA.
     * 2. If every matching result has Sta=FA, the verified contract is FA.
     *
     * This prevents an unrostered namesake from hiding the contract belonging
     * to the player who was actually on an ECFHL roster.
     */
    public static function preferredFantraxResult(array $results): ?array
    {
        foreach ($results as $result) {
            if (strcasecmp(trim((string)($result['sta'] ?? '')), 'FA') !== 0 && trim((string)($result['sta'] ?? '')) !== '') {
                return $result;
            }
        }

        foreach ($results as $result) {
            if (strcasecmp(trim((string)($result['sta'] ?? '')), 'FA') === 0) {
                $result['contract'] = 'FA';
                return $result;
            }
        }

        return null;
    }

    public static function leagueIds(): array
    {
        $ids=[];
        foreach (array_merge(self::records(), self::statusRecords()) as $row) {
            if (!empty($row['season']) && !empty($row['league_id'])) $ids[$row['season']]=$row['league_id'];
        }
        return $ids;
    }

    public static function fantraxSearchUrl(string $season, string $player): ?string
    {
        $leagueId=self::leagueIds()[$season]??null;
        if (!$leagueId) return null;
        return 'https://www.fantrax.com/fantasy/league/'.$leagueId.'/players;searchName='.rawurlencode($player).';miscDisplayType=1;statusOrTeamFilter=ALL;positionOrGroup=ALL;pageNumber=1';
    }
}
