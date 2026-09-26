@php $leaderLimit = $limit ?? 3; @endphp
<div class="grid-3 leader-cards">
@foreach($cards as $key=>$title)
<article class="card leader-card" data-expand-card>
<h3 class="leader-card-title">{{ $title }}</h3>
<div id="leader-{{ $key }}">
@forelse(($leaderRows[$key]??[]) as $i=>$row)
<div class="expand-row" @if($i >= $leaderLimit) hidden @endif>
@include('partials.leader-row')
</div>
@empty
<div class="empty">No recorded results for this selection.</div>
@endforelse
</div>
@if(count($leaderRows[$key]??[]) > $leaderLimit)
<button type="button" class="expand-card-link" data-expand-target="leader-{{ $key }}" data-limit="{{ $leaderLimit }}">View all</button>
@endif
</article>
@endforeach
</div>

@once
@push('scripts')
<script>
document.querySelectorAll('.expand-card-link').forEach(btn=>btn.addEventListener('click',()=>{
    const box=document.getElementById(btn.dataset.expandTarget);
    const limit=parseInt(btn.dataset.limit||'3',10);
    const hiddenRows=box.querySelector('.expand-row[hidden]');
    if(!hiddenRows){
        box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=i>=limit);
        btn.textContent='View all';
        return;
    }
    box.querySelectorAll('.expand-row').forEach(r=>r.hidden=false);
    btn.textContent='Show top '+limit;
}));
</script>
<style>
.leader-cards{align-items:stretch}
.leader-cards .leader-card{display:flex;flex-direction:column}
.expand-card-link{display:block;margin:auto auto 0;padding:14px 0 0;border:0;background:none;color:var(--accent,#1d5fa7);font:inherit;font-weight:700;cursor:pointer}
.expand-card-link:hover{text-decoration:underline}
.franchise-link,.franchise-link:hover{text-decoration:none}
</style>
@endpush
@endonce