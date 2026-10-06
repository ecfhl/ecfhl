<?php
namespace App\Support;

final class PlayerName
{
    public static function display(?string $value): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', (string)$value) ?? (string)$value);
        if ($name === '' || $name === '—') return $name;
        if (str_contains($name, ',')) {
            [$last, $first] = array_map('trim', explode(',', $name, 2));
            return $first !== '' ? $last.', '.$first : $last;
        }
        $parts = explode(' ', $name, 2);
        return count($parts) === 2 ? $parts[1].', '.$parts[0] : $name;
    }
}
