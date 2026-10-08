@extends('layouts.app')
@section('title','Draft · ECFHL')
@section('content')
<div class="shell"><div class="page-head"><div class="eyebrow">Draft archive</div><h1>📖 Draft</h1><p>Browse every recorded ECFHL draft selection.</p></div>
<div class="grid-3 leader-cards draft-leader-cards" style="margin-bottom:22px">
@foreach([['overall1','#1 Overall Picks'],['top5','Top 5 Picks'],['round1','1st Round Picks']] as [$key,$title])
<article class="card leader-card" data-expand-card>
  <div class="leader-card-head"><h3 class="leader-card-title">{{ $title }}</h3>@if(count($draftLeaders[$key]??[])>3)<button type="button" class="expand-card-icon" data-expand-target="draft-{{ $key }}" aria-label="Expand {{ $title }}" title="Expand">⛶</button>@endif</div>
  <div id="draft-{{ $key }}">
  @forelse(($draftLeaders[$key]??[]) as $i=>$row)<div class="expand-row" @if($i>=3) hidden @endif>@include('partials.leader-row')</div>@empty<div class="empty">No draft data.</div>@endforelse
  </div>
</article>
@endforeach
</div>
<div id="draft-results"></div>
@php
    $archive = app(\App\Support\Archive::class);
    $draftFranchises = collect($archive->teamLedger($archive->mode(), 'all'))->mapWithKeys(fn($f) => [$f['id'] => $f['team']])->sort();
@endphp
<form class="toolbar" method="get" id="draftForm">
  <input type="hidden" name="type" value="{{ $archive->mode() }}">
  <select class="control" name="season" onchange="this.form.submit()"><option value="all" @selected($selected==='all')>All years</option>@foreach($seasons as $s)<option value="{{ $s }}" @selected($s===$selected)>{{ $s }}</option>@endforeach</select>
  <select class="control" id="draftFranchise" name="franchise"><option value="">All franchises</option>@foreach($draftFranchises as $id=>$franchise)<option value="{{ $id }}" @selected($selectedFranchise===$id)>{{ $franchise }}</option>@endforeach</select>
  <input id="draftSearch" name="q" value="{{ $q }}" class="control" style="flex:1;min-width:220px" placeholder="Search player or team…">
</form>
<div class="table-card"><div class="table-scroll"><table class="data-table" id="draftTable"><thead><tr><th class="num">Pick</th><th class="num">Overall</th><th>Player</th><th>Team</th></tr></thead><tbody>
@php $lastSeason = null; $lastRound = null; @endphp
@foreach($picks as $p)
  @php
    $season = $p['season'] ?? '—';
    $round = $p['round'] ?? '—';
    $showSeasonHeader = $selected === 'all' && $season !== $lastSeason;
    if ($showSeasonHeader) $lastRound = null;
    $showRoundHeader = $round !== $lastRound;
    $lastSeason = $season;
    $lastRound = $round;
  @endphp
  @if($showSeasonHeader)<tr class="draft-season-header"><td colspan="4">{{ $season }}</td></tr>@endif
  @if($showRoundHeader)<tr class="draft-round-header" id="draft-round-{{ $round }}"><td colspan="4">Round {{ $round }}</td></tr>@endif
  <tr class="draft-pick-row" data-franchise="{{ $p['franchise_id'] ?? '' }}" data-search="{{ strtolower(($p['player']??'').' '.($p['team']??'').' '.$season) }}"><td class="num"><span class="draft-number">{{ $p['pick'] ?? '' }}</span></td><td class="num"><span class="draft-number draft-number-secondary">{{ $p['overall'] ?? '—' }}</span></td><td>@include('partials.player-link',['name'=>$p['player'] ?? ''])</td><td>{{ $p['team'] ?? '' }}</td></tr>
