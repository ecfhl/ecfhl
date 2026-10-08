@extends('layouts.app')
@section('title','Players · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Player history</div><h1>📖 Players</h1><p>Explore draft selections, trades and awards across all seasons, from oldest to newest.</p></div>
<form method="get" class="toolbar"><label class="sr-only" for="playerQuery">Player name</label><input id="playerQuery" name="q" value="{{ $q }}" class="control" placeholder="Enter a player name…" style="flex:1" required><button class="button primary" type="submit">Search</button></form>

@push('styles')
<style>
.player-leader-grid{margin:22px 0 28px}
#player-results{scroll-margin-top:110px}
.player-timeline{display:block;margin:18px 0 30px}
.timeline-season{display:flex;align-items:center;gap:12px;margin:26px 0 10px;font-size:18px;font-weight:800}
.timeline-season:first-child{margin-top:0}
.timeline-season::after{content:"";height:1px;flex:1;background:var(--line)}
.timeline-season a{text-decoration:none}
.timeline-season a:hover{text-decoration:underline}
.timeline-event{margin:0 0 8px}
.timeline-label{display:flex;align-items:center;gap:8px;margin:0 0 5px 12px;font-size:12px;color:var(--muted)}
.timeline-kind{font-weight:800;color:var(--text)}
.timeline-icon{display:inline-grid;place-items:center;width:22px;height:22px;border-radius:6px;background:var(--panel);border:1px solid var(--line);font-size:12px}
.timeline-event .card{margin:0;padding:10px 14px;border-left:3px solid var(--line);border-radius:8px;box-shadow:none}
.timeline-event[data-kind="draft"] .card{border-left-color:#f29e0d}
.timeline-event[data-kind="trade"] .card{border-left-color:#4a56b1}
.timeline-event[data-kind="award"] .card{border-left-color:#aa086d}
.timeline-event .card h3{margin:0;font-size:15px;line-height:1.4}
@media(max-width:600px){.timeline-season{margin-top:22px}.timeline-event .card{padding:9px 11px}.timeline-label{margin-left:6px}}
</style>
@endpush
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
<section class="timeline-event" data-kind="{{ $event['kind'] }}">
<div class="timeline-label"><span class="timeline-icon" aria-hidden="true">{{ match($event['kind']){'draft'=>'🎯','trade'=>'🔄',default=>'🏆'} }}</span><span class="timeline-kind">{{ match($event['kind']){'draft'=>'Drafted','trade'=>!empty($event['data']['vetoed'])?'Trade vetoed':'Traded',default=>'Award won'} }}</span><span>{{ match($event['kind']){'draft'=>'Season draft','trade'=>$event['data']['date'],default=>'Season awards'} }}</span></div>
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