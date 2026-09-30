@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026-27 roster</div><label class="team-title-switcher"><span class="sr-only">Team</span><select aria-label="Team" onchange="if(this.value) location.href='/teams/current/'+this.value+'?date={{ $date }}'">@foreach($teamChoices as $choice)<option value="{{ $choice['slug'] }}" {{ $choice['slug']===$slug?'selected':'' }}>{{ $choice['name'] }}</option>@endforeach</select></label><p>Current Fantrax roster for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}. @if($fantraxTeamUrl)<a class="team-fantrax-link" href="{{ $fantraxTeamUrl }}" target="_blank" rel="noopener noreferrer"><img src="/fantrax-icon.png" alt="">Fantrax ↗</a>@endif</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>
<div class="shell current-team-page">
  <div class="team-page-controls">
    <div class="team-left-controls">
      <div class="team-date-buttons">
        <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $today }}">Today</a>
        <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $tomorrow }}">Tomorrow</a>
      </div>
    </div>
  </div>

  @php($hasRows=collect($positions)->sum(fn($g)=>$g['rows']->count())>0)
  @if(!$hasRows)
    <div class="card"><h2>No roster data yet</h2><p class="subtle">Run the Fantasy Team Rosters collector from Collector Status to populate this team.</p><a class="button primary" href="/job-status">Collector Status</a></div>
  @else
    @foreach($positions as $code=>$group)
      <section class="team-position-section">
        <div class="table-card"><div class="table-scroll"><table class="data-table team-roster-table">
          <thead><tr><th>Player</th><th>Opponent</th><th class="num">Proj.</th></tr></thead>
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
              <tr class="team-roster-group {{ $groupKey==='playing'?'team-playing-group':'' }} {{ $groupKey==='active'?'team-not-playing-toggle':'' }}" data-status="{{ $groupKey }}">
                <td colspan="3">
                  @if($groupKey==='active')
                    <button type="button" class="team-not-playing-button" aria-expanded="false">{{ $group['label'] }} Not Playing <span class="team-not-playing-chevron">▾</span></button>
                  @else
                    {{ $group['label'] }} {{ $groupLabel }} ({{ $statusRows->reject(fn($p)=>(bool)$p->is_ir)->count() }})
                  @endif
                </td>
              </tr>
              @foreach($statusRows as $player)
                <tr class="team-player-data-row {{ $groupKey!=='playing'?'team-not-playing':'' }} {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }}" data-status="{{ $groupKey }}">
                  <td data-label="Player">
                    <div class="team-player-name-wrap">
                      @if($player->is_ir)
                        <span class="pill team-ir">IR</span>
                      @endif
                      <strong>{{ $player->player_name }}@if($player->nhl_team) ({{ $player->nhl_team }})@endif</strong>
                      @if($player->is_bench)
                        <span class="pill team-bench">Bench</span>
                      @endif
                      @if(!empty($player->contract_label))
                        <span class="pill team-contract-sticker {{ $player->contract_class }}">{{ $player->contract_label }}</span>
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
                  <td data-label="Opponent">@if($player->opponent)<span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($player->game_time) · {{ $player->game_time }}@endif</span>@elseif($player->is_playing)<span class="team-playing-text">Playing</span>@endif</td>
                  <td data-label="Proj." class="num"><strong>{{ $player->projected_fpts!==null?number_format($player->projected_fpts,0):'—' }}</strong></td>
                </tr>
              @endforeach
            @endif
          @endforeach
          </tbody>
        </table></div></div>

        @if(in_array($code,['F','D','G'],true) && count($targetGroups[$code] ?? []))
          <details class="team-targets">
            <summary>{{ $group['label'] }} Targets <span>{{ count($targetGroups[$code] ?? []) }}</span></summary>
            <div class="team-target-list">
              @foreach(($targetGroups[$code] ?? []) as $target)
                <div class="team-target-row" data-target-row @if($loop->iteration>5) hidden @endif>
                  <div class="team-target-main">
                    <div class="team-target-name">
                      @if(!empty($target['injury_status']))<span class="pill team-ir">IR</span>@endif
                      <strong>{{ $target['name'] }} ({{ $target['team'] }})</strong>
                      <span class="pill team-target-status {{ str_starts_with($target['status'],'FA')?'target-fa':'target-waiver' }}">{{ $target['status'] }}</span>
                      @if(!empty($target['line_number']))
                        @if($code==='G' && $target['line_number']<=2)
                          <span class="pill goalie-{{ $target['line_number'] }}">G{{ $target['line_number'] }}</span>
                        @elseif($target['line_number']<=4)
                          <span class="pill line-{{ $target['line_number'] }}">L{{ $target['line_number'] }}</span>
                        @endif
                      @endif
                      @if(($target['pp_unit']??null)===1)<span class="pill pp1">PP1</span>@elseif(($target['pp_unit']??null)===2)<span class="pill pp2">PP2</span>@endif
                    </div>
                    <div class="team-target-opponent">@if(!empty($target['opponent'])){{ $target['opponent'] }}@endif @if($code==='G' && !empty($target['starting_status']))· {{ $target['starting_status'] }}@endif</div>
                  </div>
                  <div class="team-target-actions">
                    <strong class="team-target-proj">{{ $target['projected_points']!==null?number_format($target['projected_points'],0):'—' }}</strong>
                    @if(!empty($target['add_url']))
                      <a class="team-target-add" href="{{ $target['add_url'] }}" target="_blank" rel="noopener noreferrer">+ Add</a>
                    @endif
                  </div>
                </div>
              @endforeach
              @if(count($targetGroups[$code] ?? [])>5)
                <button type="button" class="team-target-more" data-target-more>View more</button>
              @endif
            </div>
          </details>
        @endif
      </section>
    @endforeach
  @endif
