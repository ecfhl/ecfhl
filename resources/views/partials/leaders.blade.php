@php
    $leaderLimit = $limit ?? 3;
    $expandedLimit = 15;
@endphp
<div class="grid-3 leader-cards">
@foreach($cards as $key=>$title)
@php $rows = array_slice($leaderRows[$key] ?? [], 0, $expandedLimit); @endphp
<article class="card leader-card" data-expand-card>
<div class="leader-card-head">
<h3 class="leader-card-title">{{ $title }}</h3>
@if(count($rows) > $leaderLimit)
<button type="button" class="leader-expand-button" data-expand-target="leader-{{ $key }}" data-limit="{{ $leaderLimit }}" aria-expanded="false" aria-label="Expand {{ strip_tags($title) }}" title="Expand">
<span class="expand-icon" aria-hidden="true">⛶</span><span class="minimize-icon" aria-hidden="true" hidden>✕</span>
</button>
@endif
</div>
<div id="leader-{{ $key }}">
@forelse($rows as $i=>$row)
<div class="expand-row" @if($i >= $leaderLimit) hidden @endif>
@include('partials.leader-row')
</div>
@empty
<div class="empty">No recorded results for this selection.</div>
@endforelse
</div>
</article>
@endforeach
</div>

@once
@push('scripts')
<script>
document.querySelectorAll('.leader-expand-button').forEach(btn=>btn.addEventListener('click',()=>{
    const box=document.getElementById(btn.dataset.expandTarget);
    const limit=parseInt(btn.dataset.limit||'3',10);
    const expanded=btn.getAttribute('aria-expanded')==='true';
    box.querySelectorAll('.expand-row').forEach((row,i)=>row.hidden=expanded ? i>=limit : false);
    btn.setAttribute('aria-expanded',expanded?'false':'true');
    btn.title=expanded?'Expand':'Minimize';
    btn.setAttribute('aria-label',expanded?'Expand card':'Minimize card');
    btn.querySelector('.expand-icon').hidden=!expanded;
    btn.querySelector('.minimize-icon').hidden=expanded;
}));
</script>
<style>
.leader-cards{align-items:stretch}
.leader-cards .leader-card{display:flex;flex-direction:column}
.leader-card-head{position:relative}
.leader-card-head .leader-card-title{padding-right:42px}
.leader-expand-button{position:absolute;top:50%;right:14px;transform:translateY(-50%);display:grid;place-items:center;width:30px;height:30px;padding:0;border:0;border-radius:7px;background:transparent;color:inherit;font:inherit;font-size:18px;line-height:1;cursor:pointer}
.leader-expand-button:hover{background:rgba(127,127,127,.12)}
.leader-expand-button:focus-visible{outline:2px solid var(--accent,#1d5fa7);outline-offset:2px}
.franchise-link,.franchise-link:hover{text-decoration:none}
</style>
@endpush
@endonce