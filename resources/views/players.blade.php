@extends('layouts.app')
@section('title','Players · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Player history</div><h1>Players</h1><p>Search trades, draft selections and awards, from oldest to newest.</p></div>
<form method="get" class="toolbar"><label class="sr-only" for="playerQuery">Player name</label><input id="playerQuery" name="q" value="{{ $q }}" class="control" placeholder="Enter a player name…" style="flex:1" required><button class="button primary" type="submit">Search</button></form>
@if($q==='')<div class="empty">Enter a player name to explore their league history.</div>
@else
<style>
.player-stat-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:18px;margin:22px 0 26px}
.player-stat-card{padding:20px;text-align:center}
.player-stat-card .player-name{font-size:14px;font-weight:800;margin-bottom:12px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.player-stat-card .stat-value{font-size:34px;line-height:1;font-weight:900;margin-bottom:7px}
.player-stat-card .stat-label{font-size:11px;text-transform:uppercase;letter-spacing:.7px;color:var(--muted);font-weight:700}
@media(max-width:700px){.player-stat-grid{grid-template-columns:1fr;gap:12px}.player-stat-card{padding:16px}.player-stat-card .stat-value{font-size:28px}}
</style>
<div class="player-stat-grid">
    <article class="card player-stat-card"><div class="player-name">{{ $q }}</div><div class="stat-value">{{ $stats['overall1'] }}</div><div class="stat-label">#1 Overall Picks</div></article>
    <article class="card player-stat-card"><div class="player-name">{{ $q }}</div><div class="stat-value">{{ $stats['trades'] }}</div><div class="stat-label">Times Traded</div></article>
    <article class="card player-stat-card"><div class="player-name">{{ $q }}</div><div class="stat-value">{{ $stats['round1'] }}</div><div class="stat-label">1st Round Picks</div></article>
</div>
<p class="subtle">{{ count($events) }} results for “{{ $q }}”. Drafts appear before season trades and awards after the season; exact draft and award dates are not recorded.</p>
<div class="player-timeline">
@forelse($events as $event)
<section class="timeline-event"><div class="timeline-label"><a href="/seasons/{{ rawurlencode($event['season']) }}">{{ $event['season'] }}</a> · {{ ucfirst($event['kind']) }}</div>
@if($event['kind']==='trade')
@include('partials.trade-card',['t'=>$event['data']])
@elseif($event['kind']==='draft')
@php($p=$event['data'])
<article class="card"><h3>📝 {{ $p['player'] }}</h3><div>{{ $p['team'] }}</div><p>Round {{ $p['round']??'—' }} · Pick {{ $p['pick']??'—' }} · #{{ $p['overall']??'—' }} overall</p><a href="/draft?season={{ urlencode($p['season']) }}">View season draft →</a></article>
@else
@php($a=$event['data'])
<article class="card"><span class="award-icon">{{ \App\Support\AwardIcon::for($a['id']) }}</span><h3>{{ $a['label'] }} · {{ $a['player'] }}</h3><div>{{ $a['team'] }}</div></article>
@endif
</section>
@empty<div class="empty">No matching player history in the selected season types.</div>@endforelse
</div>
@endif
</div>
@endsection
