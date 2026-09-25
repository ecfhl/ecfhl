@extends('layouts.app')
@section('title','Seasons · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">League archive</div><h1>Seasons</h1><p>Playoff finishes and regular-season leaders from 2007–08 to the present.</p></div>
  <section class="section"><div class="section-title"><h2>All-time leaders</h2></div>
  @include('partials.leaders',['leaderRows'=>$seasonLeaders,'cards'=>['top_seasons'=>'📈 Top Seasons','most_fpts'=>'🏒 Most Fpts','top_earners'=>'💵 Top Earners','season_trades'=>'🔄 Trades']])
  </section>
  <div class="season-cards">
  @foreach($seasons as $season)
    <a class="season-card" href="/seasons/{{ rawurlencode($season['season']) }}">
      <div class="season-card-head"><div><strong>{{ $season['season'] }}</strong><span>{{ $season['format'] ?? '' }}</span></div><span>View →</span></div>
      <div class="season-card-columns">
        <div>
          <h3>Playoffs</h3>
          <div class="award-line"><span>🏆 Champion</span><strong>{{ $season['champion'] ?: '—' }}</strong></div>
          <div class="award-line"><span>🥈 Second</span><strong>{{ $season['runner_up'] ?: '—' }}</strong></div>
          <div class="award-line"><span>🥉 Third</span><strong>{{ $season['third_place'] ?: '—' }}</strong></div>
        </div>
        <div>
          <h3>Regular Season</h3>
          @forelse($season['regular_top3'] ?? [] as $i=>$row)
            @php
              $hasRecord = $row['w'] !== null || $row['l'] !== null || $row['t'] !== null;
              $record = $hasRecord ? (($row['w']??0).'-'.($row['l']??0).'-'.($row['t']??0)) : (($row['fantasy_points_for']!==null) ? number_format($row['fantasy_points_for'],0).' Fpts' : '—');
              $rankIcon = ['1️⃣','2️⃣','3️⃣'][$i] ?? ($i+1);
            @endphp
            <div class="award-line"><span>{{ $rankIcon }} {{ $row['team'] }}</span><strong>{{ $record }}</strong></div>
          @empty
            <div class="subtle">No regular-season data</div>
          @endforelse
        </div>
      </div>
    </a>
  @endforeach
  </div>
</div>
@endsection
