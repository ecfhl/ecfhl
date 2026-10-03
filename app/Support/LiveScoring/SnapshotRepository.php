<?php

namespace App\Support\LiveScoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SnapshotRepository
{
    public function get(string $date): ?array
    {
        $row = DB::table('live_scoring_snapshots')->where('league_id', FantraxClient::LEAGUE_ID)->where('fantasy_date', $date)->first();
        if (!$row) return null;
        $data = json_decode($row->payload, true, 512, JSON_THROW_ON_ERROR);
        $data['collected_at'] = CarbonImmutable::parse($row->collected_at, 'UTC')->toIso8601String();
        return $data;
    }

    public function publish(array $snapshot, array $source, CarbonImmutable $collected): void
    {
        $snapshot['collected_at'] = $collected->utc()->toIso8601String();
        foreach ($snapshot['players'] as &$player) $player['collected_at'] = $snapshot['collected_at'];
        unset($player);
        // A single date is replaced atomically only after all collectors and validators succeed.
        DB::transaction(function () use ($snapshot,$source,$collected) {
            DB::table('live_scoring_snapshots')->updateOrInsert(
                ['league_id'=>FantraxClient::LEAGUE_ID, 'fantasy_date'=>$snapshot['fantasy_date']],
                ['source_date'=>$snapshot['source_date'], 'source'=>'fantrax',
                    'payload'=>json_encode($snapshot, JSON_THROW_ON_ERROR), 'source_payload'=>json_encode($source, JSON_THROW_ON_ERROR),
                    'player_count'=>count($snapshot['players']), 'collected_at'=>$collected->utc()->format('Y-m-d H:i:s'),
                    'created_at'=>$collected->utc()->format('Y-m-d H:i:s'), 'updated_at'=>$collected->utc()->format('Y-m-d H:i:s')]
            );
        });
    }
}
