@extends('layouts.app')
@section('title','Prizes · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">League prizes</div><h1>Prizes</h1><p>Historical awards and winnings for the selected season types.</p></div>
<section class="section"><div class="section-title"><h2>All-time leaders</h2></div>
@include('partials.leaders',['leaderRows'=>['champions'=>$leaders['championships'],'earners'=>$seasonLeaders['top_earners'],'top_pick'=>$leaders['top_pick']],'cards'=>['champions'=>'🏆 Champions','earners'=>'💵 Single season top earner','top_pick'=>'🎯 Top Pick Winner']])
</section>
@php($awardTypes = collect($awardEvents)->pluck('label','id')->all())
<div class="toolbar award-filters" role="group" aria-label="Award type">
<button type="button" class="filter-button active" data-award="all" aria-pressed="true">All awards</button>
@foreach($awardTypes as $id=>$label)<button type="button" class="filter-button" data-award="{{ $id }}" aria-pressed="false">{{ $label }}</button>@endforeach
</div>
<div class="prizes-grid">
<section><div class="section-title"><h2>Awards and Total Winnings</h2></div>
<div class="table-card"><table class="data-table winnings-table"><thead><tr><th>Team</th><th class="num">Paid awards</th><th class="num">Winnings</th></tr></thead><tbody>
@forelse($totals as $r)<tr><td><a href="/teams/{{ $r['franchise_id'] }}"><strong>{{ $r['franchise_name'] }}</strong></a></td><td class="num">{{ $r['awards'] }}</td><td class="num">${{ number_format($r['total_cents']/100,2) }}</td></tr>@empty<tr><td colspan="3">No recorded winnings.</td></tr>@endforelse
</tbody></table></div></section>
<section><div class="section-title"><h2>Awards by season</h2></div><div class="season-list">
@forelse(collect($awardEvents)->groupBy('season') as $year=>$items)
<article class="card award-season"><h3><a href="/seasons/{{ rawurlencode($year) }}">{{ $year }}</a></h3>
@foreach($items as $a)
<div class="award-entry" data-award-type="{{ $a['id'] }}">
<span class="award-icon">{{ \App\Support\AwardIcon::for($a['id']) }}</span>
<div><strong>{{ $a['label'] }}</strong><div>{{ $a['player'] ? $a['player'].' · ' : '' }}{{ $a['team'] }}</div></div>
</div>
@endforeach
</article>
@empty<div class="empty">No recorded awards.</div>@endforelse
<p id="noAwards" class="empty" hidden>No awards of this type in the selected seasons.</p>
</div></section>
</div></div>
@endsection
@push('scripts')
<script>
document.querySelectorAll('[data-award]').forEach(button=>button.addEventListener('click',()=>{
 document.querySelectorAll('[data-award]').forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',b===button?'true':'false');});
 document.querySelectorAll('[data-award-type]').forEach(e=>e.hidden=button.dataset.award!=='all'&&e.dataset.awardType!==button.dataset.award);
 document.querySelectorAll('.award-season').forEach(e=>e.hidden=![...e.querySelectorAll('[data-award-type]')].some(a=>!a.hidden));
 document.getElementById('noAwards').hidden=[...document.querySelectorAll('.award-season')].some(e=>!e.hidden);
}));
</script>
@endpush
