@php($hay=strtolower(($t['from']??'').' '.($t['to']??'').' '.implode(' ',$t['from_items']??[]).' '.implode(' ',$t['to_items']??[])))
<article class="card trade-item {{ !empty($t['vetoed'])?'trade-vetoed':'' }}" data-season="{{ $t['season'] }}" data-search="{{ $hay }}" data-teams="{{ strtolower(implode('|',$t['filter_teams']??[])) }}">
@if(!empty($t['vetoed']))<span class="veto-label">Vetoed</span>@endif
<div class="trade-sides">
<div><strong>{{ $t['from'] }} sent</strong><ul>@foreach($t['from_items'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
<div><strong>{{ $t['to'] }} sent</strong><ul>@foreach($t['to_items'] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
</div>
<time class="subtle trade-date" datetime="{{ $t['datetime']??'' }}">{{ $t['date'] }}</time>
</article>