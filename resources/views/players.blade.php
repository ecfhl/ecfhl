@extends('layouts.app')
@section('title','Players · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Player history</div><h1>Players</h1><p>Search trades, draft selections and awards, from oldest to newest.</p></div>
<form method="get" class="toolbar"><label class="sr-only" for="playerQuery">Player name</label><input id="playerQuery" name="q" value="{{ $q }}" class="control" placeholder="Enter a player name…" style="flex:1" required><button class="button primary" type="submit">Search</button></form>

<style>
.player-leader-grid{margin:22px 0 28px}
#player-results{scroll-margin-top:110px}
</style>
<div class="player-leader-grid">
@include('partials.leaders',[
    'leaderRows'=>[
        'overall1'=>$playerLeaders['overall1'] ?? [],
        'trades'=>$playerLeaders['trades'] ?? [],
        'round1'=>$playerLeaders['round1'] ?? [],
    ],
    'cards'=>[
        'overall1'=>'🥇 #1 Overall Picks',
        'trades'=>'🔄 Most Traded Players',
        'round1'=>'1️⃣ 1st Round Picks',
    ],
    'playerLink'=>true,
])
</div>

<div id="player-results">
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
<article class="card"><h3>📝 @include('partials.player-link',['name'=>$p['player']])</h3><div>{{ $p['team'] }}</div><p>Round {{ $p['round']??'—' }} · Pick {{ $p['pick']??'—' }} · #{{ $p['overall']??'—' }} overall</p><a href="/draft?season={{ urlencode($p['season']) }}">View season draft →</a></article>
@else
@php($a=$event['data'])
<article class="card"><span class="award-icon">{{ \App\Support\AwardIcon::for($a['id']) }}</span><h3>{{ $a['label'] }} · @include('partials.player-link',['name'=>$a['player']])</h3><div>{{ $a['team'] }}</div></article>
@endif
</section>
@empty<div class="empty">No matching player history in the selected season types.</div>@endforelse
</div>
@endif
</div>
</div>
@endsection