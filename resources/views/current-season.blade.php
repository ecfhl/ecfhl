@extends('layouts.app')
@section('title','Current Season · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head">
    <div class="eyebrow">Current season</div>
    <h1>{{ $season['season'] ?? '2026-27' }}</h1>
    <p>{{ $season['format'] ?? 'Head-to-Head' }} · {{ $season['status'] ?? 'Upcoming' }}</p>
  </div>

  <div class="grid-3" style="margin-bottom:22px">
    <article class="card"><span class="subtle">Teams</span><h2 style="margin-bottom:0">{{ count($standings) }}</h2></article>
    <article class="card"><span class="subtle">Trades</span><h2 style="margin-bottom:0">{{ $tradeCount }}</h2></article>
    <article class="card"><span class="subtle">Draft picks recorded</span><h2 style="margin-bottom:0">{{ count($draftPicks) }}</h2></article>
  </div>

  @if(empty($draftPicks))
  <div class="card" style="margin-bottom:22px">
    <strong>Draft pending</strong>
    <p class="subtle" style="margin-bottom:0">The {{ $season['season'] }} draft has not been added yet. Draft results will appear here once they are available.</p>
  </div>
  @endif

  <div class="section-title"><h2>Teams</h2><span class="subtle">{{ count($standings) }} franchises</span></div>
  <div class="table-card" style="margin-bottom:28px"><div class="table-scroll"><table class="data-table">
    <thead><tr><th>Team</th><th class="num">W</th><th class="num">L</th><th class="num">T</th><th class="num">Fpts</th></tr></thead>
    <tbody>@foreach($standings as $r)<tr><td><strong>{{ $r['team'] }}</strong></td><td class="num">{{ $r['w'] ?? 0 }}</td><td class="num">{{ $r['l'] ?? 0 }}</td><td class="num">{{ $r['t'] ?? 0 }}</td><td class="num">{{ $r['fantasy_points_for']!==null ? number_format($r['fantasy_points_for'],1) : '—' }}</td></tr>@endforeach</tbody>
  </table></div></div>

  <div class="grid-2 season-links-grid">
    <a class="card season-action-card" href="/trades?season={{ urlencode($season['season']) }}">
      <span class="subtle">Current season activity</span><h3>Trades</h3>
      <div class="top-picks-list">@forelse($tradeLeaders as $r)<div><strong>{{ $r['team'] }}</strong> · {{ $r['value'] }} trades</div>@empty<div class="subtle">No completed trades</div>@endforelse</div>
      <span>View all {{ $tradeCount }} trades →</span>
    </a>
    <a class="card season-action-card" href="/draft?season={{ urlencode($season['season']) }}">
      <span class="subtle">Current season</span><h3>Draft</h3>
      <div class="top-picks-list">@forelse(array_slice($draftPicks,0,3) as $i=>$pick)<div><b>{{ $i+1 }}.</b> {{ $pick['player'] ?? '—' }} <small>{{ $pick['team'] ?? '' }}</small></div>@empty<div class="subtle">Draft results pending</div>@endforelse</div>
      <span>View {{ $season['season'] }} draft →</span>
    </a>
  </div>

  <div style="margin-top:22px"><a class="button secondary" href="/seasons/{{ rawurlencode($season['season']) }}">Full season page →</a></div>
</div>
@endsection
