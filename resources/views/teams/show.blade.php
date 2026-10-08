@extends('layouts.app')
@section('title', $team['team'].' · ECFHL')
@section('content')
<div class="shell">
@php
$teamIconKey=\Illuminate\Support\Str::slug((string)($team['team']??'team'));
@endphp
@push('styles')
<style>
.franchise-title{display:flex;align-items:center;gap:12px}.franchise-title .team-icon-uploader img{width:58px;height:58px;border-radius:16px}.franchise-title .team-icon-uploader{flex:0 0 58px}@media(max-width:700px){.franchise-title .team-icon-uploader img{width:50px;height:50px;border-radius:14px}.franchise-title .team-icon-uploader{flex-basis:50px}.franchise-title{gap:9px}}
</style>
@endpush
<div class="page-head"><div class="eyebrow">Franchise history</div><h1 class="franchise-title"><span class="team-message-logo-stack">@include('teams.partials.team-icon-uploader',['slug'=>$teamIconKey,'name'=>$team['team']])@include('communication.team-message',['messageTeamName'=>$team['team']])</span><span>{{ $team['team'] }}</span></h1><p>Complete recorded franchise history.</p></div>
@php
$archive=app(\App\Support\Archive::class);
$franchiseOptions=$archive->teamLedger($archive->mode(),'all');
$tradePartnerRows=array_slice($tradePartners,0,15);
$tradePartnerTotal=array_sum(array_column($tradePartners,'value'));
$selectedSeasonIds=array_values(array_filter(array_column($archive->seasons(),'season_id')));
$awardRows=\Illuminate\Support\Facades\DB::table('awards as a')
    ->join('award_types as at','at.award_type_id','=','a.award_type_id')
    ->where('a.franchise_id',$team['id'])
    ->whereIn('a.season_id',$selectedSeasonIds)
    ->select('at.award_type_id','at.award_name',\Illuminate\Support\Facades\DB::raw('COUNT(*) as wins'))
    ->groupBy('at.award_type_id','at.award_name')
    ->orderByDesc('wins')->orderBy('at.award_name')->get();
$firstRoundRows=\Illuminate\Support\Facades\DB::table('draft_picks as dp')
    ->join('drafts as d','d.draft_id','=','dp.draft_id')
    ->join('seasons as s','s.season_id','=','d.season_id')
    ->leftJoin('players as p','p.player_id','=','dp.player_id')
    ->leftJoin('team_seasons as ts',function($join){
        $join->on('ts.season_id','=','d.season_id')->on('ts.original_name','=','dp.team_name_raw');
    })
    ->where('dp.round',1)
    ->whereIn('d.season_id',$selectedSeasonIds)
    ->where(function($q)use($team){
        $q->where('dp.franchise_id',$team['id'])->orWhere('ts.franchise_id',$team['id']);
    })
    ->select('s.season_name','s.sequence','p.player_name','dp.overall_pick','dp.pick_in_round')
    ->distinct()
    ->orderByDesc('s.sequence')
    ->orderBy('dp.overall_pick')
    ->limit(15)
    ->get();
