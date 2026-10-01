@extends('layouts.app')
@section('title',($season['season'] ?? 'Season').' · ECFHL')
@section('content')
<div class="shell">
  @php $seasonOptions = app(\App\Support\Archive::class)->seasons(); @endphp
  <div class="page-head">
    <div class="eyebrow">Season history</div>
    <div style="display:flex;align-items:center;gap:10px"><span style="font-size:1.05em">📖</span><label class="season-title-switcher">
      <span class="sr-only">Season</span>
      <select aria-label="Season" onchange="if(this.value) window.location.href=this.value">
        @foreach($seasonOptions as $option)
          <option value="/seasons/{{ rawurlencode($option['season']) }}" {{ $option['season']===$season['season']?'selected':'' }}>{{ $option['season'] }}</option>
        @endforeach
      </select>
    </label></div>
    <p>{{ $season['format'] ?? '' }} · {{ $season['status'] ?? '' }}</p>
    @include('partials.fantrax-standings',['season'=>$season])
  </div>

  @if(($season['season'] ?? '') !== '2026-27')
  <div class="season-result-podium">
    <div class="season-result-entry"><strong class="season-result-team">{{ $season['runner_up'] ?: '—' }}</strong><div class="podium-place podium-second"><span class="podium-medal">🥈</span><small>2nd</small></div></div>
    <div class="season-result-entry"><strong class="season-result-team">{{ $season['champion'] ?: '—' }}</strong><div class="podium-place podium-first"><span class="podium-medal">🏆</span><small>Champion</small></div></div>
    <div class="season-result-entry"><strong class="season-result-team">{{ $season['third_place'] ?: '—' }}</strong><div class="podium-place podium-third"><span class="podium-medal">🥉</span><small>3rd</small></div></div>
  </div>
  @endif

  <div class="section-title">
    <h2>Standings</h2>
    <span class="subtle">
      {{ count($standings) }} teams
      @if(!empty($standingsLastUpdate))
        · Updated @include('partials.updated-time',['value'=>$standingsLastUpdate])
      @endif
    </span>
  </div>
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
      <div class="card"><span class="subtle"><span class="award-icon">{{ $icon }}</span> {{ $a['label'] }}</span><h3>@if($a['player'])@include('partials.player-link',['name'=>$a['player']])@else{{ $a['team'] }}@endif</h3>@if($a['player'])<div>{{ $a['team'] }}</div>@endif @if($a['points']!==null)<span class="subtle">{{ number_format($a['points'],0) }} pts</span>@endif</div>
    @endforeach
  </div></section>
  @endif

  <section class="section season-summary-links"><div class="season-links-grid season-rank-cards">
    <div class="card leader-card">
      <div class="leader-card-head"><h3 class="leader-card-title">🔄 Trades</h3></div>
      <div class="season-rank-body">
        @foreach($tradeLeaders as $i=>$r)
          <div class="leader-rank"><span>{{ $i+1 }}</span><strong>{{ $r['team'] }}</strong><b>{{ $r['value'] }}</b></div>
        @endforeach
      </div>
      <a class="season-rank-footer" href="/trades?season={{ urlencode($season['season']) }}">▶ <span>View all {{ $tradeCount }} trades</span></a>
    </div>

    <div class="card leader-card">
      <div class="leader-card-head"><h3 class="leader-card-title">🏒 1st Round Draft Picks</h3></div>
      <div class="season-rank-body">
        @forelse($topPicks as $i=>$pick)
          <div class="leader-rank"><span>{{ $pick['overall'] ?? $i+1 }}</span><strong>@include('partials.player-link',['name'=>$pick['player'] ?? ''])<small>{{ $pick['team'] ?? '' }}</small></strong></div>
        @empty
          <div class="empty">No first-round draft data</div>
        @endforelse
      </div>
      <a class="season-rank-footer" href="/draft?season={{ urlencode($season['season']) }}">▶ <span>View all draft picks</span></a>
    </div>
  </div></section>
</div>
<style>
.season-title-switcher{display:block;width:min(320px,100%)}.season-title-switcher select{appearance:auto;width:auto;max-width:100%;border:0;background:transparent;color:var(--text);font:inherit;font-size:34px;font-weight:800;line-height:1.05;letter-spacing:-1px;padding:0 30px 0 0;cursor:pointer}.season-title-switcher select:focus{outline:none}
.season-result-podium{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));align-items:end;gap:12px;min-height:205px;margin:0 0 28px;padding:24px 22px 0;background:var(--panel,#fff);border:1px solid var(--border,#d9e0ea);border-radius:20px;box-shadow:0 8px 24px rgba(18,38,63,.06);overflow:hidden}
.season-result-entry{display:flex;flex-direction:column;align-items:center;justify-content:flex-end;height:100%;min-width:0}.season-result-team{font-size:17px;text-align:center;margin-bottom:9px;line-height:1.2;overflow-wrap:anywhere}.season-result-podium .podium-place{width:100%;justify-content:center;padding:10px 8px}.season-result-podium .podium-second{height:112px}.season-result-podium .podium-first{height:145px}.season-result-podium .podium-third{height:90px}.season-result-podium .podium-medal{margin-bottom:7px}
.season-summary-links{padding-top:28px}.season-rank-cards{align-items:start}.season-rank-footer{display:block;padding:15px 18px;border-top:1px solid var(--line);font-weight:700;text-decoration:none}
@media(max-width:700px){.season-result-podium{gap:8px;padding:20px 10px 0;min-height:185px}.season-result-team{font-size:13px}.season-result-podium .podium-second{height:95px}.season-result-podium .podium-first{height:125px}.season-result-podium .podium-third{height:78px}}
</style>
@endsection