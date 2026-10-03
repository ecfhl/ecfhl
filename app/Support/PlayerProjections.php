<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// A request-scoped map: raw Fantrax scoring snapshots stay intact.
final class PlayerProjections
{
    private ?array $byId = null;
    private array $byName = [];

    private function load(): void
    {
        if ($this->byId !== null) return;
        $this->byId = [];
        if (!Schema::hasTable('player_projections')) return;
        $rows = DB::table('player_projections as p')->join('player_projection_baselines as b', 'b.player_id', '=', 'p.player_id')
            ->select('p.*', 'b.player_name', 'b.nhl_team', 'b.fantrax_fpts_per_game')->get();
        foreach ($rows as $row) {
            $this->byId[(string)$row->player_id] = $row;
            $key = self::name($row->player_name);
            $this->byName[$key][] = $row;
        }
    }

    public function find(object $player): ?object
    {
        $this->load();
        $id = (string)($player->player_id ?? '');
        if ($id !== '') return $this->byId[$id] ?? null;
        $matches = $this->byName[self::name($player->player_name ?? $player->name ?? '')] ?? [];
        if (count($matches) === 1) return $matches[0];
        $team = self::team($player->nhl_team ?? $player->team ?? '');
        $matches = array_values(array_filter($matches, fn($row)=>self::team($row->nhl_team) === $team));
        return count($matches) === 1 ? $matches[0] : null;
    }

    public function decorate($players)
    {
        return $players->map(function ($player) {
            $row = $this->find($player);
            // Once initialized, players outside the top 1,000 have no custom estimate.
            if ($row || $this->byId) {
                $player->projected_fpts_per_game = $row ? (float)$row->projected_fpts_per_game : null;
                $player->custom_projection = $row !== null;
                $player->projection_as_of_date = $row?->as_of_date;
            }
            return $player;
        });
    }

    public function rate(object $player, ?float $fallback = null): ?float
    {
        $row = $this->find($player);
        return $row ? (float)$row->projected_fpts_per_game : ($this->byId ? null : $fallback);
    }

    public static function name(string $value): string
    {
        if (str_contains($value, ',')) { [$last, $first] = array_map('trim', explode(',', $value, 2)); $value = $first.' '.$last; }
        return preg_replace('/[^\pL\pN]+/u', '', mb_strtolower(trim($value))) ?? '';
    }

    private static function team(string $value): string
    {
        $team = strtoupper(trim($value));
        return match ($team) { 'LA'=>'LAK', 'NJ'=>'NJD', 'SJ'=>'SJS', 'TB'=>'TBL', default=>$team };
    }
}
