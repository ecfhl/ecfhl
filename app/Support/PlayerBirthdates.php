<?php
namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

final class PlayerBirthdates
{
    public static function nameKey(string $name): string
    {
        $display=PlayerName::display($name);
        $parts=explode(',', $display, 2);
        $name=count($parts)===2 ? trim($parts[1]).' '.trim($parts[0]) : $display;
        return preg_replace('/[^a-z0-9]/', '', strtolower(Str::ascii($name)));
    }

    public function age(string $name, ?CarbonImmutable $today=null): ?int
    {
        $dates=DB::table('player_birthdates')->where('name_key', self::nameKey($name))->pluck('birth_date')->unique();
        // Do not guess when more than one NHL player shares the same name.
        if($dates->count()!==1) return null;
        $today=$today??app(FantasyDay::class)->today();
        return (int)CarbonImmutable::parse($dates->first(), FantasyDay::TIMEZONE)->diffInYears($today);
    }

    public function refresh(bool $force=false): int
    {
        if(!$force && DB::table('player_birthdates')->where('refreshed_at','>=',now()->subWeek())->count()>=500) {
            return DB::table('player_birthdates')->count();
        }
        $rows=[];
        CollectorStatus::total('birthdates', 4);
        foreach(array_chunk(array_keys(DailyFaceoffPowerPlay::TEAMS), 8) as $teams) {
            $responses=Http::pool(function(Pool $pool) use($teams) {
                foreach($teams as $team) $pool->as($team)->connectTimeout(5)->timeout(12)
                    ->get('https://api-web.nhle.com/v1/roster/'.$team.'/current');
            });
            foreach($responses as $response) {
                if(!$response instanceof \Illuminate\Http\Client\Response || !$response->successful()) continue;
                $data=$response->json();
                foreach(['forwards','defensemen','goalies'] as $group) {
                    foreach($data[$group]??[] as $player) {
                        $birth=$player['birthDate']??'';
                        $name=trim(($player['firstName']['default']??'').' '.($player['lastName']['default']??''));
                        if(empty($player['id']) || $name==='' || !preg_match('/^\d{4}-\d{2}-\d{2}$/D',$birth)) continue;
                        try { $date=CarbonImmutable::createFromFormat('!Y-m-d',$birth); } catch(\Throwable) { continue; }
                        if(!$date || $date->toDateString()!==$birth || $date->isFuture() || $date->year<1900) continue;
                        $rows[$player['id']]=['nhl_player_id'=>(int)$player['id'],'name_key'=>self::nameKey($name),'birth_date'=>$birth,'refreshed_at'=>now()];
                    }
                }
            }
            CollectorStatus::advance('birthdates');
        }
        if(!$rows) throw new \RuntimeException('NHL birth dates unavailable; previous player ages preserved.');
        DB::transaction(function() use($rows) {
            foreach(array_chunk(array_values($rows),100) as $batch) DB::table('player_birthdates')->upsert($batch,['nhl_player_id'],['name_key','birth_date','refreshed_at']);
        });
        return count($rows);
    }
}
