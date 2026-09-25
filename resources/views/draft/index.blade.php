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
<div id="draft-results"></div>
<form class="toolbar" method="get" id="draftForm">
  <select class="control" name="season" onchange="this.form.submit()"><option value="all" @selected($selected==='all')>All years</option>@foreach($seasons as $s)<option value="{{ $s }}" @selected($s===$selected)>{{ $s }}</option>@endforeach</select>
  <input id="draftSearch" name="q" value="{{ $q }}" class="control" style="flex:1;min-width:220px" placeholder="Search player or team…">
</form>
<div class="table-card"><div class="table-scroll"><table class="data-table" id="draftTable"><thead><tr>@if($selected==='all')<th>Season</th>@endif<th class="num">Pick</th><th>Player</th><th>Team</th><th class="num">Round</th><th class="num">Pick in round</th></tr></thead><tbody>
@php $lastDraftGroup = null; @endphp
@foreach($picks as $p)
  @php
    $draftGroup = $selected === 'all' ? (($p['season'] ?? '').'|'.($p['round'] ?? '')) : (string)($p['round'] ?? '');
    $showRoundHeader = $draftGroup !== $lastDraftGroup;
    $lastDraftGroup = $draftGroup;
    $colspan = $selected === 'all' ? 6 : 5;
  @endphp
  @if($showRoundHeader)
    <tr class="draft-round-header"><td colspan="{{ $colspan }}">@if($selected==='all')<span>{{ $p['season'] ?? '' }}</span> · @endif Round {{ $p['round'] ?? '—' }}</td></tr>
  @endif
  <tr class="draft-pick-row" data-search="{{ strtolower(($p['player']??'').' '.($p['team']??'')) }}">@if($selected==='all')<td class="nowrap">{{ $p['season'] ?? '' }}</td>@endif<td class="num"><strong>{{ $p['overall'] ?? '—' }}</strong></td><td>{{ $p['player'] ?? '' }}</td><td>{{ $p['team'] ?? '' }}</td><td class="num">{{ $p['round'] ?? '' }}</td><td class="num">{{ $p['pick'] ?? '' }}</td></tr>
@endforeach
</tbody></table></div></div></div>
@endsection
@push('scripts')
<script>
const draftSearch=document.getElementById('draftSearch');draftSearch.addEventListener('input',e=>{const q=e.target.value.toLowerCase();document.querySelectorAll('#draftTable .draft-pick-row').forEach(r=>r.style.display=r.dataset.search.includes(q)?'':'none');document.querySelectorAll('#draftTable .draft-round-header').forEach(h=>{let row=h.nextElementSibling,visible=false;while(row&&!row.classList.contains('draft-round-header')){if(row.classList.contains('draft-pick-row')&&row.style.display!=='none')visible=true;row=row.nextElementSibling;}h.style.display=visible?'':'none';});});
document.querySelectorAll('.expand-card-link').forEach(btn=>btn.addEventListener('click',()=>{const box=document.getElementById(btn.dataset.expandTarget),opening=box.querySelector('.expand-row[hidden]')!==null;box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=!opening&&i>=5);btn.textContent=opening?'Show top 5':'View all';}));
@if($selected !== 'all')
window.addEventListener('load',()=>{const target=document.getElementById('draft-results');if(target)target.scrollIntoView({block:'start'});});
@endif
</script>
<style>#draft-results{scroll-margin-top:170px}.draft-round-header td{padding:14px 20px!important;background:rgba(225,232,242,.55);font-size:15px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--muted,#687486);border-top:2px solid var(--line,#dce2ea);border-bottom:1px solid var(--line,#dce2ea)}.draft-round-header:first-child td{border-top:0}.expand-card-link{display:block;margin:14px auto 0;padding:0;border:0;background:none;color:var(--accent,#1d5fa7);font:inherit;font-weight:700;cursor:pointer}.expand-card-link:hover{text-decoration:underline}</style>
@endpush
