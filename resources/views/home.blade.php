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
<section class="section"><div class="shell grid-2">
  <article class="card"><div class="eyebrow">Latest season</div>
  @if($latest)
    <div class="section-title"><h2>{{ $latest['season'] }}</h2><span class="season-badge">{{ $latest['format'] ?? '' }}</span></div>
    <div class="latest-grid">
      <div class="placing"><small>🏆 Champion</small><strong>{{ $latest['champion'] ?? '—' }}</strong></div>
      <div class="placing"><small>🥈 2nd</small><strong>{{ $latest['runner_up'] ?? '—' }}</strong></div>
      <div class="placing"><small>🥉 3rd</small><strong>{{ $latest['third_place'] ?? '—' }}</strong></div>
    </div>
    <p class="subtle">Regular-season leader: <strong>{{ $latestLeader ?? '—' }}</strong></p>
    <a href="/seasons/{{ rawurlencode($latest['season']) }}">View latest season →</a>
  @endif
  </article>
  <article class="card"><div class="section-title"><h2>All-time leaders</h2><a href="/teams">Full franchise ledger →</a></div>
    <div class="leader-list">@foreach($leaders as $label => $row)<div class="leader-row"><div><strong>{{ $row['value'] }}</strong><div class="subtle">{{ $label }}</div></div><strong>{{ $row['team'] }}</strong></div>@endforeach</div>
  </article>
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
