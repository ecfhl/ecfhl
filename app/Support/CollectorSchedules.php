<?php
namespace App\Support;

use Carbon\CarbonImmutable;
use Cron\CronExpression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class CollectorSchedules
{
    public const REFRESH_ALL = ['projections','players','goalies','lines','odds','teams','scores','standings'];
    public const FREQUENCIES = ['1','2','5','10','15','30','60','120','360','720','daily','weekly','off'];

    public static function catalog(): array
    {
        return [
            'transactions'=>['command'=>'ecfhl:watch-transactions','name'=>'Fantrax Transactions','frequency'=>'5'],
            'projections'=>['command'=>'ecfhl:refresh-player-projections','name'=>'Projected FPts','frequency'=>'daily','time'=>'04:00'],
            'players'=>['command'=>'ecfhl:refresh-daily-players','name'=>'Fantrax Available Players','frequency'=>'60','on_transaction'=>true],
            'teams'=>['command'=>'ecfhl:refresh-fantasy-rosters','name'=>'Fantasy Team Rosters','frequency'=>'60','on_transaction'=>true],
            'goalies'=>['command'=>'ecfhl:refresh-starting-goalies','name'=>'Daily Faceoff Goalies','frequency'=>'5'],
            'lines'=>['command'=>'ecfhl:refresh-pp-lines','name'=>'Daily Faceoff Lines','frequency'=>'60'],
            'odds'=>['command'=>'ecfhl:refresh-odds','name'=>'NHL Odds','frequency'=>'120'],
            'scores'=>['command'=>'ecfhl:refresh-live-scoring','name'=>'Fantrax Live Scoring','frequency'=>'1','idle_minutes'=>60],
            'standings'=>['command'=>'ecfhl:refresh-current-standings','name'=>'Current Standings','frequency'=>'1','idle_minutes'=>60],
            'matchups'=>['command'=>'ecfhl:refresh-scoring-period-matchups','name'=>'Scoring Period Matchups','frequency'=>'weekly','time'=>'08:00','weekday'=>1],
            'birthdates'=>['command'=>'ecfhl:refresh-player-birthdates','name'=>'Player Birthdates','frequency'=>'daily','time'=>'03:50'],
            'available-goalies'=>['command'=>'ecfhl:refresh-available-goalies','name'=>'Available Goalie Projections','frequency'=>'60','minute'=>35],
            'maintenance'=>['command'=>'ecfhl:database-maintenance','name'=>'Database Maintenance','frequency'=>'daily','time'=>'04:30'],
        ];
    }

    public static function all(): array
    {
        $stored = Schema::hasTable('collector_schedules') ? DB::table('collector_schedules')->pluck('settings_json','job_key')->all() : [];
        $all=[];
        foreach(self::catalog() as $key=>$default) {
            $all[$key]=array_replace(['time'=>'04:00','weekday'=>1,'idle_minutes'=>null,'on_transaction'=>false],$default,json_decode($stored[$key]??'{}',true)?:[]);
        }
        return $all;
    }

    public static function expression(array $s): string
    {
        [$hour,$minute]=array_map('intval',explode(':',$s['time']));
        return match($s['frequency']) {
            'daily'=>"$minute $hour * * *",
            'weekly'=>"$minute $hour * * ".$s['weekday'],
            'off'=>'0 0 31 2 *',
            default=>(int)$s['frequency']<60 ? '*/'.(int)$s['frequency'].' * * * *' : ($s['minute']??0).' */'.intdiv((int)$s['frequency'],60).' * * *',
        };
    }

    public static function due(string $key, ?CarbonImmutable $now=null): bool
    {
        $s=self::all()[$key];
        if($s['frequency']==='off')return false;
        $now=($now??CarbonImmutable::now())->setTimezone('America/Halifax');
        if($s['idle_minutes']!==null && !app(\App\Support\LiveScoring\RefreshLiveScoring::class)->live()) {
            return ((int)$now->format('H')*60+(int)$now->format('i'))%(int)$s['idle_minutes']===0;
        }
        return (new CronExpression(self::expression($s)))->isDue($now);
    }

    public static function describe(array $s): string
    {
        if($s['frequency']==='off')return 'Automatic refresh off';
        $label=match($s['frequency']) {'daily'=>'Daily at '.$s['time'].' Atlantic','weekly'=>'Weekly on '.['Sun','Mon','Tue','Wed','Thu','Fri','Sat'][(int)$s['weekday']].' at '.$s['time'].' Atlantic',default=>'Every '.$s['frequency'].' minute'.($s['frequency']==='1'?'':'s')};
        if($s['idle_minutes']!==null)$label.=' during games / every '.$s['idle_minutes'].' minutes idle';
        if($s['on_transaction'])$label='After transactions + '.$label.' backup';
        return $label;
    }

    public static function next(array $s, ?CarbonImmutable $now=null): string
    {
        if($s['frequency']==='off')return 'Manual only';
        $now=($now??CarbonImmutable::now())->setTimezone('America/Halifax');
        if($s['idle_minutes']!==null && !app(\App\Support\LiveScoring\RefreshLiveScoring::class)->live()) {
            for($i=1;$i<=1440;$i++){
                $candidate=$now->addMinutes($i)->startOfMinute();
                if(((int)$candidate->format('H')*60+(int)$candidate->format('i'))%(int)$s['idle_minutes']===0)return $candidate->format('M j · g:i a T');
            }
        }
        return CarbonImmutable::instance((new CronExpression(self::expression($s)))->getNextRunDate($now))->format('M j · g:i a T');
    }
}
