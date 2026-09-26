@extends('layouts.app')
@section('title','Trades · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">Transaction archive</div><h1>Trades</h1><p>Search recorded ECFHL trades by season, franchise or player.</p></div>

  <div class="grid-3 leader-cards" style="margin-bottom:22px">
    @foreach([['trade-traders','🔄 Top Traders',$topTraders,'No recorded trades.'],['trade-partners','🤝 Top Trade Partners',$topTradePartners,'No recorded trade partners.'],['trade-firsts','1️⃣ 1st Round Picks Traded',$topFirstRoundTraders,'No recorded first-round picks traded.']] as [$id,$title,$rows,$empty])
    <article class="card leader-card" data-expand-card>
      <h3 class="leader-card-title">{{ $title }}</h3>
      <div id="{{ $id }}">
      @forelse($rows as $i=>$row)<div class="expand-row" @if($i>=5) hidden @endif>@include('partials.leader-row')</div>@empty<div class="empty">{{ $empty }}</div>@endforelse
      </div>
      @if(count($rows)>5)<button type="button" class="expand-card-link" data-expand-target="{{ $id }}">View all</button>@endif
    </article>
    @endforeach
  </div>

  <div class="toolbar">
    <select id="tradeSeason" class="control"><option value="">All seasons</option>@foreach($seasons as $s)<option value="{{ $s }}" @selected($selectedSeason===$s)>{{ $s }}</option>@endforeach</select>
    <select id="tradeFranchise" class="control" aria-label="Franchise"><option value="">All franchises</option>@foreach($franchises as $id=>$name)<option value="{{ $id }}" @selected($selectedFranchise===$id)>{{ $name }}</option>@endforeach</select>
    <input id="tradeSearch" class="control" style="flex:1;min-width:220px" placeholder="Search player or draft pick…">
  </div>
  <div id="tradeList" class="season-list">@foreach($trades as $t)@include('partials.trade-card')@endforeach</div>
</div>
@endsection
@push('scripts')
<script>
const q=document.getElementById('tradeSearch'),s=document.getElementById('tradeSeason'),t=document.getElementById('tradeFranchise');
function filterTrades(){const n=q.value.toLowerCase(),franchise=t.value;document.querySelectorAll('.trade-item').forEach(e=>{const seasonOk=!s.value||e.dataset.season===s.value;const franchiseOk=!franchise||e.dataset.franchises.split('|').includes(franchise);const searchOk=!n||e.dataset.search.includes(n);e.style.display=(seasonOk&&franchiseOk&&searchOk)?'':'none';});}
q.addEventListener('input',filterTrades);s.addEventListener('change',filterTrades);t.addEventListener('change',filterTrades);filterTrades();
document.querySelectorAll('.expand-card-link').forEach(btn=>btn.addEventListener('click',()=>{const box=document.getElementById(btn.dataset.expandTarget),opening=box.querySelector('.expand-row[hidden]')!==null;box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=!opening&&i>=5);btn.textContent=opening?'Show top 5':'View all';}));
</script>
<style>.expand-card-link{display:block;margin:14px auto 0;padding:0;border:0;background:none;color:var(--accent,#1d5fa7);font:inherit;font-weight:700;cursor:pointer}.expand-card-link:hover{text-decoration:underline}</style>
@endpush
