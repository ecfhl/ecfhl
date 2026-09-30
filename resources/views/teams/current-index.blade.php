@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026-27 rosters</div><h1>Teams</h1><p>Current fantasy rosters for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}.</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>
<div class="shell current-teams-page">
  <div class="team-toolbar">
    <div class="team-date-buttons">
      <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $today }}">Today</a>
      <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $tomorrow }}">Tomorrow</a>
    </div>
    <div class="team-status-slicer" role="group" aria-label="Roster status filter">
      @foreach(['playing'=>'Playing','active'=>'Active','bench'=>'Bench','injured'=>'Injured'] as $filterKey=>$filterLabel)
        <button type="button" class="team-status-button {{ $filterKey==='playing'?'active':'' }}" data-status-filter="{{ $filterKey }}" aria-pressed="{{ $filterKey==='playing'?'true':'false' }}">{{ $filterLabel }}</button>
      @endforeach
    </div>
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
                @foreach(['playing'=>'Playing','active'=>'Active','bench'=>'Bench','injured'=>'Injured'] as $groupKey=>$groupLabel)
                  @php($statusRows=$group['rows']->filter(function($p)use($groupKey){
                    $injured=(bool)$p->is_ir;
                    $bench=(bool)$p->is_bench;
                    $playing=(bool)$p->is_playing;
                    return match($groupKey){
                      'playing'=>$playing && !$injured && !$bench,
                      'active'=>!$playing && !$injured && !$bench,
                      'bench'=>$bench && !$injured,
                      'injured'=>$injured,
                      default=>false,
                    };
                  }))
                  @if($statusRows->count())
                    <div class="current-playing-label" data-status="{{ $groupKey }}">{{ $groupLabel }}</div>
                    @foreach($statusRows as $player)
                      <div class="current-player-row {{ $groupKey!=='playing'?'not-playing':'' }}" data-status="{{ $groupKey }}">
                        <div class="current-player-main">
                          <div class="current-player-name">
                            @if($player->is_ir)<span class="pill team-ir">IR</span>@endif
                            <strong>{{ $player->player_name }} @if($player->nhl_team)({{ $player->nhl_team }})@endif</strong>
                            <span class="current-badges">
                              @if($player->is_bench)<span class="pill team-bench">Bench</span>@endif
                              @if(strtoupper((string)$player->roster_status)==='MINORS')<span class="pill team-minors">Minors</span>@endif
                              @if($player->line_number)
                                @if(strtoupper((string)$player->position)==='G' && $player->line_number<=2)<span class="pill goalie-{{ $player->line_number }}">G{{ $player->line_number }}</span>
                                @elseif($player->line_number<=4)<span class="pill line-{{ $player->line_number }}">L{{ $player->line_number }}</span>@endif
                              @endif
                              @if($player->pp_unit===1)<span class="pill pp1">PP1</span>@elseif($player->pp_unit===2)<span class="pill pp2">PP2</span>@endif
                            </span>
                          </div>
                          <div class="current-player-opponent">
                            @if($player->opponent)<span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($player->game_time) · {{ $player->game_time }}@endif</span>@elseif($player->is_playing)<span class="team-playing-text">Playing</span>@else<span class="subtle">No game</span>@endif
                          </div>
                        </div>
                        <div class="current-player-metrics"><div><span>Contract</span><strong>{{ $player->contract ?: '—' }}</strong></div><div class="current-player-points"><span>Proj.</span><strong>{{ $player->projected_fpts!==null?number_format($player->projected_fpts,0):'—' }}</strong></div></div>
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
.team-updated{font-size:12px;opacity:.8;margin-top:5px}.team-toolbar{display:flex;flex-direction:column;align-items:flex-start;gap:8px;margin:18px 0}.team-date-buttons{display:flex;gap:6px;margin:18px 0}.team-date-buttons .button{padding:6px 10px;font-size:12px}.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.team-status-slicer{display:flex;gap:6px;flex-wrap:wrap}.team-status-button{appearance:none;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:999px;padding:6px 11px;font-size:11px;font-weight:800;cursor:pointer}.team-status-button.active{background:#0b5f95;color:#fff;border-color:#0b5f95}.team-status-button:hover{border-color:#64748b}.current-team-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin-bottom:28px}.current-team-card{padding:0;overflow:hidden}.current-team-card summary{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;cursor:pointer;list-style:none}.current-team-card summary::-webkit-details-marker{display:none}.current-team-card summary>span{display:flex;flex-direction:column;gap:3px}.current-team-card summary small{font-size:11px;color:var(--muted);font-weight:500}.team-open-link{font-size:12px;font-weight:800;text-decoration:none}.current-team-roster{border-top:1px solid var(--line);padding:0 14px 14px}.current-position-group h3{margin:14px 0 7px;font-size:15px}.current-playing-label{font-size:11px;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);font-weight:800;margin:9px 0 5px}.current-player-row{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-top:1px solid var(--line)}.current-player-row.not-playing{opacity:.62}.current-player-main{min-width:0}.current-player-name{display:flex;align-items:center;gap:6px;flex-wrap:wrap;font-size:13px}.current-badges{display:inline-flex;align-items:center;gap:4px;flex-wrap:wrap}.current-player-opponent{font-size:11px;margin-top:3px}.current-player-metrics{display:flex;align-items:center;gap:14px;text-align:right;flex:0 0 auto}.current-player-metrics>div>span{display:block;color:var(--muted);font-size:9px;font-weight:700}.current-player-metrics>div>strong{font-size:13px}.current-player-points{text-align:right}.current-player-points span{display:block;color:var(--muted);font-size:9px;font-weight:700}.current-player-points strong{font-size:17px}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626;padding:2px 5px!important;font-size:9px!important;line-height:1}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.line-1,.pp1,.goalie-1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2,.goalie-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}.team-playing-text{color:#15803d;font-weight:800}
@media(max-width:800px){.current-team-grid{grid-template-columns:1fr}.current-team-card summary{padding:14px}.current-team-roster{padding:0 12px 12px}.current-player-name{font-size:12px}.current-player-points strong{font-size:16px}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const statusButtons=[...document.querySelectorAll('.team-status-button')];
  const selected=new Set(['playing']);
  const renderStatusFilter=()=>{
    document.querySelectorAll('[data-status]').forEach(el=>{
      el.style.display=selected.has(el.dataset.status)?'':'none';
    });
    document.querySelectorAll('.team-position-section,.current-position-group').forEach(section=>{
      const visible=[...section.querySelectorAll('.team-player-data-row,.current-player-row')].some(row=>row.style.display!=='none');
      section.style.display=visible?'':'none';
    });
    statusButtons.forEach(button=>{
      const active=selected.has(button.dataset.statusFilter);
      button.classList.toggle('active',active);
      button.setAttribute('aria-pressed',active?'true':'false');
    });
  };
  statusButtons.forEach(button=>button.addEventListener('click',()=>{
    const key=button.dataset.statusFilter;
    if(selected.has(key)) selected.delete(key); else selected.add(key);
    if(selected.size===0) selected.add('playing');
    renderStatusFilter();
  }));
  renderStatusFilter();
});
</script>
@endsection
