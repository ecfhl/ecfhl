@extends('layouts.app')
@section('title','Franchises · ECFHL')
@section('content')
<div class="shell">
<div class="page-head"><div class="eyebrow">Franchise ledger</div><h1>📖 Franchises</h1><p>All-time franchise performance across the ECFHL archive.</p></div>
<div class="filter-bar" style="justify-content:space-between;flex-wrap:wrap">
<div class="filter-group">
<a class="filter-button {{ $status==='active'?'active':'' }}" href="?type={{ $type }}&status=active">Active</a>
<a class="filter-button {{ $status==='inactive'?'active':'' }}" href="?type={{ $type }}&status=inactive">Inactive</a>
<a class="filter-button {{ $status==='all'?'active':'' }}" href="?type={{ $type }}&status=all">All</a>
</div>
<form method="get"><input type="hidden" name="type" value="{{ $type }}"><input type="hidden" name="status" value="{{ $status }}"><select name="go" aria-label="Franchise" onchange="this.form.action=this.value;this.form.submit()" class="control"><option value="">Franchise…</option>@foreach($allTeams as $f)<option value="/teams/{{ \Illuminate\Support\Str::slug($f['team']) }}">{{ $f['team'] }}</option>@endforeach</select></form>
</div>
<section class="section" style="padding-top:0"><div class="section-title"><h2>Franchise leaders</h2></div>@include('partials.leaders',['leaderRows'=>$franchiseLeaders,'cards'=>['championships'=>'🏆 Champions','presidents'=>'🏆 President Trophies','winning_pct'=>'📈 Winning %','first_picks'=>'1️⃣ #1 Overall Picks','trades'=>'🔄 Trades','awards'=>'🏅 Awards']])</section>
<div class="table-card"><div class="table-scroll"><table class="data-table sortable-table" id="teamsTable"><thead><tr><th>Franchise</th><th class="num">Seasons</th><th class="num">Champion</th><th class="num">2nd</th><th class="num">3rd</th><th class="num">President</th><th class="num">Fpts Leader</th><th class="num">Total Fpts</th><th class="num">Record</th><th class="num">Win %</th></tr></thead><tbody>
@foreach($teams as $t)<tr><td><a href="/teams/{{ \Illuminate\Support\Str::slug($t['team']) }}"><strong>{{ $t['team'] }}</strong></a><br><small class="pill {{ !empty($t['active'])?'status-active':'status-inactive' }}" style="margin-top:4px;padding:2px 7px">{{ !empty($t['active'])?'Active':'Inactive' }}</small></td><td class="num">{{ $t['seasons'] }}</td><td class="num">{{ $t['champion'] }}</td><td class="num">{{ $t['second'] }}</td><td class="num">{{ $t['third'] }}</td><td class="num">{{ $t['president'] }}</td><td class="num">{{ $t['fpts_leader'] }}</td><td class="num">{{ isset($t['total_fpts']) ? number_format($t['total_fpts'],0) : '—' }}</td><td class="num">{{ $t['games'] ? ($t['w'].'-'.$t['l'].'-'.$t['t']) : '—' }}</td><td class="num">{{ $t['win_pct']!==null ? number_format($t['win_pct']*100,1).'%' : '—' }}</td></tr>@endforeach
</tbody></table></div></div>
</div>
@endsection