<?php

namespace App\Support\LiveScoring;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class SnapshotRepository
{
    private array $loaded = [];
    public function get(string $date): ?array
    {
        if (array_key_exists($date, $this->loaded)) return $this->loaded[$date];
        return $this->loaded[$date] = \App\Support\PublicData::remember('snapshot:'.$date, 10, fn()=> $this->read($date));
    }

    private function read(string $date): ?array
    {
        // The raw upstream responses are large and only needed by collectors/debugging.
        $row = DB::table('live_scoring_snapshots')->where('league_id', FantraxClient::LEAGUE_ID)->where('fantasy_date', $date)->first(['payload','collected_at']);
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
                    'payload'=>json_encode($snapshot, JSON_THROW_ON_ERROR), 'source_payload'=>json_encode(['compact_version'=>1,'source_date'=>$snapshot['source_date'],'player_count'=>count($snapshot['players'])], JSON_THROW_ON_ERROR),
                    'player_count'=>count($snapshot['players']), 'collected_at'=>$collected->utc()->format('Y-m-d H:i:s'),
                    'created_at'=>$collected->utc()->format('Y-m-d H:i:s'), 'updated_at'=>$collected->utc()->format('Y-m-d H:i:s')]
            );
        });
        unset($this->loaded[$snapshot['fantasy_date']]);
        \App\Support\PublicData::forget('snapshot:'.$snapshot['fantasy_date']);
    }
}
