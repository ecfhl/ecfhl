@php
    $hay=strtolower(($t['from']??'').' '.($t['to']??'').' '.implode(' ',$t['from_items']??[]).' '.implode(' ',$t['to_items']??[]));
    $sortTradeItems = function ($items) {
        return collect($items)->sortBy(function ($item, $index) {
            $isPick = preg_match('/\b(?:draft\s+pick|pick\s+\d+|round\s+\d+)\b/i', $item) ? 1 : 0;
            return sprintf('%d-%06d', $isPick, $index);
        })->values()->all();
    };
    $fromItems = $sortTradeItems($t['from_items'] ?? []);
    $toItems = $sortTradeItems($t['to_items'] ?? []);
@endphp
<article class="card trade-item {{ !empty($t['vetoed'])?'trade-vetoed':'' }}" data-franchises="{{ implode('|',array_filter([$t['from_id']??null,$t['to_id']??null])) }}" data-season="{{ $t['season'] }}" data-search="{{ $hay }}" data-teams="{{ strtolower(implode('|',$t['filter_teams']??[])) }}">
@if(!empty($t['vetoed']))<span class="veto-label">Vetoed</span>@endif
<div class="trade-sides">
<div><strong>{{ $t['from'] }} sent</strong><ul class="trade-assets">@foreach($fromItems as $item)@php($isPick=preg_match('/\b(?:draft\s+pick|pick\s+\d+|round\s+\d+)\b/i',$item))<li class="{{ $isPick?'trade-asset-pick':'trade-asset-player' }}">@include('partials.trade-asset',['item'=>$item])</li>@endforeach</ul></div>
<div><strong>{{ $t['to'] }} sent</strong><ul class="trade-assets">@foreach($toItems as $item)@php($isPick=preg_match('/\b(?:draft\s+pick|pick\s+\d+|round\s+\d+)\b/i',$item))<li class="{{ $isPick?'trade-asset-pick':'trade-asset-player' }}">@include('partials.trade-asset',['item'=>$item])</li>@endforeach</ul></div>
</div>
<time class="subtle trade-date" datetime="{{ $t['datetime']??'' }}">{{ $t['date'] }}</time>
</article>
@once
<style>
.trade-assets{list-style:none;padding-left:1.2rem}
.trade-assets li{position:relative}
.trade-assets li::before{position:absolute;right:calc(100% + .55rem);font-weight:800}
.trade-asset-player::before{content:'•';font-size:1.15em;line-height:1.2}
.trade-asset-pick::before{content:'◆';font-size:.62em;line-height:2.05}
</style>
@endonce
