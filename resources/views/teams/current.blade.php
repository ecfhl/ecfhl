@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026-27 roster</div><h1>{{ $teamName }}</h1><p>Current Fantrax roster for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}.</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>
<div class="shell current-team-page">
  <div class="team-date-buttons">
    <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $today }}">Today</a>
    <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $tomorrow }}">Tomorrow</a>
  </div>

  @php($hasRows=collect($positions)->sum(fn($g)=>$g['rows']->count())>0)
  @if(!$hasRows)
    <div class="card"><h2>No roster data yet</h2><p class="subtle">Run the Fantasy Team Rosters collector from Collector Status to populate this team.</p><a class="button primary" href="/job-status">Collector Status</a></div>
  @else
    @foreach($positions as $code=>$group)
      <section class="team-position-section">
        <div class="section-title"><h2>{{ $group['label'] }}</h2><span class="subtle">{{ $group['rows']->count() }} players</span></div>
        <div class="table-card"><div class="table-scroll"><table class="data-table team-roster-table">
          <thead><tr><th>Player</th><th>Opponent</th><th>Roster</th><th>Line</th><th>PP</th><th class="num">Proj. FPts</th></tr></thead>
          <tbody>
          @foreach($group['rows'] as $player)
            <tr class="{{ empty($player->opponent)?'team-not-playing':'' }}">
              <td data-label="Player"><strong>{{ $player->player_name }} @if($player->nhl_team)({{ $player->nhl_team }})@endif</strong>@if($player->is_ir)<span class="pill team-ir">IR</span>@endif</td>
              <td data-label="Opponent">@if($player->opponent)<span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}</span>@else<span class="subtle">Not playing</span>@endif</td>
              <td data-label="Roster">@if($player->is_bench)<span class="pill team-bench">Bench</span>@elseif(strtoupper((string)$player->roster_status)==='MINORS')<span class="pill team-minors">MIN</span>@else<span class="pill team-active">Active</span>@endif</td>
              <td data-label="Line">@if($player->line_number)<span class="pill line-{{ $player->line_number }}">L{{ $player->line_number }}</span>@else<span class="subtle">—</span>@endif</td>
              <td data-label="PP">@if($player->pp_unit===1)<span class="pill pp1">PP1</span>@elseif($player->pp_unit===2)<span class="pill pp2">PP2</span>@else<span class="subtle">—</span>@endif</td>
              <td data-label="Proj. FPts" class="num"><strong>{{ $player->projected_fpts!==null?number_format($player->projected_fpts,0):'—' }}</strong></td>
            </tr>
          @endforeach
          </tbody>
        </table></div></div>
      </section>
    @endforeach
  @endif
</div>
<style>
.team-updated{font-size:12px;opacity:.8;margin-top:5px}.team-date-buttons{display:flex;gap:6px;margin:18px 0}.team-date-buttons .button{padding:6px 10px;font-size:12px}.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.team-position-section{margin:22px 0}.team-roster-table td,.team-roster-table th{padding:8px 10px}.team-roster-table td:first-child{white-space:normal}.team-not-playing{opacity:.62}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626;margin-left:6px}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.team-active{background:#dcfce7;color:#166534;border-color:#86efac}.line-1,.pp1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}
@media(max-width:700px){.team-roster-table,.team-roster-table tbody{display:block}.team-roster-table thead{display:none}.team-roster-table tr{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px 12px;padding:11px;border-bottom:1px solid var(--line)}.team-roster-table td{border:0!important;padding:0!important}.team-roster-table td::before{display:none}.team-roster-table td[data-label="Player"]{grid-column:1}.team-roster-table td[data-label="Proj. FPts"]{grid-column:2;grid-row:1;text-align:right}.team-roster-table td[data-label="Opponent"]{grid-column:1}.team-roster-table td[data-label="Roster"],.team-roster-table td[data-label="Line"],.team-roster-table td[data-label="PP"]{display:inline-block}.team-roster-table .num{text-align:right}}
</style>
@endsection
