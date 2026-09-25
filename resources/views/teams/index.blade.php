@extends('layouts.app')
@section('title','Teams · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">Franchise ledger</div><h1>Teams</h1><p>All-time franchise performance across the ECFHL archive.</p></div>

  <div class="filter-bar">
    <div class="filter-group"><span>Status</span>
      <a class="filter-button {{ $status==='all'?'active':'' }}" href="?type={{ $type }}&status=all">Active + Inactive</a>
      <a class="filter-button {{ $status==='active'?'active':'' }}" href="?type={{ $type }}&status=active">Active</a>
      <a class="filter-button {{ $status==='inactive'?'active':'' }}" href="?type={{ $type }}&status=inactive">Inactive</a>
    </div>
  </div>

  <div class="table-card"><div class="table-scroll"><table class="data-table sortable-table" id="teamsTable">
    <thead><tr>
      <th data-col="0" data-type="text">Franchise</th>
      <th data-col="1" data-type="num" class="num">Seasons</th>
      <th data-col="2" data-type="num" class="num">Champion</th>
      <th data-col="3" data-type="num" class="num">2nd</th>
      <th data-col="4" data-type="num" class="num">3rd</th>
      <th data-col="5" data-type="num" class="num">President</th>
      <th data-col="6" data-type="num" class="num">Fpts Leader</th>
      <th data-col="7" data-type="text" class="num">Record</th>
      <th data-col="8" data-type="num" class="num">Win %</th>
    </tr></thead>
    <tbody>
    @foreach($teams as $t)
      <tr>
        <td data-value="{{ strtolower($t['team']) }}"><a href="/teams/{{ \Illuminate\Support\Str::slug($t['team']) }}"><strong>{{ $t['team'] }}</strong></a></td>
        <td class="num" data-value="{{ $t['seasons'] }}">{{ $t['seasons'] }}</td>
        <td class="num" data-value="{{ $t['champion'] }}">{{ $t['champion'] }}</td>
        <td class="num" data-value="{{ $t['second'] }}">{{ $t['second'] }}</td>
        <td class="num" data-value="{{ $t['third'] }}">{{ $t['third'] }}</td>
        <td class="num" data-value="{{ $t['president'] }}">{{ $t['president'] }}</td>
        <td class="num" data-value="{{ $t['fpts_leader'] }}">{{ $t['fpts_leader'] }}</td>
        <td class="num" data-value="{{ $t['w']*1000000+$t['l']*1000+$t['t'] }}">{{ $t['games'] ? ($t['w'].'-'.$t['l'].'-'.$t['t']) : '—' }}</td>
        <td class="num" data-value="{{ $t['win_pct'] ?? -1 }}">{{ $t['win_pct']!==null ? number_format($t['win_pct']*100,1).'%' : '—' }}</td>
      </tr>
    @endforeach
    </tbody>
  </table></div></div>
</div>
@endsection
@push('scripts')
<script>
(() => {
  const table=document.getElementById('teamsTable'), body=table.tBodies[0];
  let activeCol=null, direction=-1;
  table.querySelectorAll('th[data-col]').forEach(th=>th.addEventListener('click',()=>{
    const col=+th.dataset.col, type=th.dataset.type;
    direction = activeCol===col ? -direction : (type==='text'?1:-1);
    activeCol=col;
    const rows=[...body.rows];
    rows.sort((a,b)=>{
      let av=a.cells[col].dataset.value ?? a.cells[col].textContent.trim();
      let bv=b.cells[col].dataset.value ?? b.cells[col].textContent.trim();
      if(type==='num'){av=parseFloat(av);bv=parseFloat(bv);return (av-bv)*direction;}
      return av.localeCompare(bv)*direction;
    });
    rows.forEach(r=>body.appendChild(r));
    table.querySelectorAll('th').forEach(x=>x.classList.remove('sort-asc','sort-desc'));
    th.classList.add(direction===1?'sort-asc':'sort-desc');
  }));
})();
</script>
@endpush