</div>
<style>
.team-updated{font-size:12px;opacity:.8;margin-top:3px}.team-title-switcher{display:block;width:min(520px,100%)}.team-title-switcher select{appearance:auto;width:auto;max-width:100%;border:0;background:transparent;color:var(--text);font:inherit;font-size:34px;font-weight:800;line-height:1.05;letter-spacing:-1px;padding:0 30px 0 0;cursor:pointer}.team-title-switcher select:focus{outline:none}.team-left-controls{display:flex;flex-direction:column;gap:8px}.team-page-controls{display:flex;align-items:end;justify-content:flex-start;gap:12px;margin:2px 0 12px}.team-fantrax-link{display:inline-flex;align-items:center;gap:4px;margin-left:6px;font-weight:800;text-decoration:none}.team-fantrax-link img{width:15px;height:15px;object-fit:contain}.team-date-buttons{display:flex;gap:5px;margin:0}.team-date-buttons .button{padding:4px 8px;font-size:11px;min-height:30px}.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.team-not-playing-toggle{cursor:pointer}.team-not-playing-button{appearance:none;border:0;background:transparent;color:inherit;font:inherit;font-weight:900;text-transform:uppercase;letter-spacing:.06em;padding:0;cursor:pointer;display:inline-flex;align-items:center;gap:6px}.team-not-playing-button:hover{text-decoration:underline}.team-not-playing-chevron{font-size:11px}.team-position-section{margin:22px 0}.team-position-section .table-card{border-radius:10px 10px 0 0}.team-targets{margin-top:0;border:1px solid #f4cf63;border-top:0;border-radius:0 0 10px 10px;background:#fff8db;overflow:hidden}.team-targets summary{cursor:pointer;list-style:none;padding:7px 9px;background:#fde68a;color:#7c5a00;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;display:flex;align-items:center;justify-content:space-between}.team-targets summary::-webkit-details-marker{display:none}.team-targets summary:after{content:"▾";font-size:10px}.team-targets[open] summary:after{content:"▴"}.team-targets summary span{margin-left:auto;margin-right:8px;font-size:9px}.team-target-list{display:block}.team-target-row{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 9px;border-top:1px solid var(--line)}.team-target-main{min-width:0}.team-target-name{display:flex;align-items:center;gap:3px;flex-wrap:wrap;font-size:12px}.team-target-name .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}.team-target-opponent{margin-top:3px;font-size:10px;color:var(--muted)}.team-target-actions{display:flex;align-items:center;gap:7px;flex:0 0 auto}.team-target-proj{flex:0 0 auto;font-size:13px}.team-target-add{display:inline-flex;align-items:center;justify-content:center;background:#0055A7;color:#fff!important;border:1px solid #004786;border-radius:7px;padding:3px 6px;font-size:9px;font-weight:900;text-decoration:none;white-space:nowrap}.team-target-add:hover{background:#004786;text-decoration:none}.team-target-more{display:block;margin:8px auto 2px;border:1px solid #e3b931;background:#fff3b0;color:#6b5200;border-radius:7px;padding:5px 10px;font-size:10px;font-weight:900;cursor:pointer}.team-target-more:hover{background:#fde68a}.team-target-status{font-weight:800}.target-fa{background:#dcfce7;color:#166534;border-color:#86efac}.target-waiver{background:#fef3c7;color:#92400e;border-color:#fcd34d}.team-roster-table td,.team-roster-table th{padding:6px 8px}.team-roster-table td:first-child{white-space:normal}.team-roster-group td{background:var(--surface-2,#f1f5f9);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:5px 8px!important}.team-playing-group td{background:#dbeafe;color:#1d4ed8}.team-not-playing{opacity:.62}.team-bench-row{background:#f3f4f6}.team-bench-row td{background:#f3f4f6!important}.team-ir-row{background:#fff1f2}.team-ir-row td{background:#fff1f2!important}.team-player-name-wrap{display:flex;align-items:center;gap:3px;flex-wrap:wrap;font-size:12px;line-height:1.2}.team-player-name-wrap .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626;margin:0;padding:2px 5px!important;font-size:9px!important;line-height:1}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.team-active{background:#dcfce7;color:#166534;border-color:#86efac}.line-1,.pp1,.goalie-1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2,.goalie-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-contract-sticker{font-weight:800;padding:3px 7px}.contract-green{background:#dcfce7;color:#166534;border-color:#86efac}.contract-yellow{background:#fef3c7;color:#92400e;border-color:#fcd34d}.contract-red{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}.team-playing-text{color:#15803d;font-weight:800}
@media(max-width:700px){
.current-team-page{width:100%;min-width:0;max-width:100%;overflow-x:hidden;contain:inline-size}
.team-position-section,.table-card,.table-scroll{width:100%;min-width:0;max-width:100%;overflow-x:hidden;contain:inline-size}
.team-page-controls{align-items:stretch;flex-direction:column;padding:0 14px}.team-title-switcher select{font-size:30px;max-width:100%}
.team-roster-table,.team-roster-table tbody{display:block!important;width:100%!important;min-width:0!important;max-width:100%!important}
.team-roster-table thead{display:none}
.team-roster-table tr{display:grid;width:100%;min-width:0;max-width:100%;grid-template-columns:minmax(0,1fr) 58px;gap:4px 8px;padding:8px;border-bottom:1px solid var(--line);overflow:hidden}
.team-roster-table td{border:0!important;padding:0!important;min-width:0;max-width:100%;overflow-wrap:anywhere}
.team-roster-table td::before{display:none}
.team-roster-table td[data-label="Player"]{grid-column:1;min-width:0}
.team-player-name-wrap{min-width:0;max-width:100%}
.team-player-name-wrap strong{min-width:0;overflow-wrap:anywhere}
.team-roster-table td[data-label="Proj."]{grid-column:2;grid-row:1;text-align:right;white-space:nowrap}
.team-roster-table td[data-label="Opponent"]{grid-column:1;min-width:0;white-space:normal;font-size:11px;line-height:1.2}
.team-roster-table .num{text-align:right}
.team-roster-table tr.team-roster-group{display:block;width:100%;padding:0;overflow:hidden}
.team-roster-table tr.team-roster-group td{display:block!important;width:100%;max-width:100%;padding:5px 8px!important}
}
</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  document.querySelectorAll('[data-target-more]').forEach(button=>{
    button.addEventListener('click',()=>{
      const list=button.closest('.team-target-list');
      const hidden=[...list.querySelectorAll('[data-target-row][hidden]')];
      hidden.slice(0,10).forEach(row=>row.hidden=false);
      if(hidden.length<=10)button.remove();
    });
  });

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
