@extends('layouts.app')
@section('title','Seasons · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">League archive</div><h1>Seasons</h1><p>Standings, champions and historical results from 2007–08 to the present.</p></div>
<div class="season-list">@foreach($seasons as $season)
<a class="season-row" href="/seasons/{{ rawurlencode($season['season']) }}"><strong>{{ $season['season'] }}</strong><div><div class="champ">{{ $season['champion'] ? '🏆 '.$season['champion'] : 'No champion awarded' }}</div><span class="subtle">{{ $season['status'] ?? '' }}</span></div><div><strong>{{ $season['format'] ?? '' }}</strong><br><span class="subtle">{{ $season['runner_up'] ? '2nd: '.$season['runner_up'] : '' }}</span></div><span>View →</span></a>
@endforeach</div></div>
@endsection