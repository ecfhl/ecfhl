@extends('layouts.app')
@section('title','Prizes · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">League prizes</div><h1>Prizes</h1><p>Historical awards and winnings for the selected season types.</p></div>
@php
    $selectedSeasonNames = collect(app(\App\Support\Archive::class)->seasons())->pluck('season')->all();
    $singleSeasonEarners = $seasonLeaders['top_earners'];
@endphp
<section class="section"><div class="section-title"><h2>All-time leaders</h2></div>
@include('partials.leaders',['leaderRows'=>['champions'=>$leaders['championships'],'earners'=>$singleSeasonEarners,'top_pick'=>$leaders['first_picks'] ?? []],'cards'=>['champions'=>'🏆 Champions','earners'=>'💵 Single season top earner','top_pick'=>'🎯 Top Pick Winner']])
</section>
@php
    $awardLabels = [
        'champion' => 'Champion',
        'second' => '2nd Place',
        'third' => '3rd Place',
        'president' => "President's Trophy",
        'leader' => 'Fpts Leader',
        'top_pick' => 'Top Pick',
        'art_ross' => 'Art Ross',
        'norris' => 'Norris',
        'vezina' => 'Vezina',
        'calder' => 'Calder',
    ];
    $seasonAwardRows = collect();
    if ($selectedSeasonNames) {
        $seasonAwardRows = \Illuminate\Support\Facades\DB::table('awards as a')
            ->join('seasons as s','s.season_id','=','a.season_id')
            ->leftJoin('franchises as f','f.franchise_id','=','a.franchise_id')
            ->leftJoin('team_seasons as ts',function($join){$join->on('ts.season_id','=','a.season_id')->on('ts.franchise_id','=','a.franchise_id');})
            ->leftJoin('players as p','p.player_id','=','a.player_id')
            ->whereIn('s.season_name',$selectedSeasonNames)
            ->whereIn('a.award_type_id',array_keys($awardLabels))
            ->select('s.season_name','s.sequence','a.award_type_id','a.team_name_raw','f.franchise_name','ts.original_name','p.player_name')
            ->orderByDesc('s.sequence')
            ->orderByRaw("CASE a.award_type_id WHEN 'champion' THEN 1 WHEN 'second' THEN 2 WHEN 'third' THEN 3 WHEN 'president' THEN 4 WHEN 'leader' THEN 5 WHEN 'top_pick' THEN 6 WHEN 'art_ross' THEN 7 WHEN 'norris' THEN 8 WHEN 'vezina' THEN 9 WHEN 'calder' THEN 10 ELSE 11 END")
            ->get()
            ->map(function($r) use ($awardLabels){
                return ['season'=>$r->season_name,'id'=>$r->award_type_id,'award'=>$awardLabels[$r->award_type_id] ?? $r->award_type_id,'team'=>$r->original_name ?: ($r->team_name_raw ?: $r->franchise_name),'player'=>$r->player_name];
            });
    }
    $awardTypes = $seasonAwardRows->pluck('award','id')->all();
    $playerAwardLeaders = [];
    foreach(['art_ross','norris','vezina'] as $awardId){
        $playerAwardLeaders[$awardId] = $seasonAwardRows
            ->where('id',$awardId)->filter(fn($r)=>!empty($r['player']))->groupBy('player')
            ->map(fn($rows,$player)=>['team'=>$player,'value'=>$rows->count(),'score'=>$rows->count()])
            ->sort(function($a,$b){ return ($b['score'] <=> $a['score']) ?: strcasecmp($a['team'],$b['team']); })->values()->all();
    }
@endphp
<section class="section" style="padding-top:0"><div class="section-title"><h2>Player award leaders</h2></div>
@include('partials.leaders',['leaderRows'=>$playerAwardLeaders,'cards'=>['art_ross'=>'🏒 Art Ross','norris'=>'🛡️ Norris','vezina'=>'🥅 Vezina']])
</section>
<div class="toolbar award-filters" role="group" aria-label="Award type">
<button type="button" class="filter-button active" data-award="all" aria-pressed="true">All awards</button>
@foreach($awardLabels as $id=>$label)@if(array_key_exists($id,$awardTypes))<button type="button" class="filter-button" data-award="{{ $id }}" aria-pressed="false">{{ $label }}</button>@endif @endforeach
</div>
<div class="prizes-grid">
<section><div class="section-title"><h2>Awards and Total Winnings</h2></div><div class="table-card"><table class="data-table winnings-table"><thead><tr><th>Team</th><th class="num">Winnings</th><th class="num">Fees</th><th class="num">Net</th></tr></thead><tbody>
@forelse($totals as $r)<tr><td><strong>{{ ($r['team'] ?? '—') === 'Lone Tsar' ? 'Ꮮσոє⚡️𐌕รคг' : ($r['team'] ?? '—') }}</strong></td><td class="num">${{ number_format((float)($r['awards'] ?? 0),2) }}</td><td class="num">${{ number_format((float)($r['fees'] ?? 0),2) }}</td><td class="num">${{ number_format((float)($r['net'] ?? 0),2) }}</td></tr>@empty<tr><td colspan="4">No recorded winnings.</td></tr>@endforelse
</tbody></table></div></section>
<section><div class="section-title" style="display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap"><h2>Awards by season</h2><select id="awardSeasonFilter" aria-label="Season" style="min-width:150px"><option value="all">All Seasons</option>@foreach($seasonAwardRows->pluck('season')->unique()->values() as $year)<option value="{{ $year }}">{{ $year }}</option>@endforeach</select></div><div class="season-list">
@forelse($seasonAwardRows->groupBy('season') as $year=>$items)<article class="card award-season" data-season="{{ $year }}"><h3><a href="/seasons/{{ rawurlencode($year) }}">{{ $year }}</a></h3>@foreach($items as $a)<div class="award-entry" data-award-type="{{ $a['id'] }}"><span class="award-icon">{{ \App\Support\AwardIcon::for($a['id']) }}</span><div><strong>{{ $a['award'] }}</strong><div>{{ $a['team'] }}@if(!empty($a['player'])): @include('partials.player-link',['name'=>$a['player']])@endif</div></div></div>@endforeach</article>@empty<div class="empty">No recorded awards.</div>@endforelse
<p id="noAwards" class="empty" hidden>No awards matching the selected filters.</p></div></section>
</div></div>
@endsection
@push('scripts')
<script>
const awardButtons=[...document.querySelectorAll('[data-award]')];
const seasonFilter=document.getElementById('awardSeasonFilter');
function applyAwardFilters(){
    const activeAward=document.querySelector('[data-award].active')?.dataset.award||'all';
    const activeSeason=seasonFilter?.value||'all';
    document.querySelectorAll('.award-season').forEach(card=>{
        const seasonMatches=activeSeason==='all'||card.dataset.season===activeSeason;
        let hasVisibleAward=false;
        card.querySelectorAll('[data-award-type]').forEach(entry=>{
            const awardMatches=activeAward==='all'||entry.dataset.awardType===activeAward;
            entry.hidden=!awardMatches;
            if(awardMatches)hasVisibleAward=true;
        });
        card.hidden=!(seasonMatches&&hasVisibleAward);
    });
    document.getElementById('noAwards').hidden=[...document.querySelectorAll('.award-season')].some(card=>!card.hidden);
}
awardButtons.forEach(button=>button.addEventListener('click',()=>{
    awardButtons.forEach(b=>{b.classList.toggle('active',b===button);b.setAttribute('aria-pressed',b===button?'true':'false');});
    applyAwardFilters();
}));
seasonFilter?.addEventListener('change',applyAwardFilters);
</script>
@endpush