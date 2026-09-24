@extends('layouts.app')
@section('title','Prizes · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">League prizes</div><h1>Prizes</h1><p>Championship placements and award history by season.</p></div>
<div class="table-card"><div class="table-scroll"><table class="data-table"><thead><tr><th>Season</th><th>Format</th><th>Champion</th><th>Second</th><th>Third</th></tr></thead><tbody>
@foreach($seasons as $s)<tr><td><a href="/seasons/{{ rawurlencode($s['season']) }}"><strong>{{ $s['season'] }}</strong></a></td><td>{{ $s['format'] }}</td><td>{{ $s['champion'] ?: '—' }}</td><td>{{ $s['runner_up'] ?: '—' }}</td><td>{{ $s['third_place'] ?: '—' }}</td></tr>@endforeach
</tbody></table></div></div><p class="subtle" style="margin-top:14px">Detailed dollar-value prize history will be migrated from the historical prize ledger next.</p></div>
@endsection