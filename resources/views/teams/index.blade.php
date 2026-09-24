@extends('layouts.app')
@section('title','Teams · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">Franchise ledger</div><h1>Teams</h1><p>All-time franchise performance across the ECFHL archive.</p></div>
<div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Franchise</th><th class="num">Seasons</th><th class="num">Champion</th><th class="num">Finals</th><th class="num">Pts Leader</th><th class="num">Record</th><th class="num">Win %</th></tr></thead><tbody>
@foreach($teams as $t)<tr><td><a href="/teams/{{ \Illuminate\Support\Str::slug($t['team']) }}"><strong>{{ $t['team'] }}</strong></a></td><td class="num">{{ $t['fantrax_seasons'] ?? '—' }}</td><td class="num">{{ $t['titles'] ?? 0 }}</td><td class="num">{{ $t['finals'] ?? 0 }}</td><td class="num">{{ $t['h2h_first'] ?? 0 }}</td><td class="num">{{ ($t['w']??0).'-'.($t['l']??0).'-'.($t['t']??0) }}</td><td class="num">{{ isset($t['win_pct']) ? number_format($t['win_pct']*100,1).'%' : '—' }}</td></tr>@endforeach
</tbody></table></div></div></div>
@endsection