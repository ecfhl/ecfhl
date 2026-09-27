@extends('layouts.app')
@section('title','Players · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Player history</div><h1>Players</h1><p>Explore draft selections, trades and awards across all seasons, from oldest to newest.</p></div>
<form method="get" class="toolbar"><label class="sr-only" for="playerQuery">Player name</label><input id="playerQuery" name="q" value="{{ $q }}" class="control" placeholder="Enter a player name…" style="flex:1" required><button class="button primary" type="submit">Search</button></form>

<style>
.player-leader-grid{margin:22px 0 28px}
#player-results{scroll-margin-top:110px}
.player-timeline{position:relative;display:block;padding:0 0 0 34px;margin:20px 0}
.player-timeline::before{content:"";position:absolute;left:10px;top:14px;bottom:18px;width:2px;background:var(--line)}
.timeline-season{position:relative;margin:22px 0 14px;font-size:20px;font-weight:800}
.timeline-season:first-child{margin-top:0}
.timeline-season::before{content:"";position:absolute;left:-30px;top:7px;width:14px;height:14px;border-radius:50%;background:var(--accent,#1d5fa7);border:3px solid var(--panel);box-sizing:border-box}
.timeline-event{position:relative;margin:0 0 14px}
.timeline-marker{position:absolute;left:-37px;top:0;display:grid;place-items:center;width:28px;height:28px;border:1px solid var(--line);border-radius:50%;background:var(--panel);font-size:14px}
.timeline-label{display:flex;align-items:center;gap:10px;min-height:28px;margin-bottom:5px;font-size:13px;color:var(--muted)}
.timeline-kind{font-weight:800;color:var(--text)}
.timeline-event .card{margin:0;padding:12px 16px}
.timeline-event .card h3{margin:0;font-size:16px;line-height:1.35}
@media(max-width:600px){.player-timeline{padding-left:29px}.player-timeline::before{left:8px}.timeline-marker{left:-33px}.timeline-season::before{left:-27px}.timeline-event .card{padding:10px 12px}.timeline-label{flex-wrap:wrap;gap:4px 10px}}

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
<div class="player-timeline" aria-label="Player history timeline">
@php($timelineSeason=null)
@forelse($events as $event)
@if($timelineSeason!==$event['season'])
@php($timelineSeason=$event['season'])
<h2 class="timeline-season"><a href="/seasons/{{ rawurlencode($event['season']) }}">{{ $event['season'] }}</a></h2>
@endif
<section class="timeline-event">
<span class="timeline-marker" aria-hidden="true">{{ match($event['kind']){'draft'=>'🎯','trade'=>'🔄',default=>'🏆'} }}</span>
<div class="timeline-label"><span class="timeline-kind">{{ match($event['kind']){'draft'=>'Drafted','trade'=>!empty($event['data']['vetoed'])?'Trade vetoed':'Traded',default=>'Award won'} }}</span><span>{{ match($event['kind']){'draft'=>'Season draft','trade'=>$event['data']['date'],default=>'Season awards'} }}</span></div>
@if($event['kind']==='trade')
@include('partials.trade-card',['t'=>$event['data']])
@elseif($event['kind']==='draft')
@php($p=$event['data'])
@php($ordinal=function($n){$n=(int)$n;$mod100=$n%100;if($mod100>=11&&$mod100<=13)return $n.'th';return $n.match($n%10){1=>'st',2=>'nd',3=>'rd',default=>'th'};})
<article class="card"><h3>@include('partials.player-link',['name'=>$p['player']]) drafted in the {{ $ordinal($p['round']??0) }} round ({{ $ordinal($p['overall']??0) }} overall) by {{ $p['team'] }}</h3></article>
@else
@php($a=$event['data'])
<article class="card"><h3>@include('partials.player-link',['name'=>$a['player']]) wins the {{ $a['label'] }} ({{ $a['team'] }})</h3></article>
@endif
</section>
@empty<div class="empty">No matching player history across all seasons.</div>@endforelse
</div>
@endif
</div>
</div>
@endsection