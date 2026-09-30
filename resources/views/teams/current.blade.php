@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026-27 roster</div><h1>{{ $teamName }}</h1><p>Current Fantrax roster for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}. @if($fantraxTeamUrl)<a class="team-fantrax-link" href="{{ $fantraxTeamUrl }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" alt="">Fantrax ↗</a>@endif</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>
<div class="shell current-team-page">
  <div class="team-page-controls">
    <div class="team-left-controls">
      <div class="team-date-buttons">
        <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $today }}">Today</a>
        <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $tomorrow }}">Tomorrow</a>
      </div>
    </div>
    <label class="team-switcher">
      <span>Team</span>
      <select onchange="if(this.value) location.href='/teams/current/'+this.value+'?date={{ $date }}'">
        @foreach($teamChoices as $choice)
          <option value="{{ $choice['slug'] }}" {{ $choice['slug']===$slug?'selected':'' }}>{{ $choice['name'] }}</option>
        @endforeach
      </select>
    </label>
  </div>

  @php($hasRows=collect($positions)->sum(fn($g)=>$g['rows']->count())>0)
  @if(!$hasRows)
    <div class="card"><h2>No roster data yet</h2><p class="subtle">Run the Fantasy Team Rosters collector from Collector Status to populate this team.</p><a class="button primary" href="/job-status">Collector Status</a></div>
  @else
    @foreach($positions as $code=>$group)
      <section class="team-position-section">
        <div class="table-card"><div class="table-scroll"><table class="data-table team-roster-table">
          <thead><tr><th>Player</th><th>Opponent</th><th>Contract</th><th class="num">Proj.</th></tr></thead>
          <tbody>
          @foreach(['playing'=>'Playing','active'=>'Not Playing'] as $groupKey=>$groupLabel)
            @php($statusRows=$group['rows']->filter(function($p)use($groupKey){
              $playing=(bool)$p->is_playing;
              return match($groupKey){
                'playing'=>$playing,
                'active'=>!$playing,
                default=>false,
              };
            }))
            @if($statusRows->count())
              <tr class="team-roster-group {{ $groupKey==='active'?'team-not-playing-toggle':'' }}" data-status="{{ $groupKey }}">
                <td colspan="4">
                  @if($groupKey==='active')
                    <button type="button" class="team-not-playing-button" aria-expanded="false">{{ $group['label'] }} Not Playing <span class="team-not-playing-chevron">▾</span></button>
                  @else
                    {{ $group['label'] }} {{ $groupLabel }}
                  @endif
                </td>
              </tr>
              @foreach($statusRows as $player)
                <tr class="team-player-data-row {{ $groupKey!=='playing'?'team-not-playing':'' }}" data-status="{{ $groupKey }}">
                  <td data-label="Player">
                    <div class="team-player-name-wrap">
                      @if($player->is_ir)
                        <span class="pill team-ir">IR</span>
                      @endif
                      <strong>{{ $player->player_name }}@if($player->nhl_team) ({{ $player->nhl_team }})@endif</strong>
                      @if($player->is_bench)
                        <span class="pill team-bench">Bench</span>
                      @endif
                      @if($player->line_number)
                        @if(strtoupper((string)$player->position)==='G' && $player->line_number<=2)
                          <span class="pill goalie-{{ $player->line_number }}">G{{ $player->line_number }}</span>
                        @elseif($player->line_number<=4)
                          <span class="pill line-{{ $player->line_number }}">L{{ $player->line_number }}</span>
                        @endif
                      @endif
                      @if($player->pp_unit===1)
                        <span class="pill pp1">PP1</span>
                      @elseif($player->pp_unit===2)
                        <span class="pill pp2">PP2</span>
                      @endif
                    </div>
                  </td>
                  <td data-label="Opponent">@if($player->opponent)<span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($player->game_time) · {{ $player->game_time }}@endif</span>@elseif($player->is_playing)<span class="team-playing-text">Playing</span>@else<span class="subtle">Not playing</span>@endif</td>
                  <td data-label="Contract">@if($player->contract)<span class="team-contract">{{ $player->contract }}</span>@else<span class="subtle">—</span>@endif</td>
                  <td data-label="Proj." class="num"><strong>{{ $player->projected_fpts!==null?number_format($player->projected_fpts,0):'—' }}</strong></td>
                </tr>
              @endforeach
            @endif
          @endforeach
          </tbody>
        </table></div></div>
      </section>
    @endforeach
  @endif
