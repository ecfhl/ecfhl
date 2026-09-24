@extends('layouts.app')
@section('title','Trades · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">Transaction archive</div><h1>Trades</h1><p>Search recorded ECFHL trades by season, franchise or player.</p></div>

  <div class="toolbar">
    <select id="tradeSeason" class="control">
      <option value="">All seasons</option>
      @foreach($seasons as $s)<option value="{{ $s }}" @selected($selectedSeason===$s)>{{ $s }}</option>@endforeach
    </select>
    <select id="tradeTeam" class="control">
      <option value="">All teams</option>
      @foreach($teams as $team)<option value="{{ strtolower($team) }}" @selected(strtolower($selectedTeam)===strtolower($team))>{{ $team }}</option>@endforeach
    </select>
    <input id="tradeSearch" class="control" style="flex:1;min-width:220px" placeholder="Search player or draft pick…">
  </div>

  <div id="tradeList" class="season-list">
  @foreach($trades as $t)
    @php($hay=strtolower(($t['from']??'').' '.($t['to']??'').' '.implode(' ',$t['from_items']??[]).' '.implode(' ',$t['to_items']??[])))
    @php($teamsHay=strtolower(implode('|',$t['filter_teams'] ?? [$t['from']??'',$t['to']??''])))
    <article class="card trade-item" data-season="{{ $t['season'] }}" data-search="{{ $hay }}" data-teams="{{ $teamsHay }}">
      <div class="section-title">
        <div><strong>{{ $t['from'] ?? '?' }} ↔ {{ $t['to'] ?? '?' }}</strong><br><span class="subtle">{{ $t['date'] ?? '' }}</span></div>
        <span class="pill {{ !empty($t['vetoed']) ? 'pill-vetoed' : '' }}">{{ $t['season'] }}{{ !empty($t['vetoed']) ? ' · Vetoed' : '' }}</span>
      </div>
      <div class="grid-2">
        <div><strong>{{ $t['from'] ?? '?' }} sent</strong><ul>@foreach($t['from_items']??[] as $i)<li>{{ $i }}</li>@endforeach</ul></div>
        <div><strong>{{ $t['to'] ?? '?' }} sent</strong><ul>@foreach($t['to_items']??[] as $i)<li>{{ $i }}</li>@endforeach</ul></div>
      </div>
    </article>
  @endforeach
  </div>
</div>
@endsection
@push('scripts')
<script>
const q=document.getElementById('tradeSearch'),s=document.getElementById('tradeSeason'),t=document.getElementById('tradeTeam');
function filterTrades(){
  const n=q.value.toLowerCase(), team=t.value.toLowerCase();
  document.querySelectorAll('.trade-item').forEach(e=>{
    const seasonOk=!s.value||e.dataset.season===s.value;
    const teamOk=!team||e.dataset.teams.split('|').includes(team);
    const searchOk=!n||e.dataset.search.includes(n);
    e.style.display=(seasonOk&&teamOk&&searchOk)?'':'none';
  });
}
q.addEventListener('input',filterTrades);s.addEventListener('change',filterTrades);t.addEventListener('change',filterTrades);filterTrades();
</script>
@endpush
