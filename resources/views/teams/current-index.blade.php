@extends('layouts.app')
@section('content')
<div class="page-head current-teams-head"><div class="shell"><div class="eyebrow">2026-27 rosters</div><h1>Teams</h1><p>Current fantasy rosters for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}.</p>@if($lastUpdate)<p class="team-updated">Updated {{ \Carbon\CarbonImmutable::parse($lastUpdate)->setTimezone('America/Halifax')->format('g:i a T') }}</p>@endif</div></div>

<div class="shell current-teams-page">
  <div class="team-toolbar">
    <div class="team-date-buttons">
      <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $today }}">Today</a>
      <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $tomorrow }}">Tomorrow</a>
    </div>
  </div>

  @if($scheduleLabel)
    <div class="matchup-period-label">{{ $scheduleLabel }}</div>
  @endif

  <div class="current-matchup-list">
    @foreach($matchups as $matchup)
      @php
        $away=$matchup['away'] ?? null;
        $home=$matchup['home'] ?? null;

        $awayDay=(float)($away['today_fpts'] ?? 0);
        $homeDay=(float)($home['today_fpts'] ?? 0);
        $awayWeek=(float)($away['week_fpts'] ?? 0);
        $homeWeek=(float)($home['week_fpts'] ?? 0);

        $scoreClass=function(float $mine,float $theirs,string $prefix): string {
          if($mine==0.0 && $theirs==0.0)return $prefix.'-zero';
          if($mine>$theirs)return $prefix.'-leading';
          if($mine<$theirs)return $prefix.'-trailing';
          return $prefix.'-tied';
        };

        $awayDayClass=$awayDay==0.0?'matchup-day-zero':$scoreClass($awayDay,$homeDay,'matchup-day');
        $homeDayClass=$homeDay==0.0?'matchup-day-zero':$scoreClass($homeDay,$awayDay,'matchup-day');
        $awayWeekClass=$scoreClass($awayWeek,$homeWeek,'matchup-week');
        $homeWeekClass=$scoreClass($homeWeek,$awayWeek,'matchup-week');

        $allPlayers=function($team){
          return $team
            ? collect($team['positions'])->flatMap(fn($group)=>$group['rows'])->values()
            : collect();
        };

        $awayAll=$allPlayers($away);
        $homeAll=$allPlayers($home);

        $skaters=function($rows){
          return $rows
            ->filter(fn($p)=>(bool)$p->is_playing && !(bool)$p->is_bench && strtoupper((string)$p->position)!=='G')
            ->sortBy(fn($p)=>(bool)$p->is_ir?1:0)
            ->values();
        };

        $goalies=function($rows){
          return $rows
            ->filter(fn($p)=>(bool)$p->is_playing && !(bool)$p->is_bench && strtoupper((string)$p->position)==='G')
            ->sortBy(fn($p)=>(bool)$p->is_ir?1:0)
            ->values();
        };

        $bench=function($rows){
          return $rows
            ->filter(fn($p)=>(bool)$p->is_playing && (bool)$p->is_bench)
            ->sortBy(fn($p)=>(bool)$p->is_ir?1:0)
            ->values();
        };

        $awaySkaters=$skaters($awayAll);
        $homeSkaters=$skaters($homeAll);
        $awayGoalies=$goalies($awayAll);
        $homeGoalies=$goalies($homeAll);
        $awayBench=$bench($awayAll);
        $homeBench=$bench($homeAll);
      @endphp

      <details class="matchup-card" data-matchup-key="{{ ($away['id'] ?? $away['slug'] ?? 'away') }}::{{ ($home['id'] ?? $home['slug'] ?? 'home') }}">
        <summary class="matchup-summary">
          <div class="matchup-summary-side matchup-summary-away">
            @if($away)
              <div class="matchup-summary-name">
                <a href="/teams/current/{{ $away['slug'] }}?date={{ $date }}" onclick="event.stopPropagation()">{{ $away['name'] }}</a>
                <div class="matchup-summary-meta matchup-summary-meta-away">
                  <span class="matchup-side-pill away-pill">AWAY</span>
                  <span class="matchup-daily-cats">@foreach(['gp'=>'GP','g'=>'G','a'=>'A','ppg'=>'PPG','shg'=>'SHG','gwg'=>'GWG','w'=>'W','so'=>'SO'] as $key=>$label)@if(($away['today_stats'][$key] ?? 0) != 0)<span>{{ $label }}: {{ $away['today_stats'][$key] }}</span>@endif @endforeach</span>
                </div>
              </div>
              <span class="matchup-summary-score"><strong class="matchup-week-score {{ $awayWeekClass }}">{{ number_format($away['week_fpts'] ?? 0,0) }}</strong><small class="matchup-day-score {{ $awayDayClass }}">+{{ number_format($away['today_fpts'] ?? 0,0) }}</small></span>
            @endif
          </div>

          <div class="matchup-summary-vs">VS</div>

          <div class="matchup-summary-side matchup-summary-home">
            @if($home)
              <span class="matchup-summary-score"><strong class="matchup-week-score {{ $homeWeekClass }}">{{ number_format($home['week_fpts'] ?? 0,0) }}</strong><small class="matchup-day-score {{ $homeDayClass }}">+{{ number_format($home['today_fpts'] ?? 0,0) }}</small></span>
              <div class="matchup-summary-name">
                <a href="/teams/current/{{ $home['slug'] }}?date={{ $date }}" onclick="event.stopPropagation()">{{ $home['name'] }}</a>
                <div class="matchup-summary-meta matchup-summary-meta-home">
                  <span class="matchup-daily-cats">@foreach(['gp'=>'GP','g'=>'G','a'=>'A','ppg'=>'PPG','shg'=>'SHG','gwg'=>'GWG','w'=>'W','so'=>'SO'] as $key=>$label)@if(($home['today_stats'][$key] ?? 0) != 0)<span>{{ $label }}: {{ $home['today_stats'][$key] }}</span>@endif @endforeach</span>
                  <span class="matchup-side-pill home-pill">HOME</span>
                </div>
              </div>
            @else
              <span class="matchup-bye">BYE</span>
            @endif
          </div>
        </summary>

        <div class="matchup-expanded">
          <div class="matchup-section-title">Skaters</div>
          <div class="matchup-roster-grid">
            <div class="matchup-roster-col">
              @forelse($awaySkaters as $player)
                @include('teams.partials.current-matchup-player',['player'=>$player])
              @empty
                <div class="matchup-empty">(Empty)</div>
              @endforelse
            </div>
            <div class="matchup-roster-col">
              @forelse($homeSkaters as $player)
                @include('teams.partials.current-matchup-player',['player'=>$player])
              @empty
                <div class="matchup-empty">(Empty)</div>
              @endforelse
            </div>
          </div>

          <div class="matchup-section-title">Goalies</div>
          <div class="matchup-roster-grid">
            <div class="matchup-roster-col">
              @forelse($awayGoalies as $player)
                @include('teams.partials.current-matchup-player',['player'=>$player])
              @empty
                <div class="matchup-empty">(Empty)</div>
              @endforelse
            </div>
            <div class="matchup-roster-col">
              @forelse($homeGoalies as $player)
                @include('teams.partials.current-matchup-player',['player'=>$player])
              @empty
                <div class="matchup-empty">(Empty)</div>
              @endforelse
            </div>
          </div>

          @if($awayBench->count() || $homeBench->count())
            <div class="matchup-bench">
              <div class="matchup-bench-title">Bench</div>
              <div class="matchup-section-title">Skaters</div>
              <div class="matchup-roster-grid">
                <div class="matchup-roster-col">
                  @forelse($awayBench as $player)
                    @include('teams.partials.current-matchup-player',['player'=>$player])
                  @empty
                    <div class="matchup-empty">(Empty)</div>
                  @endforelse
                </div>
                <div class="matchup-roster-col">
                  @forelse($homeBench as $player)
                    @include('teams.partials.current-matchup-player',['player'=>$player])
                  @empty
                    <div class="matchup-empty">(Empty)</div>
                  @endforelse
                </div>
              </div>
            </div>
          @endif
        </div>
      </details>
    @endforeach
  </div>
