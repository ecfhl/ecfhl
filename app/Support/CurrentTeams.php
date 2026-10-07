<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class CurrentTeams
{
    public static function scoreboardStanding(?array $standing): array
    {
        $rank = (int)($standing['rank'] ?? 0);
        $suffix = in_array($rank % 100, [11, 12, 13], true) ? 'th' : match ($rank % 10) { 1=>'st', 2=>'nd', 3=>'rd', default=>'th' };
        return [
            'rank_label'=>$rank > 0 ? $rank.$suffix : null,
            'record'=>$standing && isset($standing['w'], $standing['l'], $standing['t'])
                ? $standing['w'].'–'.$standing['l'].'–'.$standing['t'] : null,
        ];
    }

    public static function standings(): array
    {
        // Current league pages are independent of the historical season-type filter.
        return PublicData::remember('current-teams', 30, fn()=>DB::table('team_seasons')
            ->where('season_id', '2026-27')->orderByRaw(DB::connection()->getQueryGrammar()->wrap('rank').' IS NULL')->orderBy('rank')->orderBy('original_name')
            ->get()->map(fn($row)=>(array)$row + ['team'=>$row->original_name, 'slug'=>Str::slug($row->original_name)])->all());
    }

    public static function administration(): array
    {
        $identities = app(OwnerTeams::class)->all()->keyBy('slug');
        $claims = DB::table('owner_team_claims as c')->join('users as u', 'u.id', '=', 'c.user_id')
            ->get(['c.fantasy_team_id', 'c.team_name', 'c.user_id', 'u.name as account_name', 'u.email as account_email']);
        $byId = $claims->keyBy('fantasy_team_id');
        $bySlug = $claims->keyBy(fn($claim)=>Str::slug($claim->team_name));
        return array_map(function ($team) use ($identities, $byId, $bySlug) {
            $id = $identities->get($team['slug'])['id'] ?? null;
            $team['account'] = ($id ? $byId->get($id) : null) ?? $bySlug->get($team['slug']);
            $team['name'] = $team['team'];
            return (object)$team;
        }, self::standings());
    }
}
