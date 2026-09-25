<?php
namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Request-scoped archive: one season selection for every statistic and event. */
class Archive extends EcfhlData
{
    private array $cache = [];
    public function mode(): string
    {
        $mode = request()->query('type', request()->cookie('ecfhl-season-type', 'h2h'));
        return in_array($mode, ['h2h','total','all','none'], true) ? $mode : 'h2h';
    }
    private function rows(string $table): array
    {
        return $this->cache[$table] ??= DB::table($table)->get()->map(fn($r)=>(array)$r)->all();
    }
    private function matches(?string $format): bool
    {
        return $this->mode()==='all' || ($this->mode()!=='none' && ($this->mode()==='h2h') === (stripos($format ?? '', 'head')!==false));
    }
    private function selected(string $id): bool
    {
        foreach ($this->rows('seasons') as $s) if ($s['season_id']===$id) return $this->matches($s['format']);
        return false;
    }
    private function year(string $id): string
    {
        if (!isset($this->cache['years'])) $this->cache['years']=array_column($this->rows('seasons'),'season_name','season_id');
        if (isset($this->cache['years'][$id])) return $this->cache['years'][$id];
        return $id;
    }
    public function franchiseName(?string $id, string $fallback='—'): string
    {
        if (!$id) return $fallback;
        if (isset($this->cache['names'][$id])) return $this->cache['names'][$id];
        $history = array_values(array_filter($this->rows('team_seasons'), fn($r)=>$r['franchise_id']===$id));
        usort($history, fn($a,$b)=>strcmp($this->year($b['season_id']),$this->year($a['season_id'])));
        if (!$history) foreach ($this->rows('franchises') as $f) if ($f['franchise_id']===$id) $fallback=$f['franchise_name'];
        return $this->cache['names'][$id]=$history[0]['original_name'] ?? $fallback;
    }
    private function historical(?string $id, string $seasonId, ?string $fallback=null): string
    {
        foreach ($this->rows('team_seasons') as $r) if ($r['season_id']===$seasonId && $r['franchise_id']===$id) return $r['original_name'];
        return $fallback ?: $this->franchiseName($id);
    }
    private function resolve(?string $name): ?string
    {
        if (!isset($this->cache['aliases'])) {
            $aliases=[];
            foreach ($this->rows('team_seasons') as $r) $aliases[$r['original_name']]=$r['franchise_id'];
            foreach ($this->rows('franchise_aliases') as $r) $aliases[$r['alias_name']]=$r['franchise_id'];
            foreach ($this->rows('franchises') as $r) {
                $aliases[$r['franchise_name']]=$r['franchise_id'];
                $aliases[$this->franchiseName($r['franchise_id'])]=$r['franchise_id'];
            }
            $this->cache['aliases']=$aliases;
        }
        return $this->cache['aliases'][$name??'']??null;
    }
    private function seasonId(string $year): ?string
    {
        foreach ($this->rows('seasons') as $s) if ($s['season_name']===$year) return $s['season_id'];
        return null;
    }
    public function seasons(): array
    {
        $out=[];
        foreach ($this->rows('seasons') as $r) {
            if (!$this->selected($r['season_id'])) continue;
            $r['season']=$r['season_name'];
            foreach (['champion'=>'champion','runner_up'=>'second','third_place'=>'third'] as $key=>$type) {
                $names=[];
                foreach ($this->rows('awards') as $a) if ($a['season_id']===$r['season_id'] && $a['award_type_id']===$type) $names[]=$this->historical($a['franchise_id'],$r['season_id'],$a['team_name_raw']);
                $r[$key]=implode(' / ',array_unique($names));
            }
            $out[]=$r;
        }
        usort($out,fn($a,$b)=>$b['sequence']<=>$a['sequence']);
        return $out;
    }
    public function teamSeasons(?string $season=null, ?string $team=null): array
    {
        $fid=$team ? $this->resolve($team) : null;
        return array_values(array_map(function($r){
            $r['team']=$r['original_name']; return $r;
        },array_filter(parent::teamSeasons($season),fn($r)=>$this->selected($r['season_id']) && (!$team || $r['franchise_id']===$fid))));
    }
    public function teamLedger(string $mode='h2h', string $status='all'): array
    {
        if ($this->mode()==='none') return [];
        return array_map(function($r){$r['team']=$this->franchiseName($r['id'],$r['team']); return $r;},parent::teamLedger($this->mode(),$status));
    }
    public function team(string $slug): ?array
    {
        foreach ($this->rows('franchises') as $f) {
            $id=$f['franchise_id'];$name=$this->franchiseName($id,$f['franchise_name']);
            if (!in_array($slug,[$id,\Illuminate\Support\Str::slug($name),\Illuminate\Support\Str::slug($f['franchise_name'])],true)) continue;
            foreach ($this->teams() as $t) if ($t['id']===$id) return $t;
            return ['id'=>$id,'team'=>$name,'active'=>(bool)$f['active_in_2025_26'],'seasons'=>0,'champion'=>0,'second'=>0,'third'=>0,'president'=>0,'fpts_leader'=>0,'w'=>0,'l'=>0,'t'=>0,'games'=>0,'win_pct'=>null];
        }
        return null;
    }
    public function trades(): array
    {
        $out=[];
        $sourceTrades=[];
        foreach (parent::trades() as $t) if (!empty($t['id'])) $sourceTrades[$t['season'].'|'.$t['id']]=$t;
        $assets=[];
        foreach ($this->rows('trade_assets') as $a) $assets[$a['trade_id']][]=$a;
        foreach ($this->rows('trades') as $r) {
            if (!$this->selected($r['season_id']) || $r['is_reversed']) continue;
            $t=['id'=>$r['trade_id'],'season'=>$this->year($r['season_id']),'date'=>$r['trade_date_raw'] ?: $r['trade_datetime'],'datetime'=>$r['trade_datetime'], 'vetoed'=>(bool)$r['is_vetoed'], 'from_id'=>$r['from_franchise_id'],'to_id'=>$r['to_franchise_id'], 'from_items'=>[], 'to_items'=>[]];
            foreach (['from','to'] as $side) $t[$side]=$this->historical($r[$side.'_franchise_id'],$r['season_id'],$r[$side.'_name_raw']);
            $t['filter_teams']=array_unique([$t['from'],$t['to'],$this->franchiseName($t['from_id']),$this->franchiseName($t['to_id'])]);
            $items=$assets[$r['trade_id']]??[];
            usort($items,fn($a,$b)=>($a['item_order']??0)<=>($b['item_order']??0));
            foreach ($items as $a) {
                $side=$a['source_side']==='to' ? 'to' : 'from';
                $text=$a['asset_description']??'';
                if ($a['contract_years_at_trade']!==null && !preg_match('/\(\d+ Years?\)/i',$text)) $text.=' ('.$a['contract_years_at_trade'].' '.((int)$a['contract_years_at_trade']===1?'Year':'Years').')';
                $t[$side.'_items'][]=$text;
            }
            // Preserve richer imported descriptions, including recorded contracts.
            $source=$sourceTrades[$t['season'].'|'.($r['source_trade_id']?:$r['trade_id'])]??null;
            foreach (['from_items','to_items'] as $key) if (!empty($source[$key])) $t[$key]=$source[$key];
            $out[]=$t;
        }
        usort($out,fn($a,$b)=>strcmp($b['datetime']??$b['season'],$a['datetime']??$a['season']));
        return $out;
    }
    public function draftSeason(string $season): array
    {
        $picks=parent::draftSeason($season);
        // Keep the complete source-cache draft archive; relational exports can be partial.
        if (!$picks) {
            $players=array_column($this->rows('players'),'player_name','player_id');
            $drafts=array_column($this->rows('drafts'),'season_id','draft_id');
            foreach ($this->rows('draft_picks') as $p) {
                $year=$this->year($drafts[$p['draft_id']]);
                if ($season!=='all' && $year!==$season) continue;
                $picks[]=['season'=>$year,'team'=>$p['team_name_raw'],'player'=>$players[$p['player_id']]??'—','overall'=>$p['overall_pick'],'round'=>$p['round'],'pick'=>$p['pick_in_round']];
            }
        }
        $out=[];
        foreach ($picks as $p) {
            $id=$this->seasonId($p['season']);
            if (!$id || !$this->selected($id)) continue;
            $p['franchise_id']=$this->resolve($p['team']??null);
            $p['team']=$this->historical($p['franchise_id'],$id,$p['team']??null);
            $out[]=$p;
        }
        usort($out,fn($a,$b)=>strcmp($b['season'],$a['season']) ?: (($a['overall']??99999)<=>($b['overall']??99999)));
        return $out;
    }
    public function draftSeasons(): array { return array_values(array_unique(array_column($this->draftSeason('all'),'season'))); }
    public function awardEvents(): array
    {
        $types=array_column($this->rows('award_types'),'award_name','award_type_id');
        $players=array_column($this->rows('players'),'player_name','player_id');
        $out=[];
        foreach ($this->rows('awards') as $a) {
            if (!$this->selected($a['season_id'])) continue;
            $out[]=['id'=>$a['award_type_id'],'label'=>$types[$a['award_type_id']]??$a['award_type_id'],'season'=>$this->year($a['season_id']),'team'=>$this->historical($a['franchise_id'],$a['season_id'],$a['team_name_raw']),'franchise_id'=>$a['franchise_id'],'player'=>$players[$a['player_id']]??null,'points'=>$a['points']];
        }
        foreach ($this->rows('playoff_winners') as $w) {
            if (!$this->selected($w['season_id']) || stripos($w['competition'],'loser')===false) continue;
            $out[]=['id'=>'top_pick','label'=>'Top Pick Winner','season'=>$this->year($w['season_id']),'team'=>$this->historical($w['franchise_id'],$w['season_id']),'franchise_id'=>$w['franchise_id'],'player'=>null,'points'=>null];
        }
        usort($out,fn($a,$b)=>strcmp($b['season'],$a['season']));
        return $out;
    }
    public function seasonAwards(string $season): array { return array_values(array_filter($this->awardEvents(),fn($a)=>$a['season']===$season && in_array($a['id'],['president','leader','art_ross','norris','vezina','calder']))); }
    public function prizeTotals(): array
    {
        $out=[];
        foreach ($this->rows('prize_awards') as $p) {
            if (!$this->selected($p['season_id'])) continue;
            $id=$p['franchise_id'];
            $out[$id]??=['franchise_id'=>$id,'franchise_name'=>$this->franchiseName($id),'total_cents'=>0,'awards'=>0];
            $out[$id]['total_cents']+=$p['amount_cents']; $out[$id]['awards']++;
        }
        usort($out,fn($a,$b)=>$b['total_cents']<=>$a['total_cents']); return $out;
    }
    private function ranked(array $counts): array
    {
        arsort($counts); $out=[];
        foreach ($counts as $id=>$value) $out[]=['team'=>$this->franchiseName($id,$id),'value'=>$value];
        return $out;
    }
    public function overviewLeaders(): array
    {
        $champ=[];$awards=[];$top=[];$trades=[];$first=[];
        foreach ($this->awardEvents() as $a) {
            $id=$a['franchise_id']; if (!$id) continue;
            if ($a['id']==='champion') $champ[$id]=($champ[$id]??0)+1;
            if ($a['id']==='top_pick') $top[$id]=($top[$id]??0)+1;
            else $awards[$id]=($awards[$id]??0)+1;
        }
        foreach ($this->trades() as $t) if (!$t['vetoed']) foreach (array_unique([$t['from_id'],$t['to_id']]) as $id) if($id) $trades[$id]=($trades[$id]??0)+1;
        foreach ($this->draftSeason('all') as $p) if ((int)($p['overall']??0)===1 && $p['franchise_id']) $first[$p['franchise_id']]=($first[$p['franchise_id']]??0)+1;
        $winning=array_values(array_filter($this->teams(),fn($r)=>$r['games']>0));
        usort($winning,fn($a,$b)=>($b['win_pct']<=>$a['win_pct']) ?: ($b['games']<=>$a['games']));
        $winning=array_map(fn($r)=>['team'=>$r['team'],'value'=>number_format($r['win_pct']*100,1).'%','detail'=>$r['w'].'-'.$r['l'].'-'.$r['t']],$winning);
        return ['championships'=>$this->ranked($champ),'winning_pct'=>$winning,'trades'=>$this->ranked($trades),'winnings'=>array_map(fn($r)=>['team'=>$r['franchise_name'],'value'=>'$'.number_format($r['total_cents']/100,2)],$this->prizeTotals()),'first_picks'=>$this->ranked($first),'awards'=>$this->ranked($awards),'top_pick'=>$this->ranked($top)];
    }
    public function seasonLeaders(): array
    {
        $records=[];$points=[];$earnings=[];$trades=[];
        foreach ($this->teamSeasons() as $r) {
            $games=($r['w']??0)+($r['l']??0)+($r['t']??0);
            $base=['team'=>$r['team'],'season'=>$r['season']];
            if ($games) $records[]=$base+['score'=>(2*$r['w']+$r['t'])/(2*$games),'value'=>number_format((2*$r['w']+$r['t'])/(2*$games)*100,1).'%','detail'=>$r['w'].'-'.$r['l'].'-'.$r['t']];
            if ($r['fantasy_points_for']!==null) $points[]=$base+['score'=>(float)$r['fantasy_points_for'],'value'=>number_format($r['fantasy_points_for'],2)];
        }
        foreach ($this->rows('prize_awards') as $p) {
            if (!$this->selected($p['season_id'])) continue;
            $key=$p['season_id'].'|'.$p['franchise_id'];
            $earnings[$key]??=['team'=>$this->historical($p['franchise_id'],$p['season_id']),'season'=>$this->year($p['season_id']),'score'=>0];
            $earnings[$key]['score']+=$p['amount_cents'];
        }
        foreach ($earnings as &$r) $r['value']='$'.number_format($r['score']/100,2); unset($r);
        foreach ($this->trades() as $t) if (!$t['vetoed']) foreach (['from','to'] as $side) {
            $key=$t['season'].'|'.($t[$side.'_id']??$t[$side]);
            $trades[$key]??=['team'=>$t[$side],'season'=>$t['season'],'score'=>0,'value'=>0];
            $trades[$key]['value']=++$trades[$key]['score'];
        }
        $out=['top_seasons'=>$records,'most_fpts'=>$points,'top_earners'=>array_values($earnings),'season_trades'=>array_values($trades)];
        foreach ($out as &$rows) usort($rows,fn($a,$b)=>($b['score']<=>$a['score']) ?: strcmp($b['season'],$a['season'])); unset($rows);
        return $out;
    }
    public function seasonTradeLeaders(string $year): array { return array_slice(array_values(array_filter($this->seasonLeaders()['season_trades'],fn($r)=>$r['season']===$year)),0,3); }
    public function teamTradeCount(string $id): int { return count(array_filter($this->trades(),fn($t)=>!$t['vetoed'] && in_array($id,[$t['from_id'],$t['to_id']],true))); }
    public function analysis(array $season): string
    {
        $rows=$this->teamSeasons($season['season']);$leader=$rows[0]??null;$text=[];
        if ($season['cancelled']) $text[]='The season was cancelled, with no championship awarded.';
        elseif ($season['champion']) $text[]=$season['champion'].' won the championship'.($season['runner_up']?', with '.$season['runner_up'].' finishing second.':'.');
        if ($leader) {
            $gp=($leader['w']??0)+($leader['l']??0)+($leader['t']??0);
            $text[]=$leader['team'].' led the regular-season standings'.($gp?' with a '.$leader['w'].'-'.$leader['l'].'-'.$leader['t'].' record ('.number_format((2*$leader['w']+$leader['t'])/(2*$gp)*100,1).'% winning percentage).':($leader['fantasy_points_for']!==null?' with '.number_format($leader['fantasy_points_for'],2).' fantasy points.':'.'));
            if ($gp && !$season['cancelled'] && $season['champion']) $text[]=$season['champion']===$leader['team']?'The regular-season leader also captured the playoff title.':'The playoff title went to a different team than the regular-season leader.';
        }
        $top=$this->seasonTradeLeaders($season['season']);
        if ($top) $text[]=$top[0]['team'].' was among the most active teams, completing '.$top[0]['value'].' trades.';
        return implode(' ',$text) ?: 'Detailed results are not available for this season.';
    }
    public function playerHistory(string $query): array
    {
        if ($query==='') return [];
        $needle=mb_strtolower($query);$events=[];
        foreach ($this->trades() as $t) if (str_contains(mb_strtolower(implode(' ',array_merge($t['from_items'],$t['to_items']))),$needle)) $events[]=['kind'=>'trade','sort'=>$t['datetime'] ?: $t['season'].'-07-01','season'=>$t['season'],'data'=>$t];
        foreach ($this->draftSeason('all') as $p) if (str_contains(mb_strtolower($p['player']??''),$needle)) $events[]=['kind'=>'draft','sort'=>substr($p['season'],0,4).'-09-01','season'=>$p['season'],'data'=>$p];
        foreach ($this->awardEvents() as $a) if ($a['player'] && str_contains(mb_strtolower($a['player']),$needle)) $events[]=['kind'=>'award','sort'=>((int)substr($a['season'],0,4)+1).'-07-01','season'=>$a['season'],'data'=>$a];
        usort($events,fn($a,$b)=>strcmp($a['sort'],$b['sort']));return $events;
    }
}
