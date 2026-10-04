<?php
namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/** Cache only public database records, never HTML, sessions or owner preferences. */
final class PublicData
{
    public static function remember(string $key, int $seconds, callable $load): mixed
    {
        if (!config('performance.public_data_cache')) return $load();
        return Cache::store('file')->remember('public-data-v1:'.$key, $seconds, $load);
    }

    public static function forget(string $key): void
    {
        Cache::store('file')->forget('public-data-v1:'.$key);
    }

    public static function teamMenu(): array
    {
        return self::remember('team-menu', 60, fn()=>DB::table('team_seasons as ts')
            ->join('seasons as s','s.season_id','=','ts.season_id')
            ->where('s.season_name','2026-27')->orderBy('ts.original_name')
            ->pluck('ts.original_name')->all());
    }
}
