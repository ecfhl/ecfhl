<?php
namespace App\Support;

final class PlayerName
{
    public static function searchVariants(string $value): array
    {
        $display=self::display($value);
        $variants=[trim($value),$display];
        if(str_contains($display,',')){
            [$last,$first]=array_map('trim',explode(',',$display,2));
            $variants[]=$first.' '.$last;
        }
        return array_values(array_unique(array_filter($variants)));
    }

    public static function matches(?string $name,string $query): bool
    {
        foreach(self::searchVariants($query) as $needle){
            if(mb_stripos((string)$name,$needle)!==false)return true;
        }
        return false;
    }

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
