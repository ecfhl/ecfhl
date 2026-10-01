@extends('layouts.app')
@section('title','Standings · ECFHL')
@section('content')
<div class="shell standings-page">
  <div class="page-head">
    <div class="eyebrow">2026-27 season</div>
    <h1>Standings</h1>
    @include('partials.fantrax-standings',['season'=>$season])
  </div>

  <div class="section-title standings-title">
    <div>
      <h2>Standings</h2>
      @if(!empty($standingsLastUpdate))
        <p class="subtle standings-updated">Updated @include('partials.updated-time',['value'=>$standingsLastUpdate])</p>
      @endif
    </div>
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th class="num">Rank</th>
            <th>Team</th>
            <th class="num">W</th>
            <th class="num">L</th>
            <th class="num">T</th>
            <th class="num">Pts</th>
            <th class="num">Fpts</th>
            <th class="num">Win %</th>
          </tr>
        </thead>
        <tbody>
          @foreach($standings as $r)
            @php
              $gp=($r['w']??0)+($r['l']??0)+($r['t']??0);
              $wp=$gp?(($r['w']??0)+0.5*($r['t']??0))/$gp:null;
            @endphp
            <tr>
              <td class="num">{{ $r['rank'] ?? '—' }}</td>
              <td><strong><a href="/teams/current/{{ \Illuminate\Support\Str::slug($r['team']) }}" class="standings-team-link">{{ $r['team'] }}</a></strong></td>
              <td class="num">{{ $r['w'] ?? '—' }}</td>
              <td class="num">{{ $r['l'] ?? '—' }}</td>
              <td class="num">{{ $r['t'] ?? '—' }}</td>
              <td class="num">{{ $r['standings_points']!==null ? number_format($r['standings_points'],0) : '—' }}</td>
              <td class="num">{{ $r['fantasy_points_for']!==null ? number_format($r['fantasy_points_for'],0) : '—' }}</td>
              <td class="num">{{ $wp!==null ? number_format($wp*100,1).'%' : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
</div>
<style>
.standings-page{padding-bottom:26px}
.standings-title{margin-top:8px}.standings-updated{margin:4px 0 0;font-size:12px}.standings-team-link{color:inherit;text-decoration:none}.standings-team-link:hover{text-decoration:underline}
</style>
@endsection
