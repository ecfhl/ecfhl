@extends('layouts.app')
@section('title','Draft · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">Draft archive</div><h1>Draft</h1><p>Browse every recorded ECFHL draft selection.</p></div>
<form class="toolbar" method="get" id="draftForm">
  <select class="control" name="season" onchange="this.form.submit()">
    <option value="all" @selected($selected==='all')>All years</option>
    @foreach($seasons as $s)<option value="{{ $s }}" @selected($s===$selected)>{{ $s }}</option>@endforeach
  </select>
  <input id="draftSearch" name="q" value="{{ $q }}" class="control" style="flex:1;min-width:220px" placeholder="Search player or franchise…">
</form>
<div class="table-card"><div class="table-scroll"><table class="data-table" id="draftTable"><thead><tr>
  @if($selected==='all')<th>Season</th>@endif
  <th class="num">Pick</th><th>Player</th><th>Franchise</th><th class="num">Round</th><th class="num">Pick in round</th>
</tr></thead><tbody>
@foreach($picks as $p)<tr data-search="{{ strtolower(($p['player']??'').' '.($p['team']??'')) }}">
  @if($selected==='all')<td class="nowrap">{{ $p['season'] ?? '' }}</td>@endif
  <td class="num"><strong>{{ $p['overall'] ?? '—' }}</strong></td><td>{{ $p['player'] ?? '' }}</td><td>{{ $p['team'] ?? '' }}</td><td class="num">{{ $p['round'] ?? '' }}</td><td class="num">{{ $p['pick'] ?? '' }}</td>
</tr>@endforeach
</tbody></table></div></div></div>
@endsection
@push('scripts')
<script>
const draftSearch=document.getElementById('draftSearch');
draftSearch.addEventListener('input',e=>{
  const q=e.target.value.toLowerCase();
  document.querySelectorAll('#draftTable tbody tr').forEach(r=>r.style.display=r.dataset.search.includes(q)?'':'none');
});
</script>
@endpush
