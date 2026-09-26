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
        $verified=[];
        foreach (TradeContracts::records() as $c) {
            $verified[$c['trade_id']][$c['season']][$c['source_side']][TradeContracts::playerKey($c['player_name'])]=$c['contract_years_at_trade'];
        }
        foreach (TradeContracts::statusRecords() as $c) {
            $verified[$c['trade_id']][$c['season']][$c['source_side']][TradeContracts::playerKey($c['player_name'])]=$c['contract_raw'];
        }
        $verified['TR0480']['2025-26']['to'][TradeContracts::playerKey("Ryan O'Reilly")]='FA';
        $verified['TR0481']['2025-26']['to'][TradeContracts::playerKey('Elias Pettersson')]=2;
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
            $source=$sourceTrades[$t['season'].'|'.($r['source_trade_id']?:$r['trade_id'])]??null;
            foreach (['from_items','to_items'] as $key) if (!empty($source[$key])) $t[$key]=$source[$key];
            $contracts=[];
            foreach ($items as $a) {
                if ($a['asset_type']==='player' && $a['contract_years_at_trade']!==null) {
                    $contracts[$a['source_side']==='to'?'to':'from'][TradeContracts::playerKey($a['asset_description']??'')]=(int)$a['contract_years_at_trade'];
                }
            }
            foreach (['from','to'] as $side) {
                $years=array_replace($contracts[$side]??[], $verified[$t['id']][$t['season']][$side]??[]);
                $date=(string)$t['date'];
                if ($t['season']==='2025-26' && str_contains($date, 'Nov 20, 2025') && $side==='to') $years[TradeContracts::playerKey('Sebastian Aho')]=2;
                if ($t['season']==='2024-25' && str_contains($date, 'Dec 17, 2024') && $side==='from') $years[TradeContracts::playerKey('Elias Pettersson')]=3;
                if ($t['season']==='2023-24' && str_contains($date, 'Nov 24, 2023') && $side==='to') $years[TradeContracts::playerKey('Jack Hughes')]='FA';
                if ($t['season']==='2022-23' && str_contains($date, 'Nov 22, 2022') && $side==='from') $years[TradeContracts::playerKey('Sebastian Aho')]='FA';
                if ($t['season']==='2022-23' && str_contains($date, 'Feb 22, 2023') && $side==='to') $years[TradeContracts::playerKey('Elias Pettersson')]='FA';
                if ($t['season']==='2021-22' && str_contains($date, 'Oct 21, 2021')) {
                    if ($side==='from') $years[TradeContracts::playerKey('Elias Pettersson')]=2;
                    if ($side==='to') $years[TradeContracts::playerKey('Sebastian Aho')]=2;
                }
                if ($t['season']==='2020-21' && str_contains($date, 'Jan 4, 2021') && $side==='to') $years[TradeContracts::playerKey('Jack Hughes')]=4;
                $oreillyKey=TradeContracts::playerKey("Ryan O'Reilly");
                if (str_contains($date, 'Nov 11, 2023') || str_contains($date, 'Jan 15, 2021')) $years[$oreillyKey]=1;
                elseif (!isset($years[$oreillyKey])) $years[$oreillyKey]='FA';
                foreach ($t[$side.'_items'] as &$text) {
                    $key=TradeContracts::playerKey($text);
                    if (isset($years[$key])) $text=TradeContracts::label($text, $years[$key]);
                }
                unset($text);
            }
            $out[]=$t;
        }
        usort($out,fn($a,$b)=>strcmp($b['datetime']??$b['season'],$a['datetime']??$a['season']));
        return $out;
    }
    public function draftSeason(string $season): array
    {
        $picks=parent::draftSeason($season);
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
    public function awardEvents(): array { return parent::awardEvents(); }

    public function playerHistory(string $query): array
    {
        $needle=mb_strtolower(trim($query));
        if ($needle==='') return [];
        $events=[];

        foreach ($this->draftSeason('all') as $p) {
            if (!str_contains(mb_strtolower((string)($p['player']??'')), $needle)) continue;
            $events[]=['season'=>$p['season'],'kind'=>'draft','data'=>$p,'sort'=>$p['season'].'-00'];
        }

        foreach ($this->trades() as $t) {
            $matched=false;
            foreach (['from_items','to_items'] as $field) {
                foreach (($t[$field]??[]) as $item) {
                    $name=preg_replace('/\s*\((?:FA|MINORS|TBD|\d+\s+Years?)\)\s*$/i','',(string)$item);
                    if (str_contains(mb_strtolower($name),$needle)) {$matched=true;break 2;}
                }
            }
            if ($matched) $events[]=['season'=>$t['season'],'kind'=>'trade','data'=>$t,'sort'=>$t['datetime']??$t['date']??$t['season']];
        }

        foreach ($this->awardEvents() as $a) {
            if (empty($a['player']) || !str_contains(mb_strtolower((string)$a['player']),$needle)) continue;
            $events[]=['season'=>$a['season'],'kind'=>'award','data'=>$a,'sort'=>$a['season'].'-99'];
        }

        usort($events,function($a,$b){
            $season=strcmp($a['season'],$b['season']);
            if ($season!==0) return $season;
            $order=['draft'=>0,'trade'=>1,'award'=>2];
            $kind=($order[$a['kind']]??1)<=>($order[$b['kind']]??1);
            if ($kind!==0) return $kind;
            return strcmp((string)$a['sort'],(string)$b['sort']);
        });
        return $events;
    }
}