</div>

<style>
.current-teams-head{padding:16px 0 8px}.current-teams-head .eyebrow{margin-bottom:5px}.current-teams-head p{margin-top:4px}.team-updated{font-size:12px;opacity:.8;margin-top:3px}.team-toolbar{display:flex;flex-direction:column;align-items:flex-start;gap:8px;margin:4px 0 10px}.team-date-buttons{display:flex;gap:6px;margin:4px 0}
.team-date-buttons .button{padding:6px 10px;font-size:12px}
.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}
.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}
.matchup-period-label{margin:4px 0 10px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.current-matchup-list{display:flex;flex-direction:column;gap:7px;margin-bottom:20px}
.matchup-card{border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden}
.matchup-summary{display:grid;grid-template-columns:minmax(0,1fr) 38px minmax(0,1fr);align-items:center;gap:8px;padding:9px 14px;cursor:pointer;list-style:none;background:#fff}
.matchup-summary::-webkit-details-marker{display:none}
.matchup-summary-side{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:0}
.matchup-summary-home{text-align:right}
.matchup-summary-name{min-width:0;display:flex;flex-direction:column}
.matchup-summary-name a{font-weight:900;color:var(--text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.matchup-summary-name a:hover{text-decoration:underline}
.matchup-summary-name small{font-size:9px;color:var(--muted);font-weight:800;letter-spacing:.05em}.matchup-summary-meta{display:flex;align-items:center;gap:7px;min-width:0}.matchup-summary-meta-home{justify-content:flex-end}.matchup-daily-cats{font-size:9px;color:#475569;font-weight:900;white-space:nowrap;display:inline-flex;gap:5px;align-items:center}.matchup-side-pill{display:inline-flex;align-items:center;justify-content:center;padding:2px 7px;border-radius:999px;font-size:9px;font-weight:900;letter-spacing:.05em;line-height:1.1;border:1px solid transparent;white-space:nowrap}.away-pill{background:#fef3c7;color:#92400e;border-color:#fcd34d}.home-pill{background:#dcfce7;color:#166534;border-color:#86efac}
.matchup-summary-score{display:inline-flex;align-items:flex-start;gap:4px;white-space:nowrap}.matchup-week-score{font-size:20px;line-height:1;font-weight:900}.matchup-day-score{font-size:13px;line-height:1;font-weight:900;transform:translateY(-2px)}.matchup-day-zero,.matchup-week-zero{color:var(--text)}.matchup-day-leading,.matchup-week-leading{color:#16a34a}.matchup-day-tied,.matchup-week-tied{color:#2563eb}.matchup-day-trailing,.matchup-week-trailing{color:#dc2626}
.matchup-summary-vs{text-align:center;font-size:10px;font-weight:900;color:var(--muted)}
.matchup-bye{font-size:11px;font-weight:900;color:var(--muted)}
.matchup-expanded{border-top:1px solid var(--line);background:#fff}
.matchup-section-title{grid-column:1/-1;text-align:center;background:#e5e7eb;color:#374151;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;padding:5px 8px;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.matchup-roster-grid{display:grid;grid-template-columns:1fr 1fr}
.matchup-roster-col{min-width:0;border-right:1px solid var(--line)}
.matchup-roster-col:last-child{border-right:0}
.matchup-player-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-bottom:1px solid var(--line);min-height:58px}
.matchup-player-row:last-child{border-bottom:0}
.matchup-player-main{min-width:0}
.matchup-player-name{display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:13px;line-height:1.15}
.matchup-player-name strong{min-width:0}
.matchup-player-name .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}
.matchup-player-opponent{margin-top:4px;font-size:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}.matchup-player-cats{font-size:8px;color:var(--muted);font-weight:800;white-space:nowrap}
.matchup-player-metrics{display:flex;align-items:flex-start;align-self:flex-start;gap:10px;flex:0 0 auto;text-align:right}
.matchup-player-metrics span{display:block;font-size:8px;color:var(--muted);font-weight:700}
.matchup-player-metrics strong{display:block;font-size:14px}
.matchup-player-today strong{font-size:17px}
.matchup-empty{padding:18px 12px;color:var(--muted);font-size:12px}
.matchup-bench{background:#f8fafc;border-top:12px solid #eef2f7}
.matchup-bench-title{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;font-size:13px;font-weight:900;text-transform:uppercase;color:#4b5563}
.team-ir-row{background:#fff1f2}
.team-bench-row{background:#f3f4f6}
.team-minors-row{background:#eff6ff}
.team-ir{background:#dc2626;color:#fff;border-color:#dc2626}
.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}
.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}
.team-contract-sticker{font-weight:800}
.contract-green{background:#dcfce7;color:#166534;border-color:#86efac}
.contract-yellow{background:#fef3c7;color:#92400e;border-color:#fcd34d}
.contract-red{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}
.line-1,.pp1,.goalie-1{background:#dcfce7;color:#166534;border-color:#86efac}
.line-2,.pp2,.goalie-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}
.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}
.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}
.goalie-vegas-odds{font-weight:900}
.vegas-odds-good{background:#dcfce7;color:#166534;border-color:#86efac}
.vegas-odds-even{background:#fef3c7;color:#92400e;border-color:#fcd34d}
.vegas-odds-bad{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}
.team-away{color:#a16207;font-weight:800}
.team-home{color:#15803d;font-weight:800}
.team-playing-text{color:#15803d;font-weight:800}
html[data-theme="dark"] .matchup-card{background:#0f1c2b;border-color:#334155}
html[data-theme="dark"] .matchup-summary{background:#0f1c2b}
html[data-theme="dark"] .matchup-expanded{background:#0f1c2b;border-top-color:#334155}
html[data-theme="dark"] .matchup-summary-name a,
html[data-theme="dark"] .matchup-week-score,
html[data-theme="dark"] .matchup-player-name strong,
html[data-theme="dark"] .matchup-player-metrics strong{color:#f8fafc}
html[data-theme="dark"] .matchup-summary-vs,
html[data-theme="dark"] .matchup-player-metrics span,
html[data-theme="dark"] .matchup-player-cats,
html[data-theme="dark"] .matchup-daily-cats{color:#cbd5e1}
html[data-theme="dark"] .matchup-roster-col{border-color:#334155}
html[data-theme="dark"] .matchup-player-row{background:#0f1c2b;border-bottom-color:#334155;color:#f8fafc}
html[data-theme="dark"] .matchup-section-title{background:#1f2937;color:#e5e7eb;border-color:#334155}
html[data-theme="dark"] .matchup-bench{background:#111827;border-top-color:#1f2937}
html[data-theme="dark"] .matchup-bench-title{color:#e5e7eb}
html[data-theme="dark"] .matchup-empty{color:#94a3b8}
html[data-theme="dark"] .team-ir-row{background:#3a1f26}
html[data-theme="dark"] .team-bench-row{background:#1d2735}
html[data-theme="dark"] .team-minors-row{background:#17263a}
html[data-theme="dark"] .team-home{color:#4ade80}
html[data-theme="dark"] .team-away{color:#fbbf24}
@media(max-width:800px){
  .matchup-summary{grid-template-columns:minmax(0,1fr) 26px minmax(0,1fr);padding:8px 10px}
  .matchup-summary-score{font-size:18px}
  .matchup-summary-name a{font-size:12px}
  .matchup-summary-meta{gap:4px}
  .matchup-daily-cats{font-size:8px;color:#475569;overflow:hidden;text-overflow:ellipsis;max-width:170px}
  .matchup-roster-grid{grid-template-columns:1fr 1fr}
  .matchup-player-row{padding:7px 8px;align-items:flex-start}
  .matchup-player-name{font-size:11px}
  .team-contract-sticker,.line-1,.line-2,.line-3,.line-4,.pp1,.pp2{display:none!important}
  .matchup-player-opponent{gap:5px}
  .matchup-player-cats{font-size:7px}
  .matchup-player-metrics{gap:6px}
  .matchup-player-metrics strong{font-size:12px}
  .matchup-player-today strong{font-size:14px}
}
</style>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const stateKey='ecfhl-open-matchups:'+location.pathname+location.search;
  const cards=[...document.querySelectorAll('.matchup-card[data-matchup-key]')];

  try{
    const saved=JSON.parse(sessionStorage.getItem(stateKey)||'[]');
    if(Array.isArray(saved)){
      cards.forEach(card=>card.open=saved.includes(card.dataset.matchupKey));
    }
  }catch(e){}

  const saveOpenMatchups=()=>{
    try{
      const open=cards.filter(card=>card.open).map(card=>card.dataset.matchupKey);
      sessionStorage.setItem(stateKey,JSON.stringify(open));
    }catch(e){}
  };

  cards.forEach(card=>card.addEventListener('toggle',saveOpenMatchups));

  setInterval(()=>{
    saveOpenMatchups();
    location.reload();
  },60000);

  document.querySelectorAll('.matchup-summary a').forEach(link=>{
    link.addEventListener('click',event=>event.stopPropagation());
  });
});
</script>
@endsection
