@extends('layouts.app')
@section('title','Prizes · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">League prizes</div><h1>Prizes</h1><p>Historical ECFHL awards and prize winnings.</p></div>

  <div class="prizes-grid">
    <div>
      <div class="section-title"><h2>Awards and Total Winnings</h2></div>
      <div class="table-card"><div class="table-scroll"><table class="data-table">
        <thead><tr><th>Franchise</th><th class="num">Awards</th><th class="num">Total Winnings</th></tr></thead>
        <tbody>@foreach($totals as $r)<tr>
          <td class="nowrap"><a href="/teams/{{ \Illuminate\Support\Str::slug($r['franchise_name']) }}"><strong>{{ $r['franchise_name'] }}</strong></a></td>
          <td class="num">{{ $r['awards'] }}</td>
          <td class="num">&#36;{{ number_format($r['total_cents']/100,2) }}</td>
        </tr>@endforeach</tbody>
      </table></div></div>
    </div>

    <div>
      <div class="section-title"><h2>Awards by season</h2></div>
      <div class="table-card"><div class="table-scroll"><table class="data-table awards-season-table">
        <thead><tr><th>Season</th><th>Playoffs</th><th>Regular Season</th><th>Individual Awards</th></tr></thead>
        <tbody>
        @foreach($awardsBySeason as $row)<tr>
          <td class="nowrap"><strong>{{ $row['season'] }}</strong></td>
          <td>
            @foreach($row['playoffs'] as $item)
              @php
                $icon = str_starts_with($item,'Champion:')
                  ? '🏆'
                  : (str_starts_with($item,'Second place:') ? '🥈' : '🥉');
              @endphp
              <div>{{ $icon }} {{ preg_replace('/^[^:]+:\s*/','',$item) }}</div>
            @endforeach
          </td>
          <td>
            @forelse($row['regular'] as $item)
              @php
                $icon = str_starts_with($item,'President Trophy:') ? '🏅' : '⭐';
              @endphp
              <div>{{ $icon }} {{ preg_replace('/^[^:]+:\s*/','',$item) }}</div>
            @empty<span class="subtle">—</span>@endforelse
          </td>
          <td>
            @foreach($row['individual'] as $item)
              @php
                $icon = str_starts_with($item,'Art Ross:') ? '🏒' : (str_starts_with($item,'Norris:') ? '🛡️' : (str_starts_with($item,'Vezina:') ? '🥅' : '🌟'));
              @endphp
              <div>{{ $icon }} {{ preg_replace('/^[^:]+:\s*/','',$item) }}</div>
            @endforeach
          </td>
        </tr>@endforeach
        </tbody>
      </table></div></div>
    </div>
  </div>
</div>
@endsection
