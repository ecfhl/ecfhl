@extends('layouts.app')
@section('title','Players · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Player history</div><h1>Players</h1><p>Search trades, draft selections and awards, from oldest to newest.</p></div>
<form method="get" class="toolbar"><label class="sr-only" for="playerQuery">Player name</label><input id="playerQuery" name="q" value="{{ $q }}" class="control" placeholder="Enter a player name…" style="flex:1" required><button class="button primary" type="submit">Search</button></form>

<style>
.player-leader-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin:22px 0 28px;align-items:start}
@media(max-width:760px){.player-leader-grid{grid-template-columns:1fr}}
</style>
<div class="player-leader-grid">
    <article class="card leader-card">
        <h3 class="leader-card-title">🥇 #1 Overall Picks</h3>
        @forelse(array_slice($playerLeaders['overall1'] ?? [],0,10) as $i => $row)
            @include('partials.leader-row')
        @empty<div class="empty">No recorded #1 overall picks.</div>@endforelse
    </article>
    <article class="card leader-card">
        <h3 class="leader-card-title">🔄 Most Traded Players</h3>
        @forelse(array_slice($playerLeaders['trades'] ?? [],0,10) as $i => $row)
            @include('partials.leader-row')
        @empty<div class="empty">No recorded player trades.</div>@endforelse
    </article>
    <article class="card leader-card">
        <h3 class="leader-card-title">1️⃣ 1st Round Picks</h3>
        @forelse(array_slice($playerLeaders['round1'] ?? [],0,10) as $i => $row)
            @include('partials.leader-row')
        @empty<div class="empty">No recorded first-round picks.</div>@endforelse
    </article>
</div>

@if($q==='')
<div class="empty">Enter a player name to explore their league history.</div>
@else
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
