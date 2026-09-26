<?php
namespace App\Support;

/** Only numeric contracts observed in the player's own historical Fantrax season. */
final class TradeContracts
{
    public static function records(): array
    {
        return json_decode(file_get_contents(database_path('data/verified-trade-contracts.json')), true, 512, JSON_THROW_ON_ERROR);
    }

    public static function playerName(string $text): string
    {
        return trim(preg_replace('/\s*\(\d+ Years?\)\s*$/i', '', $text));
    }

    public static function label(string $text, int $years): string
    {
        return self::playerName($text).' ('.$years.' '.($years === 1 ? 'Year' : 'Years').')';
    }
}
