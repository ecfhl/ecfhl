@extends('layouts.app')
@section('title',($season['season'] ?? 'Season').' · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Season history</div><h1>{{ $season['season'] }}</h1><p>{{ $season['format'] ?? '' }} · {{ $season['status'] ?? '' }}</p></div>
<div class="grid-3" style="margin-bottom:20px"><div class="card"><span class="subtle">Champion</span><h3>🏆 {{ $season['champion'] ?: 'None' }}</h3></div><div class="card"><span class="subtle">Second</span><h3>🥈 {{ $season['runner_up'] ?: '—' }}</h3></div><div class="card"><span class="subtle">Third</span><h3>🥉 {{ $season['third_place'] ?: '—' }}</h3></div></div>
@if(!empty($season['note']))<div class="card" style="margin-bottom:20px"><strong>Season note</strong><p class="subtle">{{ $season['note'] }}</p></div>@endif
<div class="section-title"><h2>Standings</h2><span class="subtle">{{ count($standings) }} teams</span></div>
<div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th class="num">Rank</th><th>Franchise</th><th>Season name</th><th class="num">W</th><th class="num">L</th><th class="num">T</th><th class="num">Pts</th><th class="num">Fpts</th><th class="num">Win %</th></tr></thead><tbody>
@foreach($standings as $r) @php($gp=($r['w']??0)+($r['l']??0)+($r['t']??0)) @php($wp=$gp ? (($r['w']??0)+0.5*($r['t']??0))/$gp : null)
<tr><td class="num">{{ $r['rank'] ?? '—' }}</td><td><strong>{{ $r['team'] }}</strong></td><td>{{ $r['original_name'] }}</td><td class="num">{{ $r['w'] ?? '—' }}</td><td class="num">{{ $r['l'] ?? '—' }}</td><td class="num">{{ $r['t'] ?? '—' }}</td><td class="num">{{ $r['standings_points'] ?? '—' }}</td><td class="num">{{ isset($r['fantasy_points_for']) ? number_format($r['fantasy_points_for']) : '—' }}</td><td class="num">{{ $wp!==null ? number_format($wp*100,1).'%' : '—' }}</td></tr>
@endforeach</tbody></table></div></div>
@if(!empty($awards))<section class="section"><div class="section-title"><h2>Individual awards</h2></div><div class="grid-3">@foreach($awards as $a)<div class="card"><span class="subtle">{{ $a['label'] }}</span><h3>{{ $a['player'] }}</h3><div>{{ $a['team'] }}</div>@if($a['points'])<span class="subtle">{{ $a['points'] }} pts</span>@endif</div>@endforeach</div></section>@endif
</div>
@endsection