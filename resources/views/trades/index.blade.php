@extends('layouts.app')
@section('title','Trades · ECFHL')
@section('content')
<div class="shell">
  <div class="page-head"><div class="eyebrow">Transaction archive</div><h1>Trades</h1><p>Search recorded ECFHL trades by season, franchise or player.</p></div>

  <div class="grid-3 leader-cards" style="margin-bottom:22px">
    @foreach([['trade-traders','🔄 Top Traders',$topTraders,'No recorded trades.'],['trade-partners','🤝 Top Trade Partners',$topTradePartners,'No recorded trade partners.'],['trade-firsts','1️⃣ 1st Round Picks Traded',$topFirstRoundTraders,'No recorded first-round picks traded.']] as [$id,$title,$rows,$empty])
    @php $rows = array_slice($rows,0,15); @endphp
    <article class="card leader-card" data-expand-card>
      <div class="leader-card-head">
        <h3 class="leader-card-title">{{ $title }}</h3>
        @if(count($rows)>3)
        <button type="button" class="leader-expand-button" data-expand-target="{{ $id }}" aria-expanded="false" aria-label="Expand {{ strip_tags($title) }}" title="Expand"><span class="expand-icon" aria-hidden="true">⛶</span><span class="minimize-icon" aria-hidden="true" hidden>✕</span></button>
        @endif
      </div>
      <div id="{{ $id }}">
      @forelse($rows as $i=>$row)<div class="expand-row" @if($i>=3) hidden @endif>@include('partials.leader-row')</div>@empty<div class="empty">{{ $empty }}</div>@endforelse
      </div>
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
document.querySelectorAll('.leader-expand-button').forEach(btn=>btn.addEventListener('click',()=>{const box=document.getElementById(btn.dataset.expandTarget),expanded=btn.getAttribute('aria-expanded')==='true';box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=expanded?i>=3:false);btn.setAttribute('aria-expanded',expanded?'false':'true');btn.title=expanded?'Expand':'Minimize';btn.querySelector('.expand-icon').hidden=!expanded;btn.querySelector('.minimize-icon').hidden=expanded;}));
</script>
<style>
.leader-card-head{position:relative}.leader-card-head .leader-card-title{padding-right:42px}.leader-expand-button{position:absolute;top:50%;right:14px;transform:translateY(-50%);display:grid;place-items:center;width:30px;height:30px;padding:0;border:0;border-radius:7px;background:transparent;color:inherit;font:inherit;font-size:18px;line-height:1;cursor:pointer}.leader-expand-button:hover{background:rgba(127,127,127,.12)}.leader-expand-button:focus-visible{outline:2px solid var(--accent,#1d5fa7);outline-offset:2px}.franchise-link,.franchise-link:hover{text-decoration:none}
</style>
@endpush