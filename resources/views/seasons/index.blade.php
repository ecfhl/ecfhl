@extends('layouts.app')
@section('title','Seasons · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">League archive</div><h1>Seasons</h1><p>Playoff finishes and regular-season leaders from 2007–08 to the present.</p></div>
<div style="display:flex;justify-content:flex-end;margin:0 0 22px"><select id="seasonJump" class="form-control" style="width:260px;padding:10px 12px;border-radius:8px" onchange="if(this.value) window.location.href=this.value"><option value="">Go to season...</option>@foreach($seasons as $season)<option value="/seasons/{{ rawurlencode($season['season']) }}">{{ $season['season'] }}</option>@endforeach</select></div>
<section class="section"><div class="section-title"><h2>All-time leaders</h2></div>@include('partials.leaders',['leaderRows'=>$seasonLeaders,'cards'=>['top_seasons'=>'📈 Top Seasons','most_fpts'=>'🏒 Most Fpts','top_earners'=>'💵 Top Earners','season_trades'=>'🔄 Most Trades','season_awards'=>'🏅 Most Awards','worst_records'=>'📉 Worst Records']])</section>
<style>
.season-playoff-podium{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:end;gap:8px;min-height:160px;margin-top:8px}.season-playoff-podium .podium-entry{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;min-width:0}.season-playoff-podium .podium-team{font-size:13px;text-align:center;margin-bottom:7px;line-height:1.15;overflow-wrap:anywhere}.season-playoff-podium .podium-place{width:100%;padding:8px 5px;justify-content:center}.season-playoff-podium .podium-second{height:84px}.season-playoff-podium .podium-first{height:112px}.season-playoff-podium .podium-third{height:70px}.season-playoff-podium .podium-medal{margin-bottom:4px}.season-playoff-podium small{font-size:10px}
</style>
<div class="season-cards">
@foreach($seasons as $season)
<a class="season-card" href="/seasons/{{ rawurlencode($season['season']) }}">
<div class="season-card-head"><div><strong>{{ $season['season'] }}</strong><span>{{ $season['format'] ?? '' }}</span></div><span>View →</span></div>
<div class="season-card-columns">
<div><h3>Playoffs</h3><div class="season-playoff-podium">
<div class="podium-entry"><strong class="podium-team">{{ $season['runner_up'] ?: '—' }}</strong><div class="podium-place podium-second"><span class="podium-medal">🥈</span><small>2nd</small></div></div>
<div class="podium-entry"><strong class="podium-team">{{ $season['champion'] ?: '—' }}</strong><div class="podium-place podium-first"><span class="podium-medal">🏆</span><small>Champion</small></div></div>
<div class="podium-entry"><strong class="podium-team">{{ $season['third_place'] ?: '—' }}</strong><div class="podium-place podium-third"><span class="podium-medal">🥉</span><small>3rd</small></div></div>
</div></div>
<div><h3>Regular Season</h3>@forelse($season['regular_top3'] ?? [] as $i=>$row)@php $hasRecord=$row['w']!==null||$row['l']!==null||$row['t']!==null;$record=$hasRecord?(($row['w']??0).'-'.($row['l']??0).'-'.($row['t']??0)):(($row['fantasy_points_for']!==null)?number_format($row['fantasy_points_for'],0).' Fpts':'—');$rankIcon=['1️⃣','2️⃣','3️⃣'][$i]??($i+1);@endphp<div class="award-line"><span>{{ $rankIcon }} {{ $row['team'] }}</span><strong>{{ $record }}</strong></div>@empty<div class="subtle">No regular-season data</div>@endforelse</div>
</div></a>
@endforeach
</div></div>
@endsection