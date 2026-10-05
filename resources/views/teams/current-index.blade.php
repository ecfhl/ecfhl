@extends('layouts.app')
@section('content')
<div class="page-head current-teams-head"><div class="shell"><div class="eyebrow">2026-27 rosters</div><h1><span style="color:#c94b52">●</span> Live Scoring</h1><p>Current Matchups for {{ \Carbon\CarbonImmutable::parse($date)->format('M j, Y') }}.</p>@if($scoreLastUpdate)<p class="team-updated">Updated @include('partials.updated-time',['value'=>$scoreLastUpdate]){{ $autoRefresh ? ' · Refreshes every 2 minutes during games' : '' }}</p>@endif</div></div>

<div class="shell current-teams-page">
  <div class="team-toolbar">
    <div class="team-date-buttons">
      <a class="button team-date-button {{ $date===$yesterday?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $yesterday }}">Yesterday</a>
      <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $today }}">Today</a>
      <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current?date={{ $tomorrow }}">Tomorrow</a>
    </div>
  </div>

  @if($scheduleLabel)
    <div class="matchup-period-label">
      <span>{{ $scheduleLabel }}</span>
    </div>
  @endif

  @php
    // Pin Lone Tsar first and One Man Bang second. If they play each other,
    // their shared matchup is first and is only listed once.
    $matchups = collect($matchups)->sortBy(function($matchup, $index){
      $names = collect([$matchup['away']['name'] ?? '', $matchup['home']['name'] ?? '']);
      $hasLoneTsar = $names->contains(fn($name) => str_contains((string)$name, 'Ꮮ૦ท૯'));
      $hasOneManBang = $names->contains(fn($name) => str_contains((string)$name, 'One Man Bang'));

      if ($hasLoneTsar && $hasOneManBang) return -2000;
      if ($hasLoneTsar) return -1000;
      if ($hasOneManBang) return -500;
      return $index;
    })->values();
  @endphp

  @if(!$scoreLastUpdate)<p>No valid Fantrax snapshot is available for this fantasy date yet.</p>@endif
  <div class="current-matchup-list">
    @foreach($matchups as $matchup)
      @php
        $away=$matchup['away'] ?? null;
        $home=$matchup['home'] ?? null;

        $awayDay=(float)($away['today_fpts'] ?? 0);
        $homeDay=(float)($home['today_fpts'] ?? 0);
        $awayWeek=(float)($away['week_fpts'] ?? 0);
        $homeWeek=(float)($home['week_fpts'] ?? 0);

        $awayDayClass='score-'.($away['today_fpts_change'] ?? 'same');
        $homeDayClass='score-'.($home['today_fpts_change'] ?? 'same');
        $awayWeekClass='score-'.($away['week_fpts_change'] ?? 'same');
        $homeWeekClass='score-'.($home['week_fpts_change'] ?? 'same');

        $allPlayers=function($team){
          return $team
            ? collect($team['positions'])->flatMap(fn($group)=>$group['rows'])->values()
            : collect();
        };

        $awayAll=$allPlayers($away);
        $homeAll=$allPlayers($home);

        $sectionGroups=function($rows){
          $playing=$rows->filter(fn($p)=>(bool)$p->daily_participant)->values();

          return [
            'Forwards'=>$playing->filter(fn($p)=>
              $p->scoring_status==='ACTIVE'
              && strtoupper((string)$p->position)==='F'
            )->values(),
            'Defensemen'=>$playing->filter(fn($p)=>
              $p->scoring_status==='ACTIVE'
              && strtoupper((string)$p->position)==='D'
            )->values(),
            'Goalies'=>$playing->filter(fn($p)=>
              $p->scoring_status==='ACTIVE'
              && strtoupper((string)$p->position)==='G'
            )->values(),
            'Bench'=>$playing->filter(fn($p)=>
              (bool)$p->is_bench
              && !(bool)$p->is_ir
              && strtoupper((string)$p->roster_status)!=='MINORS'
            )->values(),
            'IR'=>$playing->filter(fn($p)=>
              (bool)$p->is_ir
              && strtoupper((string)$p->roster_status)!=='MINORS'
            )->values(),
            'Minors'=>$playing->filter(fn($p)=>
              strtoupper((string)$p->roster_status)==='MINORS'
            )->values(),
          ];
        };

        $awaySections=$sectionGroups($awayAll);
        $homeSections=$sectionGroups($homeAll);

        $alignedSections=[];
        foreach(['Forwards','Defensemen','Goalies','Bench','IR','Minors'] as $sectionName){
          $left=$awaySections[$sectionName]??collect();
          $right=$homeSections[$sectionName]??collect();
          $max=max($left->count(),$right->count());
          while($left->count()<$max)$left->push(null);
          while($right->count()<$max)$right->push(null);
          $alignedSections[$sectionName]=['away'=>$left,'home'=>$right];
        }

        $activePlayingCounts=function($rows){
          $eligible=$rows->filter(fn($p)=>
            (bool)$p->daily_participant
            && $p->scoring_status==='ACTIVE'
          );
          return [
            'F'=>$eligible->where('position','F')->count(),
            'D'=>$eligible->where('position','D')->count(),
            'G'=>$eligible->where('position','G')->count(),
            'B'=>$rows->filter(fn($p)=>
              (bool)$p->daily_participant
              && (bool)$p->is_bench
              && !(bool)$p->is_ir
              && strtoupper((string)$p->roster_status)!=='MINORS'
            )->count(),
          ];
        };
        $awayPlayingCounts=$activePlayingCounts($awayAll);
        $homePlayingCounts=$activePlayingCounts($homeAll);

        $awayProjectedTotal=$away['daily_projected_fpts']??0;
        $homeProjectedTotal=$home['daily_projected_fpts']??0;
      @endphp

      <details class="matchup-card" data-matchup-key="{{ ($away['id'] ?? $away['slug'] ?? 'away') }}::{{ ($home['id'] ?? $home['slug'] ?? 'home') }}" data-away-team-id="{{ $away['id'] ?? '' }}" data-home-team-id="{{ $home['id'] ?? '' }}">
        <summary class="matchup-summary">
          <div class="matchup-summary-side matchup-summary-away">
            @if($away)
              <div class="matchup-summary-name">
                <div class="matchup-team-name-row matchup-team-name-row-away">
                  @include('teams.partials.team-icon-uploader',['slug'=>$away['slug'],'name'=>$away['name']])
                  <a href="/teams/current/{{ $away['slug'] }}?date={{ $date }}">{{ $away['name'] }}</a>
                </div>
                <div class="matchup-summary-meta matchup-summary-meta-away">
                  <div class="matchup-meta-row">
                    
                    <span class="matchup-playing-counts">
                      @if($awayPlayingCounts['F']>0)<span class="position-count-pill {{ $awayPlayingCounts['F']>=8?'full':'' }}">{{ $awayPlayingCounts['F'] }} Forward{{ $awayPlayingCounts['F']==1?'':'s' }}</span>@endif
                      @if($awayPlayingCounts['D']>0)<span class="position-count-pill {{ $awayPlayingCounts['D']>=4?'full':'' }}">{{ $awayPlayingCounts['D'] }} {{ $awayPlayingCounts['D']==1?'Defenseman':'Defensemen' }}</span>@endif
                      @if($awayPlayingCounts['G']>0)<span class="position-count-pill {{ $awayPlayingCounts['G']>=1?'full':'' }}">{{ $awayPlayingCounts['G'] }} Goaltender{{ $awayPlayingCounts['G']==1?'':'s' }}</span>@endif
                      @if($awayPlayingCounts['B']>0)<span class="position-count-pill bench-count-pill">{{ $awayPlayingCounts['B'] }} Bench</span>@endif
                    </span>
                    
                  </div>
                  <div class="matchup-meta-row matchup-stat-row">
                    <span class="matchup-daily-cats"><span class="matchup-daily-cats-primary">@foreach(['gp'=>'GP','g'=>'G','a'=>'A'] as $key=>$label)@if(($away['today_stats'][$key] ?? 0) != 0)<span>{{ $label }}: {{ $away['today_stats'][$key] }}</span>@endif @endforeach</span><span class="matchup-daily-cats-special">@foreach(['ppg'=>'PPG','shg'=>'SHG','gwg'=>'GWG','w'=>'W','so'=>'SO'] as $key=>$label)@if(($away['today_stats'][$key] ?? 0) != 0)<span>{{ $label }}: {{ $away['today_stats'][$key] }}</span>@endif @endforeach</span></span>
                    <small class="matchup-projected-score">Proj: {{ number_format($awayProjectedTotal,2) }}</small>
                  </div>
                </div>
              </div>
              <span class="matchup-summary-score"><strong class="matchup-week-score {{ $awayWeekClass }}">{{ number_format($away['week_fpts'] ?? 0,0) }}</strong><small class="matchup-day-score {{ $awayDayClass }}">{{ number_format($awayDay,0) }}</small></span>
            @endif
          </div>

          <div class="matchup-summary-vs">VS</div>

          <div class="matchup-summary-side matchup-summary-home">
            @if($home)
              <span class="matchup-summary-score"><strong class="matchup-week-score {{ $homeWeekClass }}">{{ number_format($home['week_fpts'] ?? 0,0) }}</strong><small class="matchup-day-score {{ $homeDayClass }}">{{ number_format($homeDay,0) }}</small></span>
              <div class="matchup-summary-name">
                <div class="matchup-team-name-row matchup-team-name-row-home">
                  <a href="/teams/current/{{ $home['slug'] }}?date={{ $date }}">{{ $home['name'] }}</a>
                  @include('teams.partials.team-icon-uploader',['slug'=>$home['slug'],'name'=>$home['name']])
                </div>
                <div class="matchup-summary-meta matchup-summary-meta-home">
                  <div class="matchup-meta-row matchup-meta-row-home">
                    
                    <span class="matchup-playing-counts">
                      @if($homePlayingCounts['F']>0)<span class="position-count-pill {{ $homePlayingCounts['F']>=8?'full':'' }}">{{ $homePlayingCounts['F'] }} Forward{{ $homePlayingCounts['F']==1?'':'s' }}</span>@endif
                      @if($homePlayingCounts['D']>0)<span class="position-count-pill {{ $homePlayingCounts['D']>=4?'full':'' }}">{{ $homePlayingCounts['D'] }} {{ $homePlayingCounts['D']==1?'Defenseman':'Defensemen' }}</span>@endif
                      @if($homePlayingCounts['G']>0)<span class="position-count-pill {{ $homePlayingCounts['G']>=1?'full':'' }}">{{ $homePlayingCounts['G'] }} Goaltender{{ $homePlayingCounts['G']==1?'':'s' }}</span>@endif
                      @if($homePlayingCounts['B']>0)<span class="position-count-pill bench-count-pill">{{ $homePlayingCounts['B'] }} Bench</span>@endif
                    </span>
                    
                  </div>
                  <div class="matchup-meta-row matchup-meta-row-home matchup-stat-row">
                    <span class="matchup-daily-cats"><span class="matchup-daily-cats-primary">@foreach(['gp'=>'GP','g'=>'G','a'=>'A'] as $key=>$label)@if(($home['today_stats'][$key] ?? 0) != 0)<span>{{ $label }}: {{ $home['today_stats'][$key] }}</span>@endif @endforeach</span><span class="matchup-daily-cats-special">@foreach(['ppg'=>'PPG','shg'=>'SHG','gwg'=>'GWG','w'=>'W','so'=>'SO'] as $key=>$label)@if(($home['today_stats'][$key] ?? 0) != 0)<span>{{ $label }}: {{ $home['today_stats'][$key] }}</span>@endif @endforeach</span></span><small class="matchup-projected-score">Proj: {{ number_format($homeProjectedTotal,2) }}</small>
                  </div>
                </div>
              </div>
            @else
              <span class="matchup-bye">BYE</span>
            @endif
          </div>
        </summary>

        <div class="matchup-expanded">
          @foreach(['Forwards','Defensemen','Goalies','Bench','IR','Minors'] as $sectionName)
            @php
              $awaySectionRows=$alignedSections[$sectionName]['away'];
              $homeSectionRows=$alignedSections[$sectionName]['home'];
              $awaySectionCount=$awaySectionRows->filter()->count();
              $homeSectionCount=$homeSectionRows->filter()->count();
              $isCollapsible=in_array($sectionName,['Bench','IR','Minors'],true);
            @endphp
            @if($awaySectionRows->count() || $homeSectionRows->count())
              @if($isCollapsible)
                <details class="matchup-subsection">
                  <summary class="matchup-section-title matchup-section-toggle">
                    <span class="matchup-section-side matchup-section-side-away">{{ $sectionName }} ({{ $awaySectionCount }})</span>
                    <span class="matchup-section-chevron" aria-hidden="true">▾</span>
                    <span class="matchup-section-side matchup-section-side-home">{{ $sectionName }} ({{ $homeSectionCount }})</span>
                  </summary>
                  <div class="matchup-roster-grid">
                    <div class="matchup-roster-col">
                      @forelse($awaySectionRows as $player)
                        @if($player)
                          @include('teams.partials.current-matchup-player',['player'=>$player])
                        @else
                          <div class="matchup-player-row matchup-player-blank" aria-hidden="true"></div>
                        @endif
                      @empty
                        <div class="matchup-empty">(Empty)</div>
                      @endforelse
                    </div>
                    <div class="matchup-roster-col">
                      @forelse($homeSectionRows as $player)
                        @if($player)
                          @include('teams.partials.current-matchup-player',['player'=>$player])
                        @else
                          <div class="matchup-player-row matchup-player-blank" aria-hidden="true"></div>
                        @endif
                      @empty
                        <div class="matchup-empty">(Empty)</div>
                      @endforelse
                    </div>
                  </div>
                </details>
              @else
                <div class="matchup-section-title matchup-section-split">
                  <span class="matchup-section-side matchup-section-side-away">{{ $sectionName }} ({{ $awaySectionCount }})</span>
                  <span class="matchup-section-side matchup-section-side-home">{{ $sectionName }} ({{ $homeSectionCount }})</span>
                </div>
                <div class="matchup-roster-grid">
                  <div class="matchup-roster-col">
                    @forelse($awaySectionRows as $player)
                      @if($player)
                        @include('teams.partials.current-matchup-player',['player'=>$player])
                      @else
                        <div class="matchup-player-row matchup-player-blank" aria-hidden="true"></div>
                      @endif
                    @empty
                      <div class="matchup-empty">(Empty)</div>
                    @endforelse
                  </div>
                  <div class="matchup-roster-col">
                    @forelse($homeSectionRows as $player)
                      @if($player)
                        @include('teams.partials.current-matchup-player',['player'=>$player])
                      @else
                        <div class="matchup-player-row matchup-player-blank" aria-hidden="true"></div>
                      @endif
                    @empty
                      <div class="matchup-empty">(Empty)</div>
                    @endforelse
                  </div>
                </div>
              @endif
            @endif
          @endforeach
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
.matchup-period-label{margin:4px 0 10px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--muted);display:flex;align-items:center;justify-content:space-between;gap:10px}.matchup-period-updated{font-size:10px;font-weight:700;letter-spacing:0;text-transform:none;white-space:nowrap}
.current-matchup-list{display:flex;flex-direction:column;gap:7px;margin-bottom:20px}
.matchup-card.notification-team-matchup>.matchup-summary{background:#eaf5ff}.matchup-card.notification-team-matchup[open]>.matchup-summary{background:#eaf5ff}html[data-theme="dark"] .matchup-card.notification-team-matchup>.matchup-summary{background:#123452}.matchup-card{border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden}.matchup-card[open]{border:3px solid #2563eb}
.matchup-side-header{display:none}.matchup-summary{display:grid;grid-template-columns:minmax(0,1fr) 38px minmax(0,1fr);align-items:center;gap:8px;padding:9px 14px;cursor:pointer;list-style:none;background:#fff}
.matchup-summary::-webkit-details-marker{display:none}
.matchup-summary-side{display:flex;align-items:center;justify-content:space-between;gap:10px;min-width:0}
.matchup-summary-home{text-align:right;justify-content:flex-end}.matchup-summary-home .matchup-summary-score{margin-right:auto;text-align:left}\n.matchup-summary-home .matchup-summary-name{margin-left:auto;width:auto;max-width:100%;align-items:flex-end;text-align:right;flex:0 1 auto}\n.matchup-summary-home .matchup-summary-name>a{display:block;width:100%;text-align:right}
.matchup-summary-name{min-width:0;display:flex;flex-direction:column}
.matchup-team-name-row{display:flex;align-items:center;gap:7px;min-width:0}
.matchup-team-name-row-away{justify-content:flex-start}
.matchup-team-name-row-home{justify-content:flex-end}
.matchup-team-name-row .team-icon-uploader{flex:0 0 auto}
.matchup-team-name-row .team-icon-uploader img{width:34px;height:34px;border-radius:9px;box-shadow:0 1px 5px rgba(15,23,42,.14)}
.matchup-summary-name a{font-weight:900;color:var(--text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.matchup-summary-name a:hover{text-decoration:underline}
.matchup-card[open] .matchup-summary-name a{text-decoration:underline;text-underline-offset:2px}
.matchup-summary-name small{font-size:9px;color:var(--muted);font-weight:800;letter-spacing:.05em}.matchup-summary-meta{display:flex;flex-direction:column;align-items:flex-start;gap:2px;min-width:0}.matchup-summary-meta-home{align-items:flex-end}\n.matchup-summary-home .matchup-summary-name{align-items:flex-end;text-align:right}.matchup-meta-row{display:flex;align-items:center;gap:7px;min-width:0}.matchup-meta-row-home{justify-content:flex-end}.matchup-stat-row{line-height:1.1;padding-top:4px}.matchup-live-games{font-size:8px;font-weight:900;color:#b45309;white-space:nowrap}.matchup-playing-counts{display:inline-flex;align-items:center;gap:4px;white-space:nowrap;flex-wrap:wrap}.position-count-pill{display:inline-flex;align-items:center;padding:2px 6px;border:1px solid #cbd5e1;border-radius:999px;background:#f8fafc;color:#111827;font-size:9px;font-weight:700;line-height:1.1}.position-count-pill.full{border-color:#2563eb;background:#2563eb;color:#fff}.position-count-pill.bench-count-pill{border-color:#dc2626;background:#dc2626;color:#fff}.matchup-projected-score{display:block;font-size:11px!important;font-weight:600!important;color:#b45309!important;line-height:1.15;white-space:nowrap;margin-top:4px;text-align:inherit}.matchup-summary-meta-home{justify-content:flex-end}.matchup-daily-cats{font-size:9px;color:#475569;font-weight:900;display:flex;flex-direction:column;gap:2px;align-items:flex-start}.matchup-summary-meta-home .matchup-daily-cats{align-items:flex-end}.matchup-daily-cats-primary,.matchup-daily-cats-special{display:flex;gap:5px;white-space:nowrap;min-height:10px}.matchup-side-pill{display:inline-flex;align-items:center;justify-content:center;padding:2px 7px;border-radius:999px;font-size:9px;font-weight:900;letter-spacing:.05em;line-height:1.1;border:1px solid transparent;white-space:nowrap}.away-pill{background:#fef3c7;color:#92400e;border-color:#fcd34d}.home-pill{background:#dcfce7;color:#166534;border-color:#86efac}
.matchup-summary-score{display:grid;grid-template-columns:auto auto;grid-template-rows:auto;align-items:start;column-gap:5px;white-space:nowrap;position:relative;padding-top:0;padding-bottom:0}.matchup-week-score{font-size:28px;line-height:1;font-weight:500;color:#111827;grid-column:1;grid-row:1}.matchup-day-score{font-size:21px;line-height:1;font-weight:500;color:#111827;grid-column:2;grid-row:1;transform:translateY(-2px)}.matchup-week-score.score-up,.matchup-day-score.score-up{color:#16834f!important}.matchup-week-score.score-down,.matchup-day-score.score-down{color:#dc2626!important}.matchup-week-score.score-same,.matchup-day-score.score-same{color:#111827!important}
.matchup-summary-vs{text-align:center;font-size:10px;font-weight:900;color:var(--muted)}
.matchup-bye{font-size:11px;font-weight:900;color:var(--muted)}
.matchup-expanded{border-top:1px solid var(--line);background:#fff}
.matchup-section-title{grid-column:1/-1;text-align:center;background:#e5e7eb;color:#374151;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;padding:5px 8px;border-top:1px solid var(--line);border-bottom:1px solid var(--line)}
.matchup-section-title{text-align:left!important;padding:7px 12px}.matchup-section-split{display:grid;grid-template-columns:1fr 1fr;align-items:center}.matchup-section-side{display:block}.matchup-section-side-away{text-align:left}.matchup-section-side-home{text-align:right}.matchup-subsection{margin:0}.matchup-section-toggle{display:grid;grid-template-columns:1fr auto 1fr;align-items:center;cursor:pointer;list-style:none}.matchup-section-toggle::-webkit-details-marker{display:none}.matchup-section-chevron{margin-left:auto;font-size:11px;transition:transform .15s ease}.matchup-subsection[open] .matchup-section-chevron{transform:rotate(180deg)}.matchup-roster-grid{display:grid;grid-template-columns:1fr 1fr}
.matchup-roster-col{min-width:0;border-right:1px solid var(--line)}
.matchup-roster-col:last-child{border-right:0}
.matchup-player-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-bottom:1px solid var(--line);min-height:58px}
.matchup-player-row:last-child{border-bottom:0}.matchup-player-blank{min-height:58px;background:transparent!important}
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
html[data-theme="dark"] .matchup-card{background:#0f1c2b;border-color:#334155}html[data-theme="dark"] .matchup-card[open]{border-color:#3b82f6}
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
@media(max-width:800px){.matchup-summary-side{position:relative;padding-bottom:18px}.matchup-summary-side .matchup-projected-score{position:absolute;bottom:0;margin:0}.matchup-summary-away .matchup-projected-score{left:0;right:auto;text-align:left}.matchup-summary-home .matchup-projected-score{left:auto;right:0;text-align:right}.matchup-summary{position:relative;padding-top:25px!important}.matchup-side-header{display:block;position:absolute;top:6px;font-size:9px;font-weight:900;letter-spacing:.08em;color:#64748b}.matchup-side-header-away{left:9px}.matchup-side-header-home{right:9px}
  .matchup-summary{grid-template-columns:minmax(0,1fr) 26px minmax(0,1fr);padding:8px 10px}
  .matchup-summary-score{font-size:18px}
  .matchup-summary-name a{font-size:12px}
  .matchup-team-name-row{gap:5px}
  .matchup-team-name-row .team-icon-uploader{position:absolute;top:-19px;z-index:3}
  .matchup-team-name-row-away .team-icon-uploader{right:0}
  .matchup-team-name-row-home .team-icon-uploader{left:0}
  .matchup-team-name-row .team-icon-uploader img{width:42px;height:42px;border-radius:10px}
  .matchup-team-name-row-away{padding-right:48px}
  .matchup-team-name-row-home{padding-left:48px}
  .matchup-summary-side{position:relative;align-items:flex-start;padding-bottom:22px}
  .matchup-summary-score{position:absolute;right:0;bottom:2px;z-index:2}
  .matchup-summary-home .matchup-summary-score{left:0;right:auto}
  .matchup-summary-meta{width:100%}
  .matchup-meta-row{max-width:100%;flex-wrap:wrap}
  .matchup-live-games{font-size:7px;line-height:1;white-space:normal;max-width:78px}
  .matchup-summary-home .matchup-live-games{text-align:right}
  .matchup-summary-meta{gap:4px}
  .matchup-playing-counts{display:none!important}
  .matchup-daily-cats{font-size:10px;color:#475569;max-width:190px;overflow:visible}.matchup-daily-cats-primary,.matchup-daily-cats-special{white-space:nowrap}
  .matchup-roster-grid{grid-template-columns:1fr 1fr}
  .matchup-player-row{padding:7px 8px;align-items:flex-start}
  .matchup-player-name{font-size:13px;line-height:1.18}
  .team-contract-sticker{display:none!important}
  .matchup-player-opponent{gap:5px;font-size:12px;flex-wrap:nowrap;white-space:nowrap}
  .matchup-player-opponent .team-playing-text{white-space:nowrap}
  .matchup-player-cats{position:absolute;left:8px;bottom:7px;font-size:11px;white-space:nowrap;display:flex;flex-direction:column;align-items:flex-start;line-height:1.15}.matchup-player-cats-primary,.matchup-player-cats-special{display:block;white-space:nowrap;min-height:13px}
  .matchup-player-opponent{padding-bottom:0}
  .matchup-player-row{position:relative;padding-bottom:38px}
  .matchup-player-metrics{gap:6px}
  .matchup-player-metrics>div:first-child{position:absolute;right:8px;bottom:7px;display:flex;align-items:baseline;gap:4px;color:#4b5563}
  .matchup-player-metrics>div:first-child span{display:inline;color:#4b5563!important;font-size:9px}
  .matchup-player-metrics>div:first-child strong{display:inline;color:#4b5563!important;font-size:13px}
  .matchup-player-metrics span{font-size:13px}
  .matchup-player-metrics strong{font-size:17px}
  .matchup-player-today span{font-size:13px}
  .matchup-player-today strong{font-size:21px}
}
.matchup-player-row.team-game-finished-row{background:#fffbea!important}
html[data-theme="dark"] .matchup-player-row.team-game-finished-row{background:#3a3217!important}

/* 2026-10 mobile matchup redesign */
@media(max-width:800px){
  .current-matchup-list{gap:10px}
  .matchup-card{border-radius:20px;box-shadow:0 2px 8px rgba(15,23,42,.04)}
  .matchup-summary{display:grid;grid-template-columns:minmax(0,1fr) 62px minmax(0,1fr);grid-template-rows:auto auto auto;gap:5px 8px;padding:12px 12px 10px!important;min-height:178px;align-items:start}
  .matchup-side-header{top:9px;font-size:9px}.matchup-side-header-away{left:14px}.matchup-side-header-home{right:14px}
  .matchup-summary-side{display:contents;padding:0}
  .matchup-summary-name{min-width:0;width:100%!important;max-width:none!important;padding-top:15px}
  .matchup-summary-away .matchup-summary-name{grid-column:1;grid-row:1/4;align-items:flex-start!important;text-align:left!important;padding-right:0}
  .matchup-summary-home .matchup-summary-name{grid-column:3;grid-row:1/4;align-items:flex-end!important;text-align:right!important;padding-left:0}
  .matchup-team-name-row{display:flex;flex-direction:column;align-items:flex-start!important;gap:6px;width:100%;padding:0!important}
  .matchup-team-name-row-home{align-items:flex-end!important}
  .matchup-team-name-row .team-icon-uploader{position:static!important;order:-1;z-index:auto}
  .matchup-team-name-row .team-icon-uploader img{width:62px;height:62px;border-radius:15px;box-shadow:0 3px 10px rgba(15,23,42,.15)}
  .matchup-summary-name a{font-size:13px;line-height:1.12;white-space:normal!important;overflow:visible!important;text-overflow:clip!important;display:block;width:100%;font-weight:900}
  .matchup-team-name-row-home a{text-align:right!important}
  .matchup-summary-meta{width:100%;gap:5px;margin-top:2px}
  .matchup-meta-row{display:block;max-width:100%}
  .matchup-live-games{display:block;font-size:9px;line-height:1.1;max-width:none;color:#b45309;margin-top:1px}
  .matchup-summary-home .matchup-live-games{text-align:right}
  .matchup-stat-row{padding-top:2px!important}
  .matchup-daily-cats{font-size:9px;line-height:1.15;gap:3px;max-width:none!important;color:#475569}
  .matchup-daily-cats-primary,.matchup-daily-cats-special{gap:5px;white-space:nowrap}
  .matchup-summary-score{position:static!important;display:flex;align-items:flex-start;justify-content:center;gap:3px;padding:0;margin:0!important;z-index:auto}
  .matchup-summary-away .matchup-summary-score{grid-column:2;grid-row:1;align-self:end}
  .matchup-summary-home .matchup-summary-score{grid-column:2;grid-row:1;align-self:end}
  .matchup-summary-away .matchup-summary-score{transform:translateX(-28px)}
  .matchup-summary-home .matchup-summary-score{transform:translateX(28px)}
  .matchup-week-score{font-size:33px;line-height:.95;font-weight:600}.matchup-day-score{font-size:20px;line-height:1;font-weight:600;transform:translateY(-3px)}
  .matchup-summary-vs{grid-column:2;grid-row:2;align-self:center;font-size:10px;padding-top:3px}
  .matchup-summary-side .matchup-projected-score{position:static!important;display:block;margin-top:4px!important;padding:4px 7px;border-radius:7px;background:#fff1e6;color:#b45309!important;font-size:11px!important;font-weight:800!important;width:max-content}
  .matchup-summary-home .matchup-projected-score{margin-left:auto!important;text-align:right}
  .matchup-playing-counts{display:none!important}
}
html[data-theme="dark"] .matchup-summary-side .matchup-projected-score{background:#3b291d;color:#fdba74!important}

/* compact mobile matchup override */
@media(max-width:800px){
  .current-matchup-list{gap:7px}
  .matchup-summary{grid-template-columns:minmax(0,1fr) 76px minmax(0,1fr);grid-template-rows:auto auto;min-height:0;padding:9px 12px 8px!important;gap:3px 6px;align-items:start}
  .matchup-side-header{top:7px}
  .matchup-summary-name{padding-top:12px}
  .matchup-team-name-row{display:grid!important;grid-template-columns:48px minmax(0,1fr);grid-template-rows:auto;align-items:center!important;gap:6px!important}
  .matchup-team-name-row-home{grid-template-columns:minmax(0,1fr) 48px}
  .matchup-team-name-row .team-icon-uploader{grid-row:1;width:48px}
  .matchup-team-name-row-away .team-icon-uploader{grid-column:1}.matchup-team-name-row-away a{grid-column:2}
  .matchup-team-name-row-home .team-icon-uploader{grid-column:2}.matchup-team-name-row-home a{grid-column:1}
  .matchup-team-name-row .team-icon-uploader img{width:48px;height:48px;border-radius:12px}
  .matchup-summary-name a{font-size:12px;line-height:1.08}
  .matchup-summary-meta{margin-top:3px;gap:2px}
  .matchup-live-games{font-size:8px}
  .matchup-daily-cats{font-size:9px;gap:2px}
  .matchup-summary-away .matchup-summary-score,.matchup-summary-home .matchup-summary-score{grid-column:2;grid-row:1;align-self:start;margin-top:8px!important;transform:none}
  .matchup-summary-away .matchup-summary-score{justify-content:flex-start}.matchup-summary-home .matchup-summary-score{justify-content:flex-end}
  .matchup-week-score{font-size:31px}.matchup-day-score{font-size:19px}
  .matchup-summary-vs{grid-column:2;grid-row:1;align-self:start;margin-top:43px;padding:0;font-size:9px}
  .matchup-summary-side .matchup-projected-score{margin-top:3px!important;padding:3px 6px;font-size:10px!important}
}

/* mobile matchup layout: team name row, logo below, score opposite logo */
@media(max-width:800px){
  .matchup-summary{grid-template-columns:minmax(0,1fr) 22px minmax(0,1fr);grid-template-rows:auto;gap:4px 5px;padding:9px 12px 8px!important}
  .matchup-summary-side{display:flex!important;position:relative!important;flex-direction:column;align-items:stretch!important;padding:15px 0 0!important;min-width:0}
  .matchup-summary-away{grid-column:1;grid-row:1}.matchup-summary-home{grid-column:3;grid-row:1}
  .matchup-summary-name,.matchup-summary-away .matchup-summary-name,.matchup-summary-home .matchup-summary-name{display:flex!important;flex-direction:column;width:100%!important;max-width:none!important;padding:0!important;align-items:stretch!important;text-align:inherit!important}
  .matchup-team-name-row,.matchup-team-name-row-home{display:flex!important;flex-direction:column!important;align-items:stretch!important;width:100%;gap:5px!important;padding:0!important}
  .matchup-team-name-row a{order:-2;width:100%!important;min-height:28px;font-size:12px;line-height:1.08;white-space:normal!important;overflow:visible!important;text-overflow:clip!important}
  .matchup-team-name-row-home a{text-align:right!important}
  .matchup-team-name-row .team-icon-uploader{position:static!important;order:-1!important;width:48px!important;align-self:flex-start}
  .matchup-team-name-row-home .team-icon-uploader{align-self:flex-end}
  .matchup-team-name-row .team-icon-uploader img{width:48px;height:48px}
  .matchup-summary-score,.matchup-summary-away .matchup-summary-score,.matchup-summary-home .matchup-summary-score{position:absolute!important;top:48px!important;bottom:auto!important;transform:none!important;margin:0!important;display:flex;gap:3px;z-index:2}
  .matchup-summary-away .matchup-summary-score{right:0!important;left:auto!important}
  .matchup-summary-home .matchup-summary-score{left:0!important;right:auto!important}
  .matchup-week-score{font-size:31px}.matchup-day-score{font-size:19px}
  .matchup-summary-vs{grid-column:2;grid-row:1;align-self:start;margin-top:69px;padding:0;font-size:9px}
  .matchup-summary-meta{margin-top:4px}
}

@media(max-width:800px){
  .matchup-summary{padding:7px 12px 6px!important}
  .matchup-summary-side{padding-top:12px!important}
  .matchup-team-name-row{gap:3px!important}
  .matchup-team-name-row a{min-height:25px}
  .matchup-summary-score,.matchup-summary-away .matchup-summary-score,.matchup-summary-home .matchup-summary-score{top:42px!important}
  .matchup-summary-vs{margin-top:62px}
  .matchup-summary-meta{margin-top:1px!important;gap:1px}
  .matchup-stat-row{padding-top:0!important}
  .matchup-summary-side .matchup-projected-score{margin-top:2px!important;padding:2px 6px}
}

@media(max-width:800px){.matchup-summary-side{padding-top:3px!important}.matchup-summary-score,.matchup-summary-away .matchup-summary-score,.matchup-summary-home .matchup-summary-score{top:33px!important}.matchup-summary-vs{margin-top:53px}.matchup-side-header{display:none!important}}

@media(max-width:800px){
  .matchup-summary-side .matchup-projected-score{position:absolute!important;top:62px!important;bottom:auto!important;margin:0!important;z-index:2}
  .matchup-summary-away .matchup-projected-score{right:0!important;left:auto!important;text-align:right}
  .matchup-summary-home .matchup-projected-score{left:0!important;right:auto!important;text-align:left}
  .matchup-summary-meta{margin-top:1px!important}
}

/* Keep unchanged scores readable in dark mode while preserving red/green movement colors. */
html[data-theme="dark"] .matchup-week-score.score-same,
html[data-theme="dark"] .matchup-day-score.score-same{color:#f8fafc!important}

@media(max-width:800px){
  .matchup-card[open] .matchup-summary{min-height:0}
  .matchup-player-row{overflow:hidden;min-width:0}
  .matchup-player-main{width:100%;padding-right:34px}
  .matchup-player-name{display:block!important;font-size:12px;line-height:1.15}
  .matchup-player-name strong{display:block;overflow-wrap:anywhere}
  .matchup-player-opponent{display:block!important;font-size:10px;line-height:1.15;white-space:normal!important;overflow:hidden}
  .matchup-player-opponent .team-playing-text,.matchup-player-opponent .team-away,.matchup-player-opponent .team-home{display:block;white-space:normal!important;overflow-wrap:anywhere}
  .matchup-player-cats{font-size:9px!important}
  .matchup-player-metrics>div:first-child{right:7px!important}
}

@media(max-width:800px){
  .matchup-player-main{padding-right:42px!important;overflow:hidden}
  .matchup-player-name{display:block!important;white-space:nowrap!important;overflow:hidden!important;font-size:11px!important;line-height:1.15}
  .matchup-player-name strong{display:block!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;overflow-wrap:normal!important}
  .matchup-player-opponent{display:block!important;white-space:nowrap!important;overflow:hidden!important;font-size:9px!important;line-height:1.2}
  .matchup-player-opponent .team-playing-text,.matchup-player-opponent .team-away,.matchup-player-opponent .team-home{display:block!important;white-space:nowrap!important;overflow:hidden!important;text-overflow:clip!important;overflow-wrap:normal!important}
  .matchup-player-metrics>div:first-child{right:7px!important;bottom:7px!important;gap:3px!important}
  .matchup-player-metrics>div:first-child span{font-size:9px!important}
  .matchup-player-metrics>div:first-child strong{font-size:21px!important;line-height:1!important;font-weight:900!important}
}

@media(max-width:800px){
  /* Let roster text use the full half-column. Day score sits below it, not beside it. */
  .matchup-player-main{width:100%!important;padding-right:0!important;overflow:visible!important}
  .matchup-player-name{width:100%;overflow:visible!important;font-size:10px!important}
  .matchup-player-name strong{width:100%;overflow:visible!important;text-overflow:clip!important;font-size:10px!important;letter-spacing:-.02em}
  .matchup-player-opponent{width:100%;overflow:visible!important;font-size:8px!important;letter-spacing:-.02em}
  .matchup-player-opponent .team-playing-text,.matchup-player-opponent .team-away,.matchup-player-opponent .team-home{overflow:visible!important;text-overflow:clip!important}
  .matchup-player-row{padding:7px 8px 28px!important;min-height:0!important}
  .matchup-player-metrics>div:first-child{right:8px!important;bottom:14px!important}
}

/* Expanded roster game-state colors */
.matchup-player-row.team-game-live-row{background:#ecfdf5!important}
.matchup-player-row.team-game-finished-row{background:#f1f5f9!important}
.matchup-player-row.team-game-upcoming-row{background:var(--game-upcoming)!important}
html[data-theme="dark"] .matchup-player-row.team-game-live-row{background:#12372b!important}
html[data-theme="dark"] .matchup-player-row.team-game-finished-row{background:#1e293b!important}
html[data-theme="dark"] .matchup-player-row.team-game-upcoming-row{background:var(--game-upcoming)!important}
/* Stack logos under names inside the existing identity area; scores keep their own layout. */
.matchup-summary .matchup-team-name-row{display:flex!important;flex-direction:column!important;gap:6px;position:relative;min-width:0}
.matchup-summary .matchup-team-name-row-away{align-items:flex-start!important;justify-content:flex-start!important}
.matchup-summary .matchup-team-name-row-home{align-items:flex-end!important;justify-content:flex-start!important}
.matchup-summary .matchup-team-name-row .team-icon-uploader{position:static!important;order:1!important;align-self:auto!important;width:64px!important;flex:0 0 auto!important}
.matchup-summary .matchup-team-name-row .team-icon-uploader img{width:64px!important;height:64px!important}
.matchup-summary .matchup-team-name-row a{order:0!important;min-width:0;white-space:normal!important;overflow-wrap:anywhere}
@media(max-width:800px){
 .matchup-summary .matchup-team-name-row .team-icon-uploader{width:56px!important}
 .matchup-summary .matchup-team-name-row .team-icon-uploader img{width:56px!important;height:56px!important}
}
@media(max-width:380px){
 .matchup-summary .matchup-team-name-row .team-icon-uploader{width:48px!important}
 .matchup-summary .matchup-team-name-row .team-icon-uploader img{width:48px!important;height:48px!important}
}
</style>

<script>
document.addEventListener('DOMContentLoaded',()=>{
  const stateKey='ecfhl-open-matchups:'+location.pathname+location.search;
  const cards=[...document.querySelectorAll('.matchup-card[data-matchup-key]')];

  const highlightNotificationTeam=()=>{
    const selected=localStorage.getItem('ecfhl-notification-team-id')||'';
    let selectedCard=null;
    cards.forEach(card=>{
      const matches=!!selected && (card.dataset.awayTeamId===selected || card.dataset.homeTeamId===selected);
      card.classList.toggle('notification-team-matchup',matches);
      if(matches)selectedCard=card;
    });
    if(selectedCard){
      const list=selectedCard.parentElement;
      const firstCard=list?.querySelector('.matchup-card');
      if(list && firstCard && firstCard!==selectedCard)list.insertBefore(selectedCard,firstCard);
    }
  };
  highlightNotificationTeam();
  const teamSelect=document.getElementById('push-team-select');
  teamSelect?.addEventListener('change',()=>setTimeout(highlightNotificationTeam,0));

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

  const autoRefresh={{ $autoRefresh ? 'true' : 'false' }};
  if(autoRefresh){
    setInterval(()=>{
      saveOpenMatchups();
      location.reload();
    },60000);
  }

  document.querySelectorAll('.matchup-summary-name a').forEach(link=>{
    link.addEventListener('click',event=>{
      const card=link.closest('.matchup-card');
      if(!card?.open){
        event.preventDefault();
        event.stopPropagation();
        card.open=true;
        saveOpenMatchups();
        return;
      }
      event.stopPropagation();
    });
  });
});
</script>
@endsection

<style>
.matchup-team-name-row .team-icon-uploader,
.matchup-team-name-row .team-icon-uploader img{
  background:transparent!important;
  border:0!important;
  border-radius:0!important;
  box-shadow:none!important;
}
.matchup-team-name-row .team-icon-uploader img{object-fit:contain!important}
</style>
