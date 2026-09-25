@extends('layouts.app')
@section('title','Trades · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">Transaction archive</div><h1>Trades</h1><p>Search recorded ECFHL trades by season, team or player.</p></div>

  <div class="grid-3 leader-cards" style="margin-bottom:22px">
    <article class="card leader-card">
      <h3 class="leader-card-title">🔄 Top Traders</h3>
      @forelse(array_slice($topTraders,0,5) as $i=>$row)@include('partials.leader-row')@empty<div class="empty">No recorded trades.</div>@endforelse
    </article>
    <article class="card leader-card">
      <h3 class="leader-card-title">🤝 Top Trade Partners</h3>
      @forelse(array_slice($topTradePartners,0,5) as $i=>$row)@include('partials.leader-row')@empty<div class="empty">No recorded trade partners.</div>@endforelse
    </article>
    <article class="card leader-card">
      <h3 class="leader-card-title">1️⃣ 1st Round Picks Traded</h3>
      @forelse(array_slice($topFirstRoundTraders,0,5) as $i=>$row)@include('partials.leader-row')@empty<div class="empty">No recorded own first-round picks traded.</div>@endforelse
    </article>
  </div>

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
    @include('partials.trade-card')
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
