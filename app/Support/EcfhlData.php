<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EcfhlData
{
    public function seasons(): array
    {
        $rows = DB::table('seasons')->orderByDesc('sequence')->get();
        $awardRows = DB::table('awards as a')
            ->leftJoin('franchises as f','f.franchise_id','=','a.franchise_id')
            ->leftJoin('players as p','p.player_id','=','a.player_id')
            ->select('a.*','f.franchise_name','p.player_name')->get()->groupBy('season_id');

        return $rows->map(function($s) use($awardRows) {
            $a = $awardRows->get($s->season_id, collect())->keyBy('award_type_id');
            $winner = fn(string $id) => optional($a->get($id))->franchise_name ?: optional($a->get($id))->team_name_raw;
            $player = fn(string $id) => optional($a->get($id))->player_name;
            $team = fn(string $id) => optional($a->get($id))->franchise_name ?: optional($a->get($id))->team_name_raw;
            $points = fn(string $id) => optional($a->get($id))->points;

            return [
                'id'=>$s->season_id,'season'=>$s->season_name,'sequence'=>$s->sequence,'league_id'=>$s->league_id,
                'format'=>$s->format,'cancelled'=>(bool)$s->cancelled,'status'=>$s->status,'note'=>$s->note,
                'champion'=>$winner('champion'),'runner_up'=>$winner('second'),'third_place'=>$winner('third'),
                'art_ross'=>$player('art_ross'),'art_ross_team'=>$team('art_ross'),'art_ross_points'=>$points('art_ross'),
                'norris'=>$player('norris'),'norris_team'=>$team('norris'),'norris_points'=>$points('norris'),
                'vezina'=>$player('vezina'),'vezina_team'=>$team('vezina'),'vezina_points'=>$points('vezina'),
                'calder'=>$player('calder'),'calder_team'=>$team('calder'),'calder_points'=>$points('calder'),
            ];
        })->all();
    }

    public function season(string $season): ?array
    {
        foreach ($this->seasons() as $row) {
            if ($row['season'] === $season || $row['id'] === $season) return $row;
        }
        return null;
    }

    public function teamSeasons(?string $season = null, ?string $team = null): array
    {
        $q = DB::table('team_seasons as ts')
            ->join('seasons as s','s.season_id','=','ts.season_id')
            ->join('franchises as f','f.franchise_id','=','ts.franchise_id')
            ->select('ts.*','s.season_name','s.sequence','s.format','f.franchise_name');

        if ($season) $q->where(function($x) use($season){ $x->where('s.season_name',$season)->orWhere('s.season_id',$season); });
        if ($team) $q->where('f.franchise_name',$team);

        $rows = $q->orderByDesc('s.sequence')->orderBy('ts.rank')->get();

        return $rows->map(fn($r)=>[
            'season'=>$r->season_name,'season_id'=>$r->season_id,'team'=>$r->franchise_name,'franchise_id'=>$r->franchise_id,
            'original_name'=>$r->original_name,'format'=>$r->format,'rank'=>$r->rank,
            'w'=>$r->w,'l'=>$r->l,'t'=>$r->t,'standings_points'=>$r->standings_points,
            'fantasy_points_for'=>$r->fantasy_points_for,'fantasy_points_against'=>$r->fantasy_points_against,
            'player_games'=>$r->player_games,'fantasy_points_per_player_game'=>$r->fantasy_points_per_player_game,
        ])->all();
    }

    public function teams(): array
    {
        $franchises = DB::table('franchises')->orderBy('franchise_name')->get();
        $seasonRows = DB::table('team_seasons as ts')->join('seasons as s','s.season_id','=','ts.season_id')
            ->select('ts.*','s.format')->get()->groupBy('franchise_id');
        $awards = DB::table('awards')->get()->groupBy('franchise_id');

        return $franchises->map(function($f) use($seasonRows,$awards) {
            $history = $seasonRows->get($f->franchise_id, collect());
            $a = $awards->get($f->franchise_id, collect());
            $h2h = $history->filter(fn($r)=>stripos($r->format ?? '', 'head') !== false);
            $w=(int)$h2h->sum('w'); $l=(int)$h2h->sum('l'); $t=(int)$h2h->sum('t'); $gp=$w+$l+$t;
            return [
                'id'=>$f->franchise_id,'team'=>$f->franchise_name,'active'=>(bool)$f->active_in_2025_26,
                'titles'=>$a->where('award_type_id','champion')->count(),
                'finals'=>$a->whereIn('award_type_id',['champion','second'])->count(),
                'h2h_first'=>$a->where('award_type_id','president')->count(),
                'points_leader'=>$a->where('award_type_id','leader')->count(),
                'w'=>$w,'l'=>$l,'t'=>$t,'win_pct'=>$gp ? (($w + 0.5*$t)/$gp) : null,
                'fantrax_seasons'=>$history->count(),
            ];
        })->all();
    }

    public function team(string $slug): ?array
    {
        foreach ($this->teams() as $team) {
            if (Str::slug($team['team']) === $slug || $team['id'] === $slug) return $team;
        }
        return null;
    }

    public function trades(): array
    {
        $trades = DB::table('trades as t')->join('seasons as s','s.season_id','=','t.season_id')
            ->select('t.*','s.season_name','s.sequence')->orderByDesc('s.sequence')->orderByDesc('t.trade_datetime')->get();
        $assets = DB::table('trade_assets')->orderBy('item_order')->get()->groupBy('trade_id');

        return $trades->map(function($t) use($assets) {
            $rows = $assets->get($t->trade_id, collect());
            $label = function($x) {
                $text = $x->asset_description ?: ucfirst($x->asset_type ?? 'asset');
                if ($x->contract_years_at_trade) $text .= ' ('.$x->contract_years_at_trade.' '.($x->contract_years_at_trade == 1 ? 'Year' : 'Years').')';
                return $text;
            };
            return [
                'id'=>$t->trade_id,'season'=>$t->season_name,'date'=>$t->trade_date_raw,'from'=>$t->from_name_raw,'to'=>$t->to_name_raw,
                'from_items'=>$rows->where('source_side','from')->map($label)->values()->all(),
                'to_items'=>$rows->where('source_side','to')->map($label)->values()->all(),
                'vetoed'=>(bool)$t->is_vetoed,'reversed'=>(bool)$t->is_reversed,'status'=>$t->status,
            ];
        })->all();
    }

    public function draftSeason(string $season): array
    {
        $draft = DB::table('drafts as d')->join('seasons as s','s.season_id','=','d.season_id')
            ->where(function($q) use($season){$q->where('s.season_name',$season)->orWhere('s.season_id',$season);})
            ->select('d.draft_id')->first();
        if (!$draft) return [];

        return DB::table('draft_picks as dp')->leftJoin('players as p','p.player_id','=','dp.player_id')
            ->leftJoin('franchises as f','f.franchise_id','=','dp.franchise_id')->where('dp.draft_id',$draft->draft_id)
            ->select('dp.*','p.player_name','f.franchise_name')->orderBy('dp.overall_pick')->get()->map(fn($r)=>[
                'overall'=>$r->overall_pick,'round'=>$r->round,'pick'=>$r->pick_in_round,'player'=>$r->player_name,
                'team'=>$r->franchise_name ?: $r->team_name_raw,'franchise_id'=>$r->franchise_id,
            ])->all();
    }

    public function draftSeasons(): array
    {
        return DB::table('drafts as d')->join('seasons as s','s.season_id','=','d.season_id')
            ->orderByDesc('s.sequence')->pluck('s.season_name')->all();
    }

    public function prizeTotals(): array
    {
        return DB::table('prize_awards as pa')->join('franchises as f','f.franchise_id','=','pa.franchise_id')
            ->select('f.franchise_id','f.franchise_name',DB::raw('SUM(pa.amount_cents) total_cents'),DB::raw('COUNT(*) awards'))
            ->groupBy('f.franchise_id','f.franchise_name')->orderByDesc('total_cents')->get()->map(fn($r)=>(array)$r)->all();
    }
}
