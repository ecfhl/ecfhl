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

  <section class="section" style="padding-top:0"><div class="season-links-grid season-rank-cards">
    <div class="season-rank-card">
      <div class="season-rank-head"><span class="season-rank-icon">🔄</span><h2>Trades</h2></div>
      <div class="season-rank-body">
        @foreach($tradeLeaders as $i=>$r)
          <div class="season-rank-row"><span class="season-rank-number">{{ $i+1 }}</span><strong class="season-rank-name">{{ $r['team'] }}</strong><strong class="season-rank-value">{{ $r['value'] }}</strong></div>
        @endforeach
      </div>
      <a class="season-rank-footer" href="/trades?season={{ urlencode($season['season']) }}">▶ <span>View all {{ $tradeCount }} trades</span></a>
    </div>

    <div class="season-rank-card">
      <div class="season-rank-head"><span class="season-rank-icon">🏒</span><h2>1st Round Draft Picks</h2></div>
      <div class="season-rank-body">
        @forelse($topPicks as $i=>$pick)
          <div class="season-rank-row draft-rank-row"><span class="season-rank-number">{{ $pick['overall'] ?? $i+1 }}</span><span class="season-rank-name"><strong>{{ $pick['player'] ?? '—' }}</strong><small>{{ $pick['team'] ?? '' }}</small></span></div>
        @empty
          <div class="season-rank-empty">No first-round draft data</div>
        @endforelse
      </div>
      <a class="season-rank-footer" href="/draft?season={{ urlencode($season['season']) }}">▶ <span>View all draft picks</span></a>
    </div>
  </div></section>
</div>
<style>
.season-rank-cards{align-items:start}.season-rank-card{overflow:hidden;border:1px solid var(--border,#d9e0ea);border-radius:20px;background:var(--card,#fff);box-shadow:0 8px 24px rgba(18,38,63,.06)}
.season-rank-head{display:flex;align-items:center;gap:10px;padding:22px 26px;background:rgba(225,232,242,.45);border-bottom:1px solid var(--border,#d9e0ea)}.season-rank-head h2{margin:0;font-size:1.65rem}.season-rank-icon{font-size:1.5rem}
.season-rank-row{display:grid;grid-template-columns:44px minmax(0,1fr) auto;align-items:center;gap:16px;padding:20px 26px;border-bottom:1px solid var(--border,#e2e7ee)}.season-rank-row:last-child{border-bottom:0}.season-rank-number{display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%;background:rgba(225,232,242,.55);font-weight:800}.season-rank-name{min-width:0}.season-rank-value{font-size:1.2rem}.draft-rank-row{grid-template-columns:44px minmax(0,1fr)}.draft-rank-row small{display:block;margin-top:3px;color:var(--muted,#758092);font-weight:400}.season-rank-empty{padding:24px 26px;color:var(--muted,#758092)}
.season-rank-footer{display:block;padding:20px 26px;border-top:1px solid var(--border,#e2e7ee);font-weight:700;text-decoration:none}.season-rank-footer span{text-decoration:underline;text-underline-offset:3px}
@media(max-width:700px){.season-rank-head{padding:18px 20px}.season-rank-head h2{font-size:1.35rem}.season-rank-row{padding:16px 20px;grid-template-columns:38px minmax(0,1fr) auto;gap:12px}.draft-rank-row{grid-template-columns:38px minmax(0,1fr)}.season-rank-number{width:36px;height:36px}.season-rank-footer{padding:18px 20px}}
</style>
@endsection
