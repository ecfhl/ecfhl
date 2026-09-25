@extends('layouts.app')
@section('title',($season['season'] ?? 'Season').' · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">Season history</div><h1>{{ $season['season'] }}</h1><p>{{ $season['format'] ?? '' }} · {{ $season['status'] ?? '' }}</p></div>

  @php $seasonOptions = app(\App\Support\Archive::class)->seasons(); @endphp
  <div style="display:flex;justify-content:flex-end;margin:0 0 22px"><select aria-label="Go to season" style="width:260px;padding:10px 12px;border-radius:8px" onchange="if(this.value) window.location.href=this.value"><option value="">Go to season...</option>@foreach($seasonOptions as $option)<option value="/seasons/{{ rawurlencode($option['season']) }}" {{ $option['season']===$season['season']?'selected':'' }}>{{ $option['season'] }}</option>@endforeach</select></div>

  <div class="grid-3" style="margin-bottom:20px">
    <div class="card"><span class="subtle">Champion</span><h3>🏆 {{ $season['champion'] ?: 'None' }}</h3></div>
    <div class="card"><span class="subtle">Second</span><h3>🥈 {{ $season['runner_up'] ?: '—' }}</h3></div>
    <div class="card"><span class="subtle">Third</span><h3>🥉 {{ $season['third_place'] ?: '—' }}</h3></div>
  </div>

  <div class="section-title"><h2>Standings</h2><span class="subtle">{{ count($standings) }} teams</span></div>
  <div class="table-card"><div class="table-scroll"><table class="data-table">
    <thead><tr><th class="num">Rank</th><th>Team</th><th class="num">W</th><th class="num">L</th><th class="num">T</th><th class="num">Pts</th><th class="num">Fpts</th><th class="num">Win %</th></tr></thead>
    <tbody>
    @foreach($standings as $r)
      @php $gp=($r['w']??0)+($r['l']??0)+($r['t']??0);$wp=$gp?(($r['w']??0)+0.5*($r['t']??0))/$gp:null; @endphp
      <tr><td class="num">{{ $r['rank'] ?? '—' }}</td><td><strong>{{ $r['team'] }}</strong></td><td class="num">{{ $r['w'] ?? '—' }}</td><td class="num">{{ $r['l'] ?? '—' }}</td><td class="num">{{ $r['t'] ?? '—' }}</td><td class="num">{{ $r['standings_points']!==null ? number_format($r['standings_points'],0) : '—' }}</td><td class="num">{{ $r['fantasy_points_for']!==null ? number_format($r['fantasy_points_for'],0) : '—' }}</td><td class="num">{{ $wp!==null ? number_format($wp*100,1).'%' : '—' }}</td></tr>
    @endforeach
    </tbody>
  </table></div></div>

  @if(!empty($awards))
  <section class="section"><div class="section-title"><h2>Individual awards</h2></div><div class="grid-3">
    @foreach($awards as $a)
      @php $icon=match($a['id']){'president'=>'🏅','leader'=>'⭐','art_ross'=>'🏒','norris'=>'🛡️','vezina'=>'🥅','calder'=>'🌟',default=>'🏆'}; @endphp
      <div class="card"><span class="subtle"><span class="award-icon">{{ $icon }}</span> {{ $a['label'] }}</span><h3>{{ $a['player'] ?: $a['team'] }}</h3>@if($a['player'])<div>{{ $a['team'] }}</div>@endif @if($a['points']!==null)<span class="subtle">{{ number_format($a['points'],0) }} pts</span>@endif</div>
    @endforeach
  </div></section>
  @endif

  <section class="section" style="padding-top:0"><div class="season-links-grid">
    <a class="card season-action-card" href="/trades?season={{ urlencode($season['season']) }}">
      <span class="subtle">Top traders this season</span>
      <div class="top-picks-list">@forelse($tradeLeaders as $r)<div><strong>{{ $r['team'] }}</strong> · {{ $r['value'] }} trades</div>@empty<div class="subtle">No completed trades</div>@endforelse</div>
      <span>View all {{ $tradeCount }} trades →</span>
    </a>

    <a class="card season-action-card" href="/draft?season={{ urlencode($season['season']) }}">
      <span class="subtle">1st round draft picks</span>
      <div class="top-picks-list">
        @forelse($topPicks as $pick)<div><b>{{ $pick['overall'] ?? '—' }}.</b> {{ $pick['player'] ?? '—' }} <small>{{ $pick['team'] ?? '' }}</small></div>@empty<div class="subtle">No first-round draft data</div>@endforelse
      </div>
      <span>View {{ $season['season'] }} draft →</span>
    </a>
  </div></section>
</div>
@endsection