@endphp
<div style="display:flex;justify-content:flex-end;margin:0 0 22px"><select aria-label="Go to franchise" style="width:260px;padding:10px 12px;border-radius:8px" onchange="if(this.value) window.location.href=this.value"><option value="">Go to franchise...</option>@foreach($franchiseOptions as $option)<option value="/teams/{{ \Illuminate\Support\Str::slug($option['team']) }}" {{ $option['id']===$team['id']?'selected':'' }}>{{ $option['team'] }}</option>@endforeach</select></div>
@push('styles')
<style>
.franchise-summary{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));background:var(--panel);border:1px solid var(--line);border-radius:13px;box-shadow:var(--shadow);overflow:hidden;margin-bottom:26px}.franchise-summary .summary-card{min-width:0;padding:20px 10px;text-align:center;border-right:1px solid var(--line);display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px}.franchise-summary .summary-card:last-child{border-right:0}.franchise-summary .summary-value{font-size:25px;font-weight:800;line-height:1.15;white-space:nowrap}.franchise-summary .summary-label{font-size:11px;line-height:1.25;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);white-space:nowrap}.franchise-detail-cards{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;align-items:start}.leader-card-head{position:relative}.leader-card-head .leader-card-title{padding-right:42px}.leader-expand-button{position:absolute;top:50%;right:14px;transform:translateY(-50%);display:grid;place-items:center;width:30px;height:30px;padding:0;border:0;border-radius:7px;background:transparent;color:inherit;font:inherit;font-size:18px;line-height:1;cursor:pointer}.leader-expand-button:hover{background:rgba(127,127,127,.12)}.leader-expand-button:focus-visible{outline:2px solid var(--accent);outline-offset:2px}.draft-pick-card-row{display:grid;grid-template-columns:42px minmax(0,1fr) auto;align-items:center;gap:12px;padding:13px 18px;border-top:1px solid var(--line)}.draft-pick-card-row:first-child{border-top:0}.draft-pick-number{display:grid;place-items:center;width:32px;height:32px;border-radius:50%;background:var(--soft,var(--panel-2));font-weight:800}.draft-pick-player{min-width:0;font-weight:700}.draft-pick-player small{display:block;margin-top:2px;color:var(--muted);font-weight:500}.draft-pick-overall{font-weight:800;color:var(--muted);white-space:nowrap}@media(max-width:760px){.franchise-detail-cards{grid-template-columns:1fr}}@media(max-width:520px){.franchise-summary .summary-card{padding:14px 4px}.franchise-summary .summary-value{font-size:20px}.franchise-summary .summary-label{font-size:9px;letter-spacing:.25px}}
</style>
@endpush
<div class="franchise-summary"><div class="summary-card"><div class="summary-value">{{ $team['seasons'] }}</div><div class="summary-label">Seasons</div></div><div class="summary-card"><div class="summary-value">{{ $team['champion']??0 }}</div><div class="summary-label">Champions</div></div><div class="summary-card"><div class="summary-value">{{ ($team['w']??0).'-'.($team['l']??0).'-'.($team['t']??0) }}</div><div class="summary-label">Record</div></div><div class="summary-card"><div class="summary-value">{{ isset($team['win_pct'])&&$team['win_pct']!==null?number_format($team['win_pct']*100,1).'%':'—' }}</div><div class="summary-label">Win %</div></div></div>
<div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Season</th><th>Team</th><th class="num">Finish</th><th class="num">Record</th><th class="num">Fpts</th></tr></thead><tbody>@foreach($history as $r)@php $rank=isset($r['rank'])?(int)$r['rank']:null;$icon=match($r['playoff_finish']??null){'champion'=>'🏆','second'=>'🥈','third'=>'🥉',default=>''};$suffix=in_array(($rank??0)%100,[11,12,13])?'th':match(($rank??0)%10){1=>'st',2=>'nd',3=>'rd',default=>'th'};$finishText=trim($icon.' '.($rank>0?$rank.$suffix:'—'));@endphp<tr><td><a href="/seasons/{{ rawurlencode($r['season']) }}"><strong>{{ $r['season'] }}</strong></a></td><td>{{ $r['original_name'] }}</td><td class="num nowrap">{{ $finishText }}</td><td class="num">{{ isset($r['w'])&&$r['w']!==null?($r['w'].'-'.($r['l']??0).'-'.($r['t']??0)):'—' }}</td><td class="num">{{ $r['fantasy_points_for']!==null?number_format($r['fantasy_points_for'],0):'—' }}</td></tr>@endforeach</tbody></table></div></div>
<section class="section"><div class="franchise-detail-cards">
<article class="card leader-card"><div class="leader-card-head"><h3 class="leader-card-title">🔄 Trades by partner - {{ $tradePartnerTotal }}</h3>@if(count($tradePartnerRows)>3)<button type="button" class="leader-expand-button" data-expand-target="franchise-trade-partners" aria-expanded="false" title="Expand"><span class="expand-icon">⛶</span><span class="minimize-icon" hidden>✕</span></button>@endif</div><div id="franchise-trade-partners">@forelse($tradePartnerRows as $i=>$row)<div class="expand-row" @if($i>=3) hidden @endif>@include('partials.leader-row')</div>@empty<div class="empty">No recorded trades.</div>@endforelse</div><div style="padding:15px 18px;border-top:1px solid var(--line)"><a class="filter-button" href="/trades?franchise={{ urlencode($team['id']) }}">View all trades →</a></div></article>
<article class="card leader-card"><div class="leader-card-head"><h3 class="leader-card-title">1️⃣ 1st Round Picks</h3>@if($firstRoundRows->count()>3)<button type="button" class="leader-expand-button" data-expand-target="franchise-first-round" aria-expanded="false" title="Expand"><span class="expand-icon">⛶</span><span class="minimize-icon" hidden>✕</span></button>@endif</div><div id="franchise-first-round">@forelse($firstRoundRows as $i=>$pick)<div class="expand-row draft-pick-card-row" @if($i>=3) hidden @endif><span class="draft-pick-number">{{ $pick->overall_pick ?? $pick->pick_in_round }}</span><div class="draft-pick-player">@if($pick->player_name)@include('partials.player-link',['name'=>$pick->player_name])@else Unknown player @endif<small><a href="/seasons/{{ rawurlencode($pick->season_name) }}">{{ $pick->season_name }}</a></small></div><span class="draft-pick-overall">#{{ $pick->overall_pick ?? $pick->pick_in_round }}</span></div>@empty<div class="empty">No recorded first-round picks.</div>@endforelse</div><div style="padding:15px 18px;border-top:1px solid var(--line)"><a class="filter-button" href="/draft?season=all&franchise={{ urlencode($team['id']) }}">View all draft picks →</a></div></article>
<article class="card leader-card"><div class="leader-card-head"><h3 class="leader-card-title">🏆 Awards</h3>@if($awardRows->count()>3)<button type="button" class="leader-expand-button" data-expand-target="franchise-awards" aria-expanded="false" aria-label="Expand awards" title="Expand"><span class="expand-icon">⛶</span><span class="minimize-icon" hidden>✕</span></button>@endif</div>
<div id="franchise-awards">@forelse($awardRows as $i=>$award)<div class="expand-row" @if($i>=3) hidden @endif><div style="display:flex;align-items:center;justify-content:space-between;gap:16px;padding:15px 18px;border-top:1px solid var(--line)"><strong>{{ match($award->award_type_id){'second'=>'2nd place','third'=>'3rd place',default=>$award->award_name} }}</strong><b>{{ number_format($award->wins) }}</b></div></div>@empty<div class="empty">No recorded awards.</div>@endforelse</div>
</article>
</div></section></div>
@endsection
@push('scripts')<script>document.querySelectorAll('.leader-expand-button').forEach(btn=>btn.addEventListener('click',()=>{const box=document.getElementById(btn.dataset.expandTarget),expanded=btn.getAttribute('aria-expanded')==='true';box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=expanded?i>=3:false);btn.setAttribute('aria-expanded',expanded?'false':'true');btn.title=expanded?'Expand':'Minimize';btn.querySelector('.expand-icon').hidden=!expanded;btn.querySelector('.minimize-icon').hidden=expanded;}));</script>@endpush