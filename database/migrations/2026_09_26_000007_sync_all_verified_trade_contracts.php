<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function playerName(string $text): string
    {
        return trim(preg_replace('/\s*\((?:\d+ Years?|MINORS?|FA|TBD)\)\s*$/i', '', $text));
    }

    private function key(string $text): string
    {
        $name = $this->playerName($text);
        $name = str_replace(["’", "‘", "`", "´"], "'", $name);
        $name = preg_replace('/\s+/u', ' ', trim($name));
        return mb_strtolower($name, 'UTF-8');
    }

    public function up(): void
    {
        $numeric = json_decode(file_get_contents(database_path('data/verified-trade-contracts.json')), true, 512, JSON_THROW_ON_ERROR);
        $statuses = json_decode(file_get_contents(database_path('data/verified-trade-contract-statuses.json')), true, 512, JSON_THROW_ON_ERROR);

        // Include the two 2025-26 records that were verified directly after the bulk research pass.
        $statuses[] = [
            'trade_id' => 'TR0480',
            'player_name' => "Ryan O'Reilly",
            'source_side' => 'to',
            'contract_raw' => 'FA',
        ];
        $numeric[] = [
            'trade_id' => 'TR0481',
            'player_name' => 'Elias Pettersson',
            'source_side' => 'to',
            'contract_years_at_trade' => 2,
        ];

        foreach ($numeric as $record) {
            $this->apply($record, (int) $record['contract_years_at_trade'], null);
        }
        foreach ($statuses as $record) {
            $this->apply($record, null, strtoupper(trim($record['contract_raw'])));
        }
    }

    private function apply(array $record, ?int $years, ?string $status): void
    {
        $assets = DB::table('trade_assets')
            ->where('trade_id', $record['trade_id'])
            ->where('asset_type', 'player')
            ->where('source_side', $record['source_side'])
            ->get();

        $wanted = $this->key($record['player_name']);
        foreach ($assets as $asset) {
            if ($this->key($asset->asset_description ?? '') !== $wanted) continue;

            if ($years !== null) {
                DB::table('trade_assets')
                    ->where('trade_asset_id', $asset->trade_asset_id)
                    ->update(['contract_years_at_trade' => $years]);
                continue;
            }

            if ($status !== null) {
                $base = $this->playerName($asset->asset_description ?? $record['player_name']);
                DB::table('trade_assets')
                    ->where('trade_asset_id', $asset->trade_asset_id)
                    ->update(['asset_description' => $base.' ('.$status.')']);
            }
        }
    }

    public function down(): void
    {
        // Historical verification data should not be discarded on rollback.
    }
};
