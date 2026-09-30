@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026-27 rosters</div><h1>Teams</h1><p>Current fantasy rosters for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}.</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>
<div class="shell current-teams-page">
  <div class="team-date-buttons">
    <a class="button {{ $date===$today?'primary':'secondary' }}" href="/teams/current?date={{ $today }}">Today</a>
    <a class="button {{ $date===$tomorrow?'primary':'secondary' }}" href="/teams/current?date={{ $tomorrow }}">Tomorrow</a>
  </div>

  <div class="current-team-grid">
    @foreach($teams as $team)
      <details class="card current-team-card">
        <summary>
          <span><strong>{{ $team['name'] }}</strong><small>{{ $team['count'] }} players</small></span>
          <a class="team-open-link" href="/teams/current/{{ $team['slug'] }}?date={{ $date }}" onclick="event.stopPropagation()">Open team →</a>
        </summary>
        <div class="current-team-roster">
          @foreach($team['positions'] as $code=>$group)
            @if($group['rows']->count())
              <section class="current-position-group">
                <h3>{{ $group['label'] }}</h3>
                @foreach([1=>'Playing',0=>'Not Playing'] as $playingFlag=>$playingLabel)
                  @php($statusRows=$group['rows']->filter(fn($p)=>(!empty($p->opponent)?1:0)===$playingFlag))
                  @if($statusRows->count())
                    <div class="current-playing-label">{{ $playingLabel }}</div>
                    @foreach($statusRows as $player)
                      <div class="current-player-row {{ !$playingFlag?'not-playing':'' }}">
                        <div class="current-player-main">
                          <div class="current-player-name">
                            <strong>{{ $player->player_name }} @if($player->nhl_team)({{ $player->nhl_team }})@endif</strong>
                            <span class="current-badges">
                              @if($player->is_ir)<span class="pill team-ir">IR</span>@endif
                              @if($player->is_bench)<span class="pill team-bench">BE</span>@endif
                              @if(strtoupper((string)$player->roster_status)==='MINORS')<span class="pill team-minors">MIN</span>@endif
                              @if($player->line_number && $player->line_number>=1 && $player->line_number<=4)<span class="pill line-{{ $player->line_number }}">L{{ $player->line_number }}</span>@endif
                              @if($player->pp_unit===1)<span class="pill pp1">PP1</span>@elseif($player->pp_unit===2)<span class="pill pp2">PP2</span>@endif
                            </span>
                          </div>
                          <div class="current-player-opponent">
                            @if($player->opponent)<span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}</span>@else<span class="subtle">No game</span>@endif
                          </div>
                        </div>
                        <div class="current-player-points"><span>Proj. FPts</span><strong>{{ $player->projected_fpts!==null?number_format($player->projected_fpts,0):'—' }}</strong></div>
                      </div>
                    @endforeach
                  @endif
                @endforeach
              </section>
            @endif
          @endforeach
        </div>
      </details>
    @endforeach
  </div>
</div>
<style>
.team-updated{font-size:12px;opacity:.8;margin-top:5px}.team-date-buttons{display:flex;gap:6px;margin:18px 0}.team-date-buttons .button{padding:6px 10px;font-size:12px}.current-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:28px}.current-team-card{padding:0;overflow:hidden}.current-team-card summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;cursor:pointer;list-style:none}.current-team-card summary::-webkit-details-marker{display:none}.current-team-card summary>span{display:flex;flex-direction:column;gap:3px}.current-team-card summary small{font-size:11px;color:var(--muted);font-weight:500}.team-open-link{font-size:12px;font-weight:800;text-decoration:none}.current-team-roster{border-top:1px solid var(--line);padding:0 14px 14px}.current-position-group h3{margin:14px 0 7px;font-size:15px}.current-playing-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:800;margin:9px 0 5px}.current-player-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid var(--line)}.current-player-row.not-playing{opacity:.62}.current-player-main{min-width:0}.current-player-name{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:13px}.current-badges{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}.current-player-opponent{font-size:11px;margin-top:3px}.current-player-points{text-align:right;flex:0 0 auto}.current-player-points span{display:block;color:var(--muted);font-size:9px;font-weight:700}.current-player-points strong{font-size:17px}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.line-1,.pp1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}
@media(max-width:800px){.current-team-grid{grid-template-columns:1fr}.current-team-card summary{padding:14px}.current-team-roster{padding:0 12px 12px}.current-player-name{font-size:12px}.current-player-points strong{font-size:16px}}
</style>
@endsection
