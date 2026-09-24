@extends('layouts.app')
@section('title','ECFHL History')
@section('content')
<section class="hero">
  <div class="shell">
    <div class="eyebrow">Established in 2007</div>
    <h1>East Coast Fantasy Hockey League</h1>
    <p>A complete record of champions, franchise identities, seasons, trades, and draft history.</p>
    <div class="hero-actions">
      <a class="button primary" href="/seasons">Explore seasons</a>
      <a class="button secondary" href="/teams">View franchises</a>
    </div>
  </div>
</section>

<div class="stats-strip"><div class="shell stats-grid">
  <div class="stat"><strong>{{ count($seasons) }}</strong><span>Seasons</span></div>
  <div class="stat"><strong>{{ $championships }}</strong><span>Championships awarded</span></div>
  <div class="stat"><strong>{{ $statsSeasons }}</strong><span>Seasons with statistics</span></div>
  <div class="stat"><strong>{{ count($teams) }}</strong><span>Franchises</span></div>
  <div class="stat"><strong>{{ count($trades) }}</strong><span>Trades recorded</span></div>
</div></div>

<section class="section"><div class="shell">
  <article class="card latest-season-card">
    <div class="section-title">
      <div><div class="eyebrow">Latest season</div><h2>{{ $latest['season'] ?? '—' }}</h2></div>
      @if($latest)<span class="season-badge">{{ $latest['format'] ?? '' }}</span>@endif
    </div>
    @if($latest)
      <div class="podium">
        <div class="podium-place podium-second"><span class="podium-medal">🥈</span><small>2nd</small><strong>{{ $latest['runner_up'] ?? '—' }}</strong></div>
        <div class="podium-place podium-first"><span class="podium-medal">🏆</span><small>Champion</small><strong>{{ $latest['champion'] ?? '—' }}</strong></div>
        <div class="podium-place podium-third"><span class="podium-medal">🥉</span><small>3rd</small><strong>{{ $latest['third_place'] ?? '—' }}</strong></div>
      </div>
      <div class="latest-footer"><span class="subtle">Regular-season leader: <strong>{{ $latestLeader ?? '—' }}</strong></span><a href="/seasons/{{ rawurlencode($latest['season']) }}">View latest season →</a></div>
    @endif
  </article>
</div></section>

<section class="section" style="padding-top:0"><div class="shell">
  <div class="section-title"><h2>All-time leaders</h2><a href="/teams">Full franchise ledger →</a></div>
  <div class="grid-3 leader-cards">
    <article class="card leader-card"><div class="leader-card-title">🏆 Championships</div>
      @foreach($leaders['championships'] ?? [] as $i=>$row)
        <div class="leader-rank"><span>{{ $i+1 }}</span><strong>{{ $row['team'] }}</strong><b>{{ $row['value'] }}</b></div>
      @endforeach
    </article>
    <article class="card leader-card"><div class="leader-card-title">📈 Winning %</div>
      @foreach($leaders['winning_pct'] ?? [] as $i=>$row)
        <div class="leader-rank"><span>{{ $i+1 }}</span><strong>{{ $row['team'] }}<small>{{ $row['detail'] }}</small></strong><b>{{ $row['value'] }}</b></div>
      @endforeach
    </article>
    <article class="card leader-card"><div class="leader-card-title">🔄 Trades <small>H2H era</small></div>
      @foreach($leaders['trades'] ?? [] as $i=>$row)
        <div class="leader-rank"><span>{{ $i+1 }}</span><strong>{{ $row['team'] }}</strong><b>{{ $row['value'] }}</b></div>
      @endforeach
    </article>
  </div>
</div></section>

<section class="section" style="padding-top:0"><div class="shell">
  <div class="section-title"><h2>Recent seasons</h2><a href="/seasons">All seasons →</a></div>
  <div class="grid-3">@foreach(array_slice($seasons,0,6) as $season)<a class="feature-link" href="/seasons/{{ rawurlencode($season['season']) }}"><strong>{{ $season['season'] }}</strong><span>🏆 {{ $season['champion'] ?: 'No champion' }}</span><br><span>{{ $season['format'] ?? '' }}</span></a>@endforeach</div>
</div></section>

<section class="section" style="padding-top:0"><div class="shell feature-links">
  <a class="feature-link" href="/seasons"><strong>Seasons</strong><span>Standings, finishes and playoff results →</span></a>
  <a class="feature-link" href="/teams"><strong>Teams</strong><span>Franchise history, awards and records →</span></a>
  <a class="feature-link" href="/trades"><strong>Trades</strong><span>Search every recorded transaction →</span></a>
  <a class="feature-link" href="/draft"><strong>Draft</strong><span>Browse picks by year and franchise →</span></a>
</div></section>
@endsection
