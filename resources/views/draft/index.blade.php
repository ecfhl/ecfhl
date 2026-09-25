@extends('layouts.app')
@section('title','Draft · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">Draft archive</div><h1>Draft</h1><p>Browse every recorded ECFHL draft selection.</p></div>
<div class="grid-3 leader-cards" style="margin-bottom:22px">
@foreach([['overall1','#1 Overall Picks'],['top5','Top 5 Picks'],['round1','1st Round Picks']] as [$key,$title])
<article class="card leader-card" data-expand-card>
  <h3 class="leader-card-title">{{ $title }}</h3>
  <div id="draft-{{ $key }}">
  @forelse(($draftLeaders[$key]??[]) as $i=>$row)<div class="expand-row" @if($i>=5) hidden @endif>@include('partials.leader-row')</div>@empty<div class="empty">No draft data.</div>@endforelse
  </div>
  @if(count($draftLeaders[$key]??[])>5)<button type="button" class="expand-card-link" data-expand-target="draft-{{ $key }}">View all</button>@endif
</article>
@endforeach
</div>
<form class="toolbar" method="get" id="draftForm">
  <select class="control" name="season" onchange="this.form.submit()"><option value="all" @selected($selected==='all')>All years</option>@foreach($seasons as $s)<option value="{{ $s }}" @selected($s===$selected)>{{ $s }}</option>@endforeach</select>
  <input id="draftSearch" name="q" value="{{ $q }}" class="control" style="flex:1;min-width:220px" placeholder="Search player or team…">
</form>
<div class="table-card"><div class="table-scroll"><table class="data-table" id="draftTable"><thead><tr>@if($selected==='all')<th>Season</th>@endif<th class="num">Pick</th><th>Player</th><th>Team</th><th class="num">Round</th><th class="num">Pick in round</th></tr></thead><tbody>
@foreach($picks as $p)<tr data-search="{{ strtolower(($p['player']??'').' '.($p['team']??'')) }}">@if($selected==='all')<td class="nowrap">{{ $p['season'] ?? '' }}</td>@endif<td class="num"><strong>{{ $p['overall'] ?? '—' }}</strong></td><td>{{ $p['player'] ?? '' }}</td><td>{{ $p['team'] ?? '' }}</td><td class="num">{{ $p['round'] ?? '' }}</td><td class="num">{{ $p['pick'] ?? '' }}</td></tr>@endforeach
</tbody></table></div></div></div>
@endsection
@push('scripts')
<script>
const draftSearch=document.getElementById('draftSearch');draftSearch.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('#draftTable tbody tr').forEach(r=>r.style.display=r.dataset.search.includes(q)?'':'none');});
document.querySelectorAll('.expand-card-link').forEach(btn=>btn.addEventListener('click',()=>{const box=document.getElementById(btn.dataset.expandTarget),opening=box.querySelector('.expand-row[hidden]')!==null;box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=!opening&&i>=5);btn.textContent=opening?'Show top 5':'View all';}));
</script>
<style>.expand-card-link{display:block;margin:14px auto 0;padding:0;border:0;background:none;color:var(--accent,#1d5fa7);font:inherit;font-weight:700;cursor:pointer}.expand-card-link:hover{text-decoration:underline}</style>
@endpush
