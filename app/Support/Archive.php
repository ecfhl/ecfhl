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
    private function rows(string $table): array { return $this->cache[$table] ??= DB::table($table)->get()->map(fn($r)=>(array)$r)->all(); }
    private function matches(?string $format): bool { return $this->mode()==='all' || ($this->mode()!=='none' && ($this->mode()==='h2h') === (stripos($format ?? '', 'head')!==false)); }
    private function selected(string $id): bool { foreach ($this->rows('seasons') as $s) if ($s['season_id']===$id) return $this->matches($s['format']); return false; }
    private function year(string $id): string { if (!isset($this->cache['years'])) $this->cache['years']=array_column($this->rows('seasons'),'season_name','season_id'); return $this->cache['years'][$id] ?? $id; }
    public function franchiseName(?string $id, string $fallback='—'): string
    {
        if (!$id) return $fallback;
        if (isset($this->cache['names'][$id])) return $this->cache['names'][$id];
        foreach ($this->rows('franchises') as $f) if ($f['franchise_id']===$id) return $this->cache['names'][$id]=$f['franchise_name'];
        return $this->cache['names'][$id]=$fallback;
    }
    private function historical(?string $id,string $seasonId,?string $fallback=null): string { foreach($this->rows('team_seasons') as $r) if($r['season_id']===$seasonId&&$r['franchise_id']===$id) return $r['original_name']; return $fallback?:$this->franchiseName($id); }
    private function resolve(?string $name): ?string
    {
        if(!isset($this->cache['aliases'])){$aliases=[];foreach($this->rows('team_seasons') as $r)$aliases[$r['original_name']]=$r['franchise_id'];foreach($this->rows('franchise_aliases') as $r)$aliases[$r['alias_name']]=$r['franchise_id'];foreach($this->rows('franchises') as $r){$aliases[$r['franchise_name']]=$r['franchise_id'];$aliases[$this->franchiseName($r['franchise_id'])]=$r['franchise_id'];}$this->cache['aliases']=$aliases;}return $this->cache['aliases'][$name??'']??null;
    }
    private function seasonId(string $year): ?string { foreach($this->rows('seasons') as $s)if($s['season_name']===$year)return $s['season_id'];return null; }
    public function seasons(): array
    {
        $out=[];foreach($this->rows('seasons') as $r){if(!$this->selected($r['season_id']))continue;$r['season']=$r['season_name'];foreach(['champion'=>'champion','runner_up'=>'second','third_place'=>'third'] as $key=>$type){$names=[];foreach($this->rows('awards') as $a)if($a['season_id']===$r['season_id']&&$a['award_type_id']===$type)$names[]=$this->historical($a['franchise_id'],$r['season_id'],$a['team_name_raw']);$r[$key]=implode(' / ',array_unique($names));}$out[]=$r;}usort($out,fn($a,$b)=>$b['sequence']<=>$a['sequence']);return $out;
    }
    public function teamSeasons(?string $season=null,?string $team=null): array { $fid=$team?$this->resolve($team):null;return array_values(array_map(function($r){$r['team']=$r['original_name'];return $r;},array_filter(parent::teamSeasons($season),fn($r)=>$this->selected($r['season_id'])&&(!$team||$r['franchise_id']===$fid)))); }
    public function teamLedger(string $mode='h2h',string $status='all'): array { if($this->mode()==='none')return [];return array_map(function($r){$r['team']=$this->franchiseName($r['id'],$r['team']);return $r;},parent::teamLedger($this->mode(),$status)); }
    public function team(string $slug): ?array { foreach($this->rows('franchises') as $f){$id=$f['franchise_id'];$name=$this->franchiseName($id,$f['franchise_name']);if(!in_array($slug,[$id,\Illuminate\Support\Str::slug($name),\Illuminate\Support\Str::slug($f['franchise_name'])],true))continue;foreach($this->teams() as $t)if($t['id']===$id)return $t;return ['id'=>$id,'team'=>$name,'active'=>(bool)$f['active_in_2025_26'],'seasons'=>0,'champion'=>0,'second'=>0,'third'=>0,'president'=>0,'fpts_leader'=>0,'w'=>0,'l'=>0,'t'=>0,'games'=>0,'win_pct'=>null];}return null; }

    public function overviewLeaders(): array
    {
        $ledger=$this->teamLedger($this->mode(),'all');
        $championships=[];$winning=[];
        foreach($ledger as $r){
            if(($r['champion']??0)>0)$championships[]=['team'=>$r['team'],'value'=>$r['champion'],'score'=>$r['champion']];
            if(($r['games']??0)>0)$winning[]=['team'=>$r['team'],'value'=>number_format(($r['win_pct']??0)*100,1).'%','detail'=>$r['w'].'-'.$r['l'].'-'.$r['t'],'score'=>$r['win_pct']??0,'games'=>$r['games']];
        }
        usort($championships,fn($a,$b)=>($b['score']<=>$a['score'])?:strnatcasecmp($a['team'],$b['team']));
        usort($winning,fn($a,$b)=>($b['score']<=>$a['score'])?:(($b['games']??0)<=>($a['games']??0))?:strnatcasecmp($a['team'],$b['team']));
        $tradeCounts=[];foreach($this->trades() as $t){if(!empty($t['vetoed']))continue;foreach(array_unique(array_filter([$t['from_id']??null,$t['to_id']??null])) as $id)$tradeCounts[$id]=($tradeCounts[$id]??0)+1;}arsort($tradeCounts);$tradeRows=[];foreach($tradeCounts as $id=>$n)$tradeRows[]=['team'=>$this->franchiseName($id),'value'=>$n,'score'=>$n];
        $first=[];foreach($this->draftSeason('all') as $p)if((int)($p['overall']??0)===1){$id=$p['franchise_id']??$this->resolve($p['team']??null);if($id)$first[$id]=($first[$id]??0)+1;}arsort($first);$firstRows=[];foreach($first as $id=>$n)$firstRows[]=['team'=>$this->franchiseName($id),'value'=>$n,'score'=>$n];
        $awards=[];foreach($this->rows('awards') as $a){if(!$this->selected($a['season_id'])||!in_array($a['award_type_id'],['president','leader','art_ross','norris','vezina','calder','champion','second','third'],true)||empty($a['franchise_id']))continue;$awards[$a['franchise_id']]=($awards[$a['franchise_id']]??0)+1;}arsort($awards);$awardRows=[];foreach($awards as $id=>$n)$awardRows[]=['team'=>$this->franchiseName($id),'value'=>$n,'score'=>$n];
        return ['championships'=>$championships,'winning_pct'=>$winning,'trades'=>$tradeRows,'first_picks'=>$firstRows,'awards'=>$awardRows];
    }
    public function seasonLeaders(): array
    {
        $rows=$this->teamSeasons();$top=$rows;$fpts=$rows;
        foreach($top as &$r){$g=($r['w']??0)+($r['l']??0)+($r['t']??0);$r['score']=$g?((2*($r['w']??0)+($r['t']??0))/(2*$g)):-1;$r['value']=$g?number_format($r['score']*100,1).'%':'—';$r['detail']=($r['w']??0).'-'.($r['l']??0).'-'.($r['t']??0);}unset($r);usort($top,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($b['season'],$a['season']));
        foreach($fpts as &$r){$r['score']=(float)($r['fantasy_points_for']??-1);$r['value']=$r['fantasy_points_for']!==null?number_format($r['fantasy_points_for'],0):'—';}unset($r);usort($fpts,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($b['season'],$a['season']));
        $tradeCounts=[];foreach($this->trades() as $t){if(!empty($t['vetoed']))continue;foreach(array_unique(array_filter([$t['from_id']??null,$t['to_id']??null])) as $id){$k=$t['season'].'|'.$id;$tradeCounts[$k]=($tradeCounts[$k]??0)+1;}}$tradeRows=[];foreach($tradeCounts as $k=>$n){[$season,$id]=explode('|',$k,2);$tradeRows[]=['team'=>$this->historical($id,$this->seasonId($season)??'',$this->franchiseName($id)),'season'=>$season,'value'=>$n,'score'=>$n];}usort($tradeRows,fn($a,$b)=>($b['score']<=>$a['score'])?:strcmp($b['season'],$a['season']));
        $earners=[];foreach($this->prizeTotals() as $r){/* all-time totals are not season rows; keep card valid when no season prize detail is exposed */}
        return ['top_seasons'=>$top,'most_fpts'=>$fpts,'top_earners'=>$earners,'season_trades'=>$tradeRows];
    }

    public function trades(): array
    {
        $out=[];$sourceTrades=[];foreach(parent::trades() as $t)if(!empty($t['id']))$sourceTrades[$t['season'].'|'.$t['id']]=$t;$assets=[];foreach($this->rows('trade_assets') as $a)$assets[$a['trade_id']][]=$a;$verified=[];
        foreach(TradeContracts::records() as $c)$verified[$c['trade_id']][$c['season']][$c['source_side']][TradeContracts::playerKey($c['player_name'])]=$c['contract_years_at_trade'];
        foreach(TradeContracts::statusRecords() as $c)$verified[$c['trade_id']][$c['season']][$c['source_side']][TradeContracts::playerKey($c['player_name'])]=$c['contract_raw'];
        $verified['TR0480']['2025-26']['to'][TradeContracts::playerKey("Ryan O'Reilly")]='FA';$verified['TR0481']['2025-26']['to'][TradeContracts::playerKey('Elias Pettersson')]=2;
        foreach($this->rows('trades') as $r){if(!$this->selected($r['season_id'])||$r['is_reversed'])continue;$t=['id'=>$r['trade_id'],'season'=>$this->year($r['season_id']),'date'=>$r['trade_date_raw']?:$r['trade_datetime'],'datetime'=>$r['trade_datetime'],'vetoed'=>(bool)$r['is_vetoed'],'from_id'=>$r['from_franchise_id'],'to_id'=>$r['to_franchise_id'],'from_items'=>[],'to_items'=>[]];foreach(['from','to'] as $side)$t[$side]=$this->historical($r[$side.'_franchise_id'],$r['season_id'],$r[$side.'_name_raw']);$t['filter_teams']=array_unique([$t['from'],$t['to'],$this->franchiseName($t['from_id']),$this->franchiseName($t['to_id'])]);$items=$assets[$r['trade_id']]??[];usort($items,fn($a,$b)=>($a['item_order']??0)<=>($b['item_order']??0));foreach($items as $a){$side=$a['source_side']==='to'?'to':'from';$text=$a['asset_description']??'';if($a['contract_years_at_trade']!==null&&!preg_match('/\(\d+ Years?\)/i',$text))$text.=' ('.$a['contract_years_at_trade'].' '.((int)$a['contract_years_at_trade']===1?'Year':'Years').')';$t[$side.'_items'][]=$text;}$source=$sourceTrades[$t['season'].'|'.($r['source_trade_id']?:$r['trade_id'])]??null;foreach(['from_items','to_items'] as $key)if(!empty($source[$key]))$t[$key]=$source[$key];$contracts=[];foreach($items as $a)if($a['asset_type']==='player'&&$a['contract_years_at_trade']!==null)$contracts[$a['source_side']==='to'?'to':'from'][TradeContracts::playerKey($a['asset_description']??'')]=(int)$a['contract_years_at_trade'];foreach(['from','to'] as $side){$years=array_replace($contracts[$side]??[],$verified[$t['id']][$t['season']][$side]??[]);$date=(string)$t['date'];if($t['season']==='2025-26'&&str_contains($date,'Nov 20, 2025')&&$side==='to')$years[TradeContracts::playerKey('Sebastian Aho')]=2;if($t['season']==='2024-25'&&str_contains($date,'Dec 17, 2024')&&$side==='from')$years[TradeContracts::playerKey('Elias Pettersson')]=3;if($t['season']==='2023-24'&&str_contains($date,'Nov 24, 2023')&&$side==='to')$years[TradeContracts::playerKey('Jack Hughes')]='FA';if($t['season']==='2022-23'&&str_contains($date,'Nov 22, 2022')&&$side==='from')$years[TradeContracts::playerKey('Sebastian Aho')]='FA';if($t['season']==='2022-23'&&str_contains($date,'Feb 22, 2023')&&$side==='to')$years[TradeContracts::playerKey('Elias Pettersson')]='FA';if($t['season']==='2021-22'&&str_contains($date,'Oct 21, 2021')){if($side==='from')$years[TradeContracts::playerKey('Elias Pettersson')]=2;if($side==='to')$years[TradeContracts::playerKey('Sebastian Aho')]=2;}if($t['season']==='2020-21'&&str_contains($date,'Jan 4, 2021')&&$side==='to')$years[TradeContracts::playerKey('Jack Hughes')]=4;$oreillyKey=TradeContracts::playerKey("Ryan O'Reilly");if(str_contains($date,'Nov 11, 2023')||str_contains($date,'Jan 15, 2021'))$years[$oreillyKey]=1;elseif(!isset($years[$oreillyKey]))$years[$oreillyKey]='FA';foreach($t[$side.'_items'] as &$text){$key=TradeContracts::playerKey($text);if(isset($years[$key]))$text=TradeContracts::label($text,$years[$key]);}unset($text);}
            $out[]=$t;}usort($out,fn($a,$b)=>strcmp($b['datetime']??$b['season'],$a['datetime']??$a['season']));return $out;
    }
    public function draftSeason(string $season): array
    {
        $picks=parent::draftSeason($season);if(!$picks){$players=array_column($this->rows('players'),'player_name','player_id');$drafts=array_column($this->rows('drafts'),'season_id','draft_id');foreach($this->rows('draft_picks') as $p){$year=$this->year($drafts[$p['draft_id']]);if($season!=='all'&&$year!==$season)continue;$picks[]=['season'=>$year,'team'=>$p['team_name_raw'],'player'=>$players[$p['player_id']]??'—','overall'=>$p['overall_pick'],'round'=>$p['round'],'pick'=>$p['pick_in_round']];}}$out=[];foreach($picks as $p){$id=$this->seasonId($p['season']);if(!$id||!$this->selected($id))continue;$p['franchise_id']=$this->resolve($p['team']??null);$p['team']=$this->historical($p['franchise_id'],$id,$p['team']??null);$out[]=$p;}usort($out,fn($a,$b)=>strcmp($b['season'],$a['season'])?:(($a['overall']??99999)<=>($b['overall']??99999)));return $out;
    }
    public function draftSeasons(): array { return array_values(array_unique(array_column($this->draftSeason('all'),'season'))); }
    public function awardEvents(): array
    {
        $types=[];foreach($this->rows('award_types') as $t)$types[$t['award_type_id']]=$t['award_name'];
        $players=[];foreach($this->rows('players') as $p)$players[$p['player_id']]=$p['player_name'];
        $out=[];foreach($this->rows('awards') as $a){if(empty($a['player_id'])||!isset($players[$a['player_id']])||!$this->selected($a['season_id']))continue;$out[]=['id'=>$a['award_type_id'],'award'=>$types[$a['award_type_id']]??$a['award_type_id'],'team'=>$this->historical($a['franchise_id'],$a['season_id'],$a['team_name_raw']),'player'=>$players[$a['player_id']],'points'=>$a['points'],'season'=>$this->year($a['season_id'])];}return $out;
    }
    public function prizeTotals(): array
    {
        $tot=[];foreach($this->rows('prize_awards') as $p){if(!$this->selected($p['season_id']))continue;$id=$p['franchise_id'];$tot[$id]=($tot[$id]??0)+(int)$p['amount_cents'];}
        $fees=[];foreach($this->rows('team_seasons') as $ts){if(!$this->selected($ts['season_id']))continue;foreach($this->rows('seasons') as $s)if($s['season_id']===$ts['season_id']&&$s['entry_fee_paid_per_franchise']!==null)$fees[$ts['franchise_id']]=($fees[$ts['franchise_id']]??0)+(float)$s['entry_fee_paid_per_franchise'];}
        $ids=array_unique(array_merge(array_keys($tot),array_keys($fees)));$out=[];foreach($ids as $id){$w=($tot[$id]??0)/100;$f=$fees[$id]??0;$out[]=['team'=>$this->franchiseName($id),'awards'=>$w,'fees'=>$f,'net'=>$w-$f];}usort($out,fn($a,$b)=>$b['net']<=>$a['net']);return $out;
    }
    public function playerHistory(string $q): array
    {
        $needle=mb_strtolower(trim($q));if($needle==='')return [];$out=[];
        foreach($this->draftSeason('all') as $p)if(str_contains(mb_strtolower($p['player']??''),$needle))$out[]=['type'=>'Draft','season'=>$p['season'],'player'=>$p['player'],'team'=>$p['team'],'detail'=>'Round '.$p['round'].' · Pick '.$p['pick'].' · #'.$p['overall'].' overall'];
        foreach($this->trades() as $t)foreach(['from','to'] as $side)foreach($t[$side.'_items'] as $item)if(str_contains(mb_strtolower($item),$needle))$out[]=['type'=>'Trade','season'=>$t['season'],'player'=>$item,'team'=>$t[$side],'detail'=>$t];
        foreach($this->awardEvents() as $a)if(str_contains(mb_strtolower($a['player']??''),$needle))$out[]=['type'=>'Award','season'=>$a['season'],'player'=>$a['player'],'team'=>$a['team'],'detail'=>$a['award'].($a['points']!==null?' · '.$a['points'].' pts':'')];
        usort($out,fn($a,$b)=>strcmp($b['season'],$a['season']));return $out;
    }
}