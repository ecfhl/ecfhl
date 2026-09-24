@extends('layouts.app')
@section('title','Prizes · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">League prizes</div><h1>Prizes</h1><p>Historical ECFHL prize winnings and season placements.</p></div>
<div class="grid-2">
<div>
<div class="section-title"><h2>Winnings by franchise</h2></div>
<div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Franchise</th><th class="num">Awards</th><th class="num">Winnings</th></tr></thead><tbody>
@foreach($totals as $r)<tr><td><a href="/teams/{{ \Illuminate\Support\Str::slug($r['franchise_name']) }}"><strong>{{ $r['franchise_name'] }}</strong></a></td><td class="num">{{ $r['awards'] }}</td><td class="num">&#36;{{ number_format($r['total_cents']/100,2) }}</td></tr>@endforeach
</tbody></table></div></div>
</div>
<div>
<div class="section-title"><h2>Season placements</h2></div>
<div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Season</th><th>Champion</th><th>Second</th><th>Third</th></tr></thead><tbody>
@foreach($seasons as $s)<tr><td><a href="/seasons/{{ rawurlencode($s['season']) }}"><strong>{{ $s['season'] }}</strong></a></td><td>{{ $s['champion'] ?: '—' }}</td><td>{{ $s['runner_up'] ?: '—' }}</td><td>{{ $s['third_place'] ?: '—' }}</td></tr>@endforeach
</tbody></table></div></div>
</div>
</div>
</div>
@endsection