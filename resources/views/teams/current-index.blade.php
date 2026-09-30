@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026-27 rosters</div><h1>Teams</h1><p>Current fantasy rosters for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}.</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>
<div class="shell current-teams-page">
  <div class="team-toolbar">
    <div class="team-date-buttons">
      <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $today }}">Today</a>
      <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $tomorrow }}">Tomorrow</a>
    </div>
  </div>

  <div class="current-team-grid">
    @foreach($teams as $team)
      @php
        $playingPlayers=collect($team['positions'])
          ->flatMap(fn($group)=>$group['rows'])
          ->filter(fn($player)=>(bool)$player->is_playing)
          ->sortBy(fn($player)=>(bool)$player->is_ir?1:0)
          ->values();
      @endphp
      <details class="card current-team-card">
        <summary>
          <span class="current-team-heading">
            <a class="current-team-name" href="/teams/current/{{ $team['slug'] }}?date={{ $date }}" onclick="event.stopPropagation()">{{ $team['name'] }}</a>
            <small>{{ $team['count'] }} players</small>
          </span>
          <span class="current-team-total"><small>Today</small><strong>{{ number_format($team['today_fpts'] ?? 0, 1) }}</strong></span>
        </summary>
        <div class="current-team-roster">
          @forelse($playingPlayers as $player)
            <div class="current-player-row {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }}">
              <div class="current-player-main">
                <div class="current-player-name">
                  @if($player->is_ir)<span class="pill team-ir">IR</span>@endif
                  <strong>{{ $player->player_name }} @if($player->nhl_team)({{ $player->nhl_team }})@endif</strong>
                  <span class="current-badges">
                    @if($player->is_bench)<span class="pill team-bench">Bench</span>@endif
                    @if(!empty($player->contract_label))<span class="pill team-contract-sticker {{ $player->contract_class }}">{{ $player->contract_label }}</span>@endif
                    @if($player->line_number)
                      @if(strtoupper((string)$player->position)==='G' && $player->line_number<=2)<span class="pill goalie-{{ $player->line_number }}">G{{ $player->line_number }}</span>@if($player->vegas_odds!==null)<span class="pill goalie-vegas-odds {{ $player->vegas_odds_class }}">{{ $player->vegas_odds>0?'+':'' }}{{ $player->vegas_odds }}</span>@endif
                      @elseif($player->line_number<=4)<span class="pill line-{{ $player->line_number }}">L{{ $player->line_number }}</span>@endif
                    @endif
                    @if($player->pp_unit===1)<span class="pill pp1">PP1</span>@elseif($player->pp_unit===2)<span class="pill pp2">PP2</span>@endif
                  </span>
                </div>
                <div class="current-player-opponent">
                  @if($player->opponent)<span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($player->game_time) · {{ $player->game_time }}@endif</span>@else<span class="team-playing-text">Playing</span>@endif
                </div>
              </div>
              <div class="current-player-metrics">
                <div class="current-player-points"><span>Proj.</span><strong>{{ $player->projected_fpts!==null?number_format($player->projected_fpts,0):'—' }}</strong></div>
                <div class="current-player-today"><span>Today</span><strong>{{ number_format($player->today_fpts ?? 0, 1) }}</strong></div>
              </div>
            </div>
          @empty
            <div class="current-team-empty">No players playing today.</div>
          @endforelse
        </div>
      </details>
    @endforeach
  </div>
</div>
<style>
.team-updated{font-size:12px;opacity:.8;margin-top:5px}.team-toolbar{display:flex;flex-direction:column;align-items:flex-start;gap:8px;margin:18px 0}.team-date-buttons{display:flex;gap:6px;margin:18px 0}.team-date-buttons .button{padding:6px 10px;font-size:12px}.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.team-not-playing-toggle{cursor:pointer}.team-not-playing-button{appearance:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:900;text-transform:uppercase;letter-spacing:.06em;padding:0;cursor:pointer;display:inline-flex;align-items:center;gap:6px}.team-not-playing-button:hover{text-decoration:underline}.team-not-playing-chevron{font-size:11px}.current-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:28px}.current-team-card{padding:0;overflow:hidden}.current-team-card summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;cursor:pointer;list-style:none}.current-team-card summary::-webkit-details-marker{display:none}.current-team-heading{display:flex;flex-direction:column;gap:3px}.current-team-card summary small{font-size:11px;color:var(--muted);font-weight:500}.current-team-name{font-weight:900;color:var(--text);text-decoration:none}.current-team-name:hover{text-decoration:underline}.current-team-total{display:flex;flex-direction:column;align-items:flex-end;gap:1px;flex:0 0 auto}.current-team-total strong{font-size:20px;line-height:1}.current-team-total small{font-size:9px;text-transform:uppercase;letter-spacing:.05em;font-weight:800}.current-team-empty{padding:12px 0;color:var(--muted);font-size:12px}.current-team-roster{border-top:1px solid var(--line);padding:0 14px 14px}.current-player-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid var(--line)}.current-player-row.not-playing{opacity:.62}.current-player-row.team-bench-row{background:#f3f4f6;margin-left:-12px;margin-right:-12px;padding-left:12px;padding-right:12px}.current-player-row.team-ir-row{background:#fff1f2;margin-left:-12px;margin-right:-12px;padding-left:12px;padding-right:12px}.current-player-main{min-width:0}.current-player-name{display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:13px}.current-player-name .pill{padding:2px 5px!important;font-size:10px!important;line-height:1.1}.current-badges{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}.current-player-opponent{font-size:11px;margin-top:3px}.current-player-metrics{display:flex;align-items:center;gap:14px;text-align:right;flex:0 0 auto}.current-player-metrics>div>span{display:block;color:var(--muted);font-size:9px;font-weight:700}.current-player-metrics>div>strong{font-size:13px}.current-player-points,.current-player-today{text-align:right}.current-player-points span,.current-player-today span{display:block;color:var(--muted);font-size:9px;font-weight:700}.current-player-points strong{font-size:14px}.current-player-today strong{font-size:17px}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626;padding:2px 5px!important;font-size:9px!important;line-height:1}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.team-contract-sticker{font-weight:800;padding:3px 7px}.contract-green{background:#dcfce7;color:#166534;border-color:#86efac}.contract-yellow{background:#fef3c7;color:#92400e;border-color:#fcd34d}.contract-red{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.goalie-vegas-odds{font-weight:900}.vegas-odds-good{background:#dcfce7;color:#166534;border-color:#86efac}.vegas-odds-even{background:#fef3c7;color:#92400e;border-color:#fcd34d}.vegas-odds-bad{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.line-1,.pp1,.goalie-1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2,.goalie-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}.team-playing-text{color:#15803d;font-weight:800}
html[data-theme="dark"] .current-playing-label[data-status="playing"]{background:#15365f;color:#dbeafe}
html[data-theme="dark"] .current-player-row.team-ir-row{background:#3a1f26;color:#f8e7eb}
html[data-theme="dark"] .current-player-row.team-bench-row{background:#1d2735}
html[data-theme="dark"] .current-player-row.not-playing{opacity:.78}
html[data-theme="dark"] .team-home{color:#4ade80}
html[data-theme="dark"] .team-away{color:#fbbf24}
@media(max-width:800px){.current-team-grid{grid-template-columns:1fr}.current-team-card summary{padding:14px}.current-team-roster{padding:0 12px 12px}.current-player-name{font-size:12px}.current-player-points strong{font-size:16px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.current-team-name').forEach(link=>{
    link.addEventListener('click',event=>event.stopPropagation());
  });
});
</script>
@endsection