@endforeach
</tbody></table></div></div></div>
@endsection
@push('scripts')
<script>
const draftSearch=document.getElementById('draftSearch'),draftFranchise=document.getElementById('draftFranchise');
function filterDraft(){const q=draftSearch.value.toLowerCase(),franchise=draftFranchise.value;document.querySelectorAll('#draftTable .draft-pick-row').forEach(r=>r.style.display=r.dataset.search.includes(q)&&(!franchise||r.dataset.franchise===franchise)?'':'none');document.querySelectorAll('#draftTable .draft-round-header').forEach(h=>{let row=h.nextElementSibling,visible=false;while(row&&!row.classList.contains('draft-round-header')&&!row.classList.contains('draft-season-header')){if(row.classList.contains('draft-pick-row')&&row.style.display!=='none')visible=true;row=row.nextElementSibling;}h.style.display=visible?'':'none';});document.querySelectorAll('#draftTable .draft-season-header').forEach(h=>{let row=h.nextElementSibling,visible=false;while(row&&!row.classList.contains('draft-season-header')){if(row.classList.contains('draft-pick-row')&&row.style.display!=='none')visible=true;row=row.nextElementSibling;}h.style.display=visible?'':'none';});}
draftSearch.addEventListener('input',filterDraft);draftFranchise.addEventListener('change',()=>document.getElementById('draftForm').submit());
document.querySelectorAll('.expand-card-icon').forEach(btn=>btn.addEventListener('click',()=>{const box=document.getElementById(btn.dataset.expandTarget),opening=box.querySelector('.expand-row[hidden]')!==null;box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=!opening&&i>=3);btn.textContent=opening?'×':'⛶';btn.setAttribute('aria-label',opening?'Minimize':'Expand');btn.title=opening?'Minimize':'Expand';}));
const draftParams=new URLSearchParams(window.location.search);const draftFilterUsed=draftParams.has('season')||draftParams.has('q');if(draftFilterUsed){window.addEventListener('load',()=>{const hash=window.location.hash;if(hash){const target=document.querySelector(hash);if(target){target.scrollIntoView({block:'start'});return;}}document.getElementById('draft-results')?.scrollIntoView({block:'start'});});}
</script>
@push('styles')
<style>#draft-results,.draft-round-header{scroll-margin-top:170px}.draft-season-header td{padding:18px 20px!important;background:rgba(205,214,228,.7);font-size:19px;font-weight:900;letter-spacing:.3px;color:var(--text,#142238);border-top:3px solid var(--line,#d4dbe5);border-bottom:1px solid var(--line,#d4dbe5)}.draft-season-header:first-child td{border-top:0}.draft-round-header td{padding:12px 20px!important;background:rgba(225,232,242,.45);font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:.7px;color:var(--muted,#687486);border-bottom:1px solid var(--line,#dce2ea)}.draft-number{display:inline-flex;align-items:center;justify-content:center;width:36px;height:36px;border-radius:50%;background:rgba(225,232,242,.55);font-weight:800;line-height:1}.draft-number-secondary{font-weight:700}.draft-leader-cards .leader-card{overflow:hidden}.draft-leader-cards .leader-card-head{display:flex!important;align-items:center;justify-content:space-between;width:100%!important;margin:0!important;padding:17px 18px!important;background:var(--panel-2)!important;border-bottom:1px solid var(--line)!important}.draft-leader-cards .leader-card-head .leader-card-title{margin:0!important;padding:0 42px 0 0!important;background:transparent!important;border:0!important}.expand-card-icon{border:0;background:none;color:var(--text,#142238);font:inherit;font-size:21px;line-height:1;cursor:pointer;padding:4px 7px}.expand-card-icon:hover{opacity:.65}.franchise-link,.franchise-link:hover{text-decoration:none}@media(max-width:700px){#draftTable th.num,#draftTable td.num{width:54px;padding-left:8px;padding-right:8px}.draft-number{width:34px;height:34px;font-size:14px}}</style>
@endpush
@endpush