@php $leaderLimit = $limit ?? 5; @endphp
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
@if(count($leaderRows[$key]??[])>$leaderLimit)
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
    const limit=parseInt(btn.dataset.limit||'5',10);
    const opening=box.querySelector('.expand-row[hidden]')!==null;
    box.querySelectorAll('.expand-row').forEach((r,i)=>r.hidden=!opening&&i>=limit);
    btn.textContent=opening?'Show top '+limit:'View all';
}));
</script>
<style>
.expand-card-link{display:block;margin:14px auto 0;padding:0;border:0;background:none;color:var(--accent,#1d5fa7);font:inherit;font-weight:700;cursor:pointer}
.expand-card-link:hover{text-decoration:underline}
</style>
@endpush
@endonce