</div>
<style>
.team-updated{font-size:12px;opacity:.8;margin-top:5px}.team-left-controls{display:flex;flex-direction:column;gap:8px}.team-page-controls{display:flex;align-items:end;justify-content:space-between;gap:12px;margin:18px 0}.team-switcher{display:flex;flex-direction:column;gap:4px;min-width:260px;font-size:12px;font-weight:800}.team-switcher select{width:100%;border:1px solid var(--line);background:var(--surface);color:var(--text);border-radius:9px;padding:8px 10px;font:inherit}.team-fantrax-link{display:inline-flex;align-items:center;gap:4px;margin-left:6px;font-weight:800;text-decoration:none}.team-fantrax-link img{width:15px;height:15px;object-fit:contain}.team-date-buttons{display:flex;gap:6px;margin:18px 0}.team-date-buttons .button{padding:6px 10px;font-size:12px}.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.team-not-playing-toggle{cursor:pointer}.team-not-playing-button{appearance:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:900;text-transform:uppercase;letter-spacing:.06em;padding:0;cursor:pointer;display:inline-flex;align-items:center;gap:6px}.team-not-playing-button:hover{text-decoration:underline}.team-not-playing-chevron{font-size:11px}.team-position-section{margin:22px 0}.team-roster-table td,.team-roster-table th{padding:8px 10px}.team-roster-table td:first-child{white-space:normal}.team-roster-group td{background:var(--surface-2,#f1f5f9);font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);padding:7px 10px!important}.team-not-playing{opacity:.62}.team-player-name-wrap{display:flex;align-items:center;gap:5px;flex-wrap:wrap}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626;margin:0;padding:2px 5px!important;font-size:9px!important;line-height:1}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.team-active{background:#dcfce7;color:#166534;border-color:#86efac}.line-1,.pp1,.goalie-1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2,.goalie-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-contract{font-weight:800;font-size:12px}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}.team-playing-text{color:#15803d;font-weight:800}
@media(max-width:700px){.current-team-page,.team-position-section,.table-card,.table-scroll{min-width:0;max-width:100%;overflow-x:hidden}.team-roster-table{width:100%;max-width:100%}.team-page-controls{align-items:stretch;flex-direction:column}.team-switcher{min-width:0;width:100%}.team-roster-table,.team-roster-table tbody{display:block}.team-roster-table thead{display:none}.team-roster-table tr{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:7px 12px;padding:11px;border-bottom:1px solid var(--line)}.team-roster-table td{border:0!important;padding:0!important}.team-roster-table td::before{display:none}.team-roster-table td[data-label="Player"]{grid-column:1}.team-roster-table td[data-label="Proj."]{grid-column:2;grid-row:1;text-align:right}.team-roster-table td[data-label="Opponent"]{grid-column:1}.team-roster-table td[data-label="Contract"]{grid-column:2;text-align:right;font-size:11px}.team-roster-table .num{text-align:right}.team-roster-table tr.team-roster-group{display:block;padding:0}.team-roster-table tr.team-roster-group td{display:block!important;width:100%;padding:7px 10px!important}}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('.team-not-playing-toggle').forEach(toggle=>{
    const section=toggle.closest('.team-position-section,.current-position-group');
    const button=toggle.querySelector('.team-not-playing-button');
    const chevron=toggle.querySelector('.team-not-playing-chevron');
    const rows=section?[...section.querySelectorAll('[data-status="active"]')].filter(el=>el!==toggle):[];
    rows.forEach(row=>row.style.display='none');
    button?.addEventListener('click',()=>{
      const expanded=button.getAttribute('aria-expanded')==='true';
      rows.forEach(row=>row.style.display=expanded?'none':'');
      button.setAttribute('aria-expanded',expanded?'false':'true');
      if(chevron)chevron.textContent=expanded?'▾':'▴';
    });
  });
});
</script>
@endsection
