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

    protected function displayTeamName(?string $name, ?string $franchiseId = null): string
    {
        if ($franchiseId === 'F009' || $name === 'Janick') return 'JDPower';
        return $name ?: '—';
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
        foreach ($this->seasons() as $row) {
            if (($row['season'] ?? '') === $season) return $row;
        }
        return null;
    }

    public function teamSeasons(?string $season = null, ?string $team = null): array
    {
        $q = DB::table('team_seasons as ts')
            ->join('seasons as s','s.season_id','=','ts.season_id')
            ->join('franchises as f','f.franchise_id','=','ts.franchise_id')
            ->select('ts.*','s.season_name','s.sequence','s.format','f.franchise_name');

        if ($season) $q->where('s.season_name', $season);
        if ($team) {
            if ($team === 'JDPower') $q->where('ts.franchise_id','F009');
            else $q->where('f.franchise_name',$team);
        }

        return $q->orderByDesc('s.sequence')->orderBy('ts.rank')->get()->map(function($r) {
            return [
                'season'=>$r->season_name,
                'season_id'=>$r->season_id,
                'team'=>$this->displayTeamName($r->franchise_name, $r->franchise_id),
                'franchise_id'=>$r->franchise_id,
                'original_name'=>$r->original_name,
                'format'=>$r->format,
                'rank'=>$r->rank,
                'w'=>$r->w,'l'=>$r->l,'t'=>$r->t,
                'standings_points'=>$r->standings_points,
                'fantasy_points_for'=>$r->fantasy_points_for,
                'fantasy_points_against'=>$r->fantasy_points_against,
                'player_games'=>$r->player_games,
                'fantasy_points_per_player_game'=>$r->fantasy_points_per_player_game,
            ];
        })->all();
    }

    public function teams(): array
    {
        return $this->teamLedger('all', 'all');
    }

    public function team(string $slug): ?array
    {
        foreach ($this->teamLedger('all','all') as $team) {
            if (Str::slug($team['team']) === $slug || $team['id'] === $slug) return $team;
        }
        return null;
    }

    public function teamLedger(string $mode = 'h2h', string $status = 'all'): array
    {
        $mode = in_array($mode,['h2h','total','all'],true) ? $mode : 'h2h';
        $status = in_array($status,['active','inactive','all'],true) ? $status : 'all';

        $franchises = DB::table('franchises')->orderBy('franchise_name')->get();
        $seasonRows = DB::table('team_seasons as ts')
            ->join('seasons as s','s.season_id','=','ts.season_id')
            ->select('ts.*','s.format')->get()->groupBy('franchise_id');

        $awardRows = DB::table('awards as a')
            ->join('seasons as s','s.season_id','=','a.season_id')
            ->select('a.*','s.format')->get()->groupBy('franchise_id');

        $matchesMode = function($format) use($mode) {
            if ($mode === 'all') return true;
            $isH2h = stripos((string)$format, 'head') !== false;
            return $mode === 'h2h' ? $isH2h : !$isH2h;
        };

        $rows = [];
        foreach ($franchises as $f) {
            $active = (bool)$f->active_in_2025_26;
            if ($status === 'active' && !$active) continue;
            if ($status === 'inactive' && $active) continue;

            $history = ($seasonRows->get($f->franchise_id, collect()))->filter(fn($r)=>$matchesMode($r->format));
            $awards = ($awardRows->get($f->franchise_id, collect()))->filter(fn($r)=>$matchesMode($r->format));

            if ($mode !== 'all' && $history->count() === 0) continue;

            $recordRows = $history->filter(fn($r)=>$r->w !== null || $r->l !== null || $r->t !== null);
            $w=(int)$recordRows->sum('w'); $l=(int)$recordRows->sum('l'); $t=(int)$recordRows->sum('t');
            $gp=$w+$l+$t;
            $pct=$gp ? (($w*2+$t)/($gp*2)) : null;

            $rows[] = [
                'id'=>$f->franchise_id,
                'team'=>$this->displayTeamName($f->franchise_name, $f->franchise_id),
                'active'=>$active,
                'seasons'=>$history->count(),
                'champion'=>$awards->where('award_type_id','champion')->count(),
                'second'=>$awards->where('award_type_id','second')->count(),
                'third'=>$awards->where('award_type_id','third')->count(),
                'president'=>$awards->where('award_type_id','president')->count(),
                'fpts_leader'=>$awards->where('award_type_id','leader')->count(),
                'w'=>$w,'l'=>$l,'t'=>$t,'games'=>$gp,'win_pct'=>$pct,
            ];
        }

        usort($rows, function($a,$b){
            foreach (['champion','second','third','president'] as $k) {
                if (($a[$k]??0) !== ($b[$k]??0)) return ($b[$k]??0) <=> ($a[$k]??0);
            }
            return strcasecmp($a['team'],$b['team']);
        });

        return $rows;
    }

    public function overviewLeaders(): array
    {
        $championships = DB::table('awards as a')
            ->join('franchises as f','f.franchise_id','=','a.franchise_id')
            ->where('a.award_type_id','champion')
            ->select('f.franchise_id','f.franchise_name',DB::raw('COUNT(*) as value'))
            ->groupBy('f.franchise_id','f.franchise_name')
            ->orderByDesc('value')->orderBy('f.franchise_name')->limit(3)->get()
            ->map(fn($r)=>['team'=>$this->displayTeamName($r->franchise_name,$r->franchise_id),'value'=>(int)$r->value])->all();

        $winning = array_values(array_filter($this->teamLedger('h2h','all'), fn($r)=>($r['games']??0)>0));
        usort($winning, function($a,$b){
            $cmp = ($b['win_pct'] <=> $a['win_pct']);
            if ($cmp !== 0) return $cmp;
            return ($b['games']??0) <=> ($a['games']??0);
        });
        $winning = array_map(fn($r)=>[
            'team'=>$r['team'],
            'value'=>number_format(($r['win_pct']??0)*100,1).'%',
            'detail'=>$r['w'].'-'.$r['l'].'-'.$r['t'],
        ], array_slice($winning,0,3));

        $h2hSeasonIds = DB::table('seasons')->where('format','like','%Head%')->pluck('season_id');
        $trades = DB::table('trades')->whereIn('season_id',$h2hSeasonIds)->get();
        $counts = [];
        foreach ($trades as $t) {
            foreach ([$t->from_franchise_id,$t->to_franchise_id] as $fid) {
                if (!$fid) continue;
                $counts[$fid] = ($counts[$fid] ?? 0) + 1;
            }
        }
        arsort($counts);
        $tradeLeaders = [];
        foreach (array_slice($counts,0,3,true) as $fid=>$count) {
            $name = DB::table('franchises')->where('franchise_id',$fid)->value('franchise_name');
            $tradeLeaders[] = ['team'=>$this->displayTeamName($name,$fid),'value'=>$count];
        }

        return [
            'championships'=>$championships,
            'winning_pct'=>$winning,
            'trades'=>$tradeLeaders,
        ];
    }

    public function seasonRegularTop3(string $season): array
    {
        return array_slice($this->teamSeasons($season),0,3);
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

        $seen=[];$out=[];
        foreach ($groups as $season=>$rows) {
            foreach ($rows as $row) {
                $key=$row['id']??md5($season.json_encode($row));
                if(isset($seen[$key])) continue;
                $seen[$key]=true;
                $row['season']=$season;
                $out[]=$row;
            }
        }
        usort($out,function($a,$b){
            $s=strcmp($b['season']??'',$a['season']??'');
            return $s!==0?$s:strcmp($b['date']??'',$a['date']??'');
        });
        return $out;
    }

    public function draftSeason(string $season): array
    {
        if ($season === 'all') {
            $all=[];
            foreach ($this->drafts() as $year=>$teams) {
                foreach ($teams as $team=>$picks) {
                    foreach ($picks as $pick) {
                        $pick['team']=$team;
                        $pick['season']=$year;
                        $all[]=$pick;
                    }
                }
            }
            usort($all,function($a,$b){
                $s=strcmp($b['season']??'',$a['season']??'');
                return $s!==0?$s:(($a['overall']??9999)<=>($b['overall']??9999));
            });
            return $all;
        }

        $teams=$this->drafts()[$season]??[];$rows=[];
        foreach($teams as $team=>$picks){
            foreach($picks as $pick){$pick['team']=$team;$pick['season']=$season;$rows[]=$pick;}
        }
        usort($rows,fn($a,$b)=>($a['overall']??9999)<=>($b['overall']??9999));
        return $rows;
    }

    public function draftSeasons(): array
    {
        $seasons=array_keys($this->drafts());
        rsort($seasons);
        return $seasons;
    }

    public function prizeTotals(): array
    {
        return DB::table('prize_awards as pa')
            ->join('franchises as f','f.franchise_id','=','pa.franchise_id')
            ->select('f.franchise_id','f.franchise_name',DB::raw('SUM(pa.amount_cents) as total_cents'),DB::raw('COUNT(*) as awards'))
            ->groupBy('f.franchise_id','f.franchise_name')->orderByDesc('total_cents')->get()
            ->map(fn($r)=>[
                'franchise_id'=>$r->franchise_id,
                'franchise_name'=>$this->displayTeamName($r->franchise_name,$r->franchise_id),
                'total_cents'=>(int)$r->total_cents,
                'awards'=>(int)$r->awards,
            ])->all();
    }

    public function awardsBySeason(): array
    {
        $rows = DB::table('awards as a')
            ->join('seasons as s','s.season_id','=','a.season_id')
            ->join('award_types as at','at.award_type_id','=','a.award_type_id')
            ->leftJoin('franchises as f','f.franchise_id','=','a.franchise_id')
            ->leftJoin('players as p','p.player_id','=','a.player_id')
            ->select('s.season_name','s.sequence','a.award_type_id','at.award_name','a.franchise_id','f.franchise_name','p.player_name','a.team_name_raw')
            ->orderByDesc('s.sequence')->get()->groupBy('season_name');

        $out=[];
        foreach($rows as $season=>$items){
            $groups=['playoffs'=>[],'regular'=>[],'individual'=>[]];
            foreach($items as $r){
                $team=$this->displayTeamName($r->franchise_name ?: $r->team_name_raw,$r->franchise_id);
                $label=$r->player_name ? $r->award_name.': '.$r->player_name.' ('.$team.')' : $r->award_name.': '.$team;
                if(in_array($r->award_type_id,['champion','second','third'],true)) $groups['playoffs'][]=$label;
                elseif(in_array($r->award_type_id,['president','leader'],true)) $groups['regular'][]=$label;
                else $groups['individual'][]=$label;
            }
            $out[]=['season'=>$season]+$groups;
        }
        return $out;
    }
}
