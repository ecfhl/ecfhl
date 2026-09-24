<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EcfhlData
{
    protected function source(string $key): array
    {
        $payload = DB::table('source_cache')->where('source_key', $key)->value('payload');
        return $payload ? (json_decode($payload, true) ?: []) : [];
    }

    public function history(): array { return $this->source('history'); }
    public function drafts(): array { return $this->source('drafts'); }

    public function seasons(): array
    {
        $rows = $this->history()['seasons'] ?? [];
        usort($rows, fn($a,$b) => ($b['sequence'] ?? 0) <=> ($a['sequence'] ?? 0));
        return $rows;
    }

    public function season(string $season): ?array
    {
        foreach ($this->history()['seasons'] ?? [] as $row) {
            if (($row['season'] ?? '') === $season) return $row;
        }
        return null;
    }

    public function teamSeasons(?string $season = null, ?string $team = null): array
    {
        $rows = $this->history()['team_seasons'] ?? [];
        $rows = array_values(array_filter($rows, function($r) use($season,$team) {
            return (!$season || ($r['season'] ?? '') === $season)
                && (!$team || ($r['team'] ?? '') === $team);
        }));
        usort($rows, function($a,$b) {
            if (($a['season'] ?? '') === ($b['season'] ?? '')) return ($a['rank'] ?? 999) <=> ($b['rank'] ?? 999);
            return strcmp($b['season'] ?? '', $a['season'] ?? '');
        });
        return $rows;
    }

    public function teams(): array
    {
        $rows = $this->history()['team_summary'] ?? [];
        usort($rows, fn($a,$b) => strcasecmp($a['team'] ?? '', $b['team'] ?? ''));
        return $rows;
    }

    public function team(string $slug): ?array
    {
        foreach ($this->teams() as $team) {
            if (Str::slug($team['team'] ?? '') === $slug) return $team;
        }
        return null;
    }

    public function trades(): array
    {
        $h = $this->history();
        $groups = $h['trades_by_season'] ?? [];

        foreach (($h['trades_authenticated_by_season'] ?? []) as $season => $rows) {
            $groups[$season] = array_merge($groups[$season] ?? [], $rows);
        }

        if (!empty($h['trades_2025_26'])) $groups['2025-26'] = array_merge($groups['2025-26'] ?? [], $h['trades_2025_26']);

        foreach (($h['vetoed_trades_by_season'] ?? []) as $season => $rows) {
            $groups[$season] = array_merge($groups[$season] ?? [], $rows);
        }

        if (!empty($h['trades_2019_20_reversed'])) {
            $groups['2019-20'] = array_merge($groups['2019-20'] ?? [], $h['trades_2019_20_reversed']);
        }

        $seen = [];
        $out = [];
        foreach ($groups as $season => $rows) {
            foreach ($rows as $row) {
                $key = $row['id'] ?? md5($season.json_encode($row));
                if (isset($seen[$key])) continue;
                $seen[$key] = true;
                $row['season'] = $season;
                $out[] = $row;
            }
        }
        usort($out, function($a,$b){
            $s = strcmp($b['season'] ?? '', $a['season'] ?? '');
            if ($s !== 0) return $s;
            return strcmp($b['date'] ?? '', $a['date'] ?? '');
        });
        return $out;
    }

    public function draftSeason(string $season): array
    {
        $teams = $this->drafts()[$season] ?? [];
        $rows = [];
        foreach ($teams as $team => $picks) {
            foreach ($picks as $pick) {
                $pick['team'] = $team;
                $rows[] = $pick;
            }
        }
        usort($rows, fn($a,$b) => ($a['overall'] ?? 9999) <=> ($b['overall'] ?? 9999));
        return $rows;
    }

    public function draftSeasons(): array
    {
        $seasons = array_keys($this->drafts());
        rsort($seasons);
        return $seasons;
    }
}
