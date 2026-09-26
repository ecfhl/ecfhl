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
        return trim(preg_replace('/\s*\((?:\d+ Years?|MINORS|FA|TBD)\)\s*$/i', '', $text));
    }

    public static function label(string $text, int|string $years): string
    {
        if (is_string($years)) return self::playerName($text).' ('.$years.')';
        return self::playerName($text).' ('.$years.' '.($years === 1 ? 'Year' : 'Years').')';
    }
}
