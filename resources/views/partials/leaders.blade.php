@php $leaderLimit = $limit ?? 5; @endphp
<div class="grid-3 leader-cards">
@foreach($cards as $key=>$title)
<article class="card leader-card">
<h3 class="leader-card-title">{{ $title }}</h3>
@forelse(array_slice($leaderRows[$key]??[],0,$leaderLimit) as $i=>$row)
@include('partials.leader-row')
@empty<div class="empty">No recorded results for this selection.</div>@endforelse
@if(count($leaderRows[$key]??[])>$leaderLimit)
<details class="leader-more"><summary><span class="when-closed">View all {{ count($leaderRows[$key]) }} results</span><span class="when-open">Show fewer results</span></summary>
@foreach(array_slice($leaderRows[$key],$leaderLimit,null,true) as $i=>$row)@php $i += $leaderLimit; @endphp @include('partials.leader-row')@endforeach
</details>
@endif
</article>
@endforeach
</div>