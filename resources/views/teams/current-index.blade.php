@extends('layouts.app')
@section('content')
<link rel="stylesheet" href="/matchup-scoreboard.css?v={{ hash_file('sha256', base_path('public/matchup-scoreboard.css')) }}">
<div class="page-head current-teams-head">
  <div class="shell live-scoring-heading">
    <div class="live-scoring-meta"><div class="eyebrow">2026-27 season</div><span class="page-date-badge">{{ \Carbon\CarbonImmutable::parse($date)->format('l, M j') }}</span></div>
    <h1><span class="live-scoring-indicator" aria-hidden="true">●</span> Live Scoring</h1>
  </div>
</div>

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
            'Defense'=>$playing->filter(fn($p)=>
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
        foreach(['Forwards','Defense','Goalies','Bench','IR','Minors'] as $sectionName){
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

      <details class="matchup-card ecfhl-scoreboard {{ request()->user()?->claim?->team_name && in_array(request()->user()->claim->team_name, [$away['name'] ?? '', $home['name'] ?? ''], true) ? 'notification-team-matchup' : '' }}" data-matchup-key="{{ ($away['id'] ?? $away['slug'] ?? 'away') }}::{{ ($home['id'] ?? $home['slug'] ?? 'home') }}" data-away-team-slug="{{ $away['slug'] ?? \Illuminate\Support\Str::slug($away['name'] ?? '') }}" data-home-team-slug="{{ $home['slug'] ?? \Illuminate\Support\Str::slug($home['name'] ?? '') }}" data-away-team-id="{{ $away['id'] ?? '' }}" data-home-team-id="{{ $home['id'] ?? '' }}">
        <summary class="matchup-summary team-live-matchup-summary">
<div class="team-live-side team-live-score-left">
@if($away)
<div class="team-live-name-row">
<a href="/teams/current/{{ $away['slug'] }}?date={{ $date }}">{{ $away['name'] }}</a>
<button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $away['slug'] }}" data-team-name="{{ $away['name'] }}" aria-label="View {{ $away['name'] }} logo"><img src="{{ \App\Support\TeamImages::url($away['slug'],160) }}" data-full-src="{{ \App\Support\TeamImages::url($away['slug']) }}" alt="{{ $away['name'] }} team icon" width="160" height="160" loading="lazy" decoding="async"></button>
</div>
<div class="team-live-body"><div class="team-live-scores">
<span class="team-live-score team-live-weekly"><strong class="{{ $awayWeekClass }}">{{ number_format($away['week_fpts'] ?? 0,0) }}</strong><small>Weekly</small></span>
<span class="team-live-score team-live-today"><strong class="{{ $awayDayClass }}">{{ number_format($awayDay,0) }}</strong><small>Daily</small></span>
</div></div>
@else<span class="matchup-bye">BYE</span>@endif
</div><div class="team-live-vs">VS</div><div class="team-live-side team-live-side-right team-live-score-right">
@if($home)
<div class="team-live-name-row">
<a href="/teams/current/{{ $home['slug'] }}?date={{ $date }}">{{ $home['name'] }}</a>
<button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $home['slug'] }}" data-team-name="{{ $home['name'] }}" aria-label="View {{ $home['name'] }} logo"><img src="{{ \App\Support\TeamImages::url($home['slug'],160) }}" data-full-src="{{ \App\Support\TeamImages::url($home['slug']) }}" alt="{{ $home['name'] }} team icon" width="160" height="160" loading="lazy" decoding="async"></button>
</div>
<div class="team-live-body"><div class="team-live-scores">
<span class="team-live-score team-live-weekly"><strong class="{{ $homeWeekClass }}">{{ number_format($home['week_fpts'] ?? 0,0) }}</strong><small>Weekly</small></span>
<span class="team-live-score team-live-today"><strong class="{{ $homeDayClass }}">{{ number_format($homeDay,0) }}</strong><small>Daily</small></span>
</div></div>
@else<span class="matchup-bye">BYE</span>@endif
</div>
</summary>

        <div class="matchup-expanded">
          @foreach(['Forwards','Defense','Goalies','Bench','IR','Minors'] as $sectionName)
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
                  <summary class="matchup-section-title matchup-section-toggle roster-table-heading" data-roster-section="{{ $sectionName }}">
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
                <div class="matchup-section-title matchup-section-split roster-table-heading" data-roster-section="{{ $sectionName }}">
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
  <footer class="compact-page-footer live-scoring-footer">
    @if($scoreLastUpdate)<span class="team-updated">Updated @include('partials.updated-time',['value'=>$scoreLastUpdate])</span>@endif
    <a class="live-scoring-fantrax" target="_blank" rel="noopener noreferrer" href="https://www.fantrax.com/fantasy/league/{{ rawurlencode(\App\Support\LiveScoring\FantraxClient::LEAGUE_ID) }}/livescoring"><img src="/fantrax-icon.png" width="16" height="16" alt="">Fantrax ↗</a>
  </footer>
</div>

<style>
.current-teams-head{padding:16px 0 8px}
.current-teams-head .live-scoring-meta{display:flex;align-items:center;justify-content:space-between;gap:8px;min-width:0}
.current-teams-head .eyebrow{margin:0;font-size:11px;letter-spacing:.12em;white-space:nowrap}
.current-teams-head .page-date-badge{font-size:11px;padding:4px 8px}
.current-teams-head h1{display:flex;align-items:center;gap:7px;white-space:nowrap;font-size:clamp(28px,7vw,36px);line-height:1.12;margin:6px 0 0}
.live-scoring-indicator{color:#c94b52;font-size:.55em}
@media(min-width:901px){.current-teams-head .live-scoring-heading{display:flex;align-items:center;justify-content:space-between;gap:18px}.current-teams-head .live-scoring-meta{order:1;justify-content:flex-end;gap:12px}.current-teams-head h1{font-size:27px;margin:0}}
.current-teams-head p{margin-top:4px}
.team-updated{font-size:12px;opacity:.8;margin-top:3px}
.current-teams-page .live-scoring-footer{gap:8px;padding:7px 0;font-size:11px;flex-wrap:wrap}
.live-scoring-footer .team-updated{margin:0;font-size:11px;line-height:1.3}
.live-scoring-fantrax{display:inline-flex;align-items:center;gap:5px;white-space:nowrap;text-decoration:none;margin-left:auto;font-weight:700;line-height:20px}
.live-scoring-fantrax:hover{text-decoration:underline}
body:has(.current-teams-page) .site-footer{margin-top:8px;padding:12px 0}
body:has(.current-teams-page) .footer-inner{flex-direction:row;align-items:center;flex-wrap:wrap;gap:6px 14px;font-size:10px}
body:has(.current-teams-page) .mobile-nav-live .mobile-nav-label{white-space:nowrap;overflow-wrap:normal;font-size:clamp(8px,2.4vw,11px)}
.current-teams-page{--matchup-shadow:0 2px 4px rgba(15,45,75,.08),0 6px 14px rgba(15,45,75,.10)}
html[data-theme="dark"] .current-teams-page{--matchup-shadow:0 2px 5px rgba(0,0,0,.25),0 7px 16px rgba(0,0,0,.30)}
.team-toolbar{margin:4px 0 10px}
.team-date-buttons{display:flex;gap:6px;margin:4px 0}
.team-date-buttons .button{padding:6px 10px;font-size:12px}
.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}
.matchup-period-label{margin:4px 0 10px;font-size:11px;font-weight:900;text-transform:uppercase;letter-spacing:.06em;color:var(--muted)}
.current-matchup-list{display:flex;flex-direction:column;gap:7px;margin-bottom:6px}
.matchup-card{border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden}
.current-teams-page .matchup-card.ecfhl-scoreboard{box-shadow:var(--matchup-shadow)!important}
.current-teams-page .matchup-card.notification-team-matchup{border:3px solid #3b82f6!important;background:#eaf5ff!important;box-shadow:0 0 0 1px rgba(59,130,246,.18),var(--matchup-shadow)!important}
.current-teams-page .matchup-card.notification-team-matchup>.matchup-summary{background:#eaf5ff!important}
.current-teams-page .matchup-card[open]{border:3px solid #f97316!important;box-shadow:0 0 0 1px rgba(249,115,22,.18),var(--matchup-shadow)!important}
html[data-theme="dark"] .current-teams-page .matchup-card.notification-team-matchup{border-color:#60a5fa!important;background:#123452!important}
html[data-theme="dark"] .current-teams-page .matchup-card.notification-team-matchup>.matchup-summary{background:#123452!important}
html[data-theme="dark"] .current-teams-page .matchup-card[open]{border-color:#fb923c!important}
.matchup-card.notification-team-matchup>.matchup-summary{background:#eaf5ff}
.matchup-summary{display:grid;grid-template-columns:minmax(0,1fr) 22px minmax(0,1fr);gap:5px;padding:10px 14px;cursor:pointer;list-style:none}
.matchup-summary::-webkit-details-marker{display:none}
.matchup-summary-side{position:relative;display:flex;flex-direction:column;min-width:0}
.matchup-summary-name{min-width:0}
.matchup-team-name-row{display:flex;flex-direction:column;gap:6px;align-items:flex-start;min-width:0;max-width:100%}
.matchup-team-name-row-away a{order:-1}
.matchup-team-name-row-home{align-items:flex-end;width:100%}
.matchup-team-name-row-home .team-icon-uploader{align-self:flex-end!important}
.matchup-team-name-row-home a{text-align:right}
.matchup-team-name-row a{font-weight:900;color:var(--text);text-decoration:none;white-space:nowrap!important;overflow:hidden!important;text-overflow:ellipsis!important;display:block;max-width:100%}
.matchup-team-name-row .team-icon-uploader{width:64px}
.matchup-team-name-row .team-icon-uploader img{display:block;width:64px;height:64px;object-fit:contain}
.matchup-summary-home{text-align:right}
.matchup-summary-score{display:flex;gap:3px;align-items:flex-start}
.matchup-week-score{font-size:33px;line-height:.95;font-weight:600}
.matchup-day-score{font-size:20px;line-height:1;font-weight:600;transform:translateY(-3px)}
.matchup-week-score.score-up,.matchup-day-score.score-up{color:#16834f!important}
.matchup-week-score.score-down,.matchup-day-score.score-down{color:#dc2626!important}
.matchup-summary-vs{text-align:center;align-self:center;font-size:10px;font-weight:900;color:var(--muted)}
.matchup-projected-score{display:inline-block;margin-top:10px;padding:3px 7px;border-radius:7px;background:#fff1e6;color:#b45309!important;font-size:11px!important;font-weight:800!important;width:max-content}
.matchup-summary-home .matchup-projected-score{margin-left:auto}
.matchup-playing-counts,.matchup-side-header{display:none!important}
.matchup-summary-meta{margin-top:4px}
.matchup-live-games,.matchup-daily-cats{font-size:9px;font-weight:800}
.matchup-expanded{border-top:1px solid var(--line)}
.matchup-section-title{display:grid;grid-template-columns:1fr 1fr;padding:7px 12px;background:#e5e7eb;color:#374151;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em}
.matchup-section-side-home{text-align:right}
.matchup-section-toggle{grid-template-columns:1fr auto 1fr;cursor:pointer;list-style:none}
.matchup-section-chevron{font-size:11px}
.matchup-roster-grid{display:grid;grid-template-columns:1fr 1fr}
.matchup-roster-col{min-width:0;border-right:1px solid var(--line)}
.matchup-roster-col:last-child{border-right:0}
.matchup-player-row{position:relative;min-width:0;height:32px;box-sizing:border-box;overflow:hidden;padding:3px 8px 1px;border-bottom:1px solid var(--line)}
.matchup-player-main{width:100%;min-width:0;padding-right:48px;box-sizing:border-box}
.matchup-player-name{display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:15px;line-height:1.1}
.matchup-player-name strong{flex-basis:100%;width:100%;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.matchup-player-name a{text-decoration:none!important}
.matchup-player-name .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}
.matchup-player-opponent{margin-top:3px;padding-right:38px;font-size:10px;line-height:1.15;min-height:18px;display:flex;align-items:flex-start}
.matchup-player-cats{display:flex;gap:5px;font-size:9px;color:var(--muted);font-weight:800}
.matchup-player-metrics{position:absolute;right:8px;top:5px;display:flex;align-items:flex-start}
.matchup-player-today{display:flex;align-items:flex-end;gap:3px}
.matchup-player-today span{font-size:8px;color:var(--muted);font-weight:700}
.matchup-player-today strong{font-size:28px;line-height:1;font-weight:900}
.matchup-player-blank{min-height:44px}
.matchup-empty{padding:12px;color:var(--muted);font-size:12px}
.matchup-player-row.team-game-live-row{background:#e8f7ee!important}
.matchup-player-row.team-game-finished-row{background:#eef1f5!important}
.matchup-player-row.team-game-upcoming-row{background:var(--game-upcoming)!important}
html[data-theme="dark"] .matchup-card{background:#0f1c2b;border-color:#334155}
html[data-theme="dark"] .matchup-card.notification-team-matchup>.matchup-summary{background:#123452}
html[data-theme="dark"] .matchup-summary{background:#0f1c2b}
html[data-theme="dark"] .matchup-week-score.score-same,html[data-theme="dark"] .matchup-day-score.score-same{color:#f8fafc!important}
html[data-theme="dark"] .matchup-projected-score{background:#3b291d;color:#fdba74!important}
html[data-theme="dark"] .matchup-section-title{background:#1f2937;color:#e5e7eb}
html[data-theme="dark"] .matchup-roster-col{border-color:#334155}
html[data-theme="dark"] .matchup-player-row{color:#f8fafc;border-color:#334155}
html[data-theme="dark"] .matchup-player-row.team-game-live-row{background:#123d30!important}
html[data-theme="dark"] .matchup-player-row.team-game-finished-row{background:#263243!important}
html[data-theme="dark"] .matchup-player-row.team-game-upcoming-row{background:var(--game-upcoming)!important}
/* Use the working compact scoreboard layout on desktop as well as mobile. */
@media(min-width:0px){
 .matchup-summary{padding:7px 12px 6px}
 .matchup-summary-side{padding-top:3px}
 .matchup-team-name-row{display:flex;flex-direction:column;gap:6px;align-items:flex-start;min-width:0;max-width:100%}
 .matchup-team-name-row .team-icon-uploader,.matchup-team-name-row .team-icon-uploader img{width:56px;height:56px}
 .matchup-summary-score{position:absolute;top:33px}
 .matchup-summary-away .matchup-summary-score{right:0}
 .matchup-summary-home .matchup-summary-score{left:0}
 .matchup-summary-vs{margin-top:53px}
 .matchup-projected-score{position:absolute;top:68px;margin:0}
 .matchup-summary-away .matchup-projected-score{right:0}
 .matchup-summary-home .matchup-projected-score{left:0}
 .matchup-player-row{height:32px;padding:3px 8px 1px}
 .matchup-player-name{font-size:15px}
 .matchup-player-opponent{font-size:10px;margin-top:3px}
 .matchup-player-metrics{top:5px;bottom:auto}
 .matchup-player-today strong{font-size:28px}
}
</style>

<script src="/live-scoring.js?v={{ hash_file('sha256', base_path('public/live-scoring.js')) }}"></script>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const destination=new URL(location.href);
  const requestedMatchup=destination.searchParams.get('matchup');
  if(requestedMatchup){
    destination.searchParams.delete('matchup');
    history.replaceState(null,'',destination.pathname+destination.search+destination.hash);
  }
  const stateKey='ecfhl-open-matchups:'+location.pathname+location.search;
  const cards=()=>[...document.querySelectorAll('.matchup-card[data-matchup-key]')];

  const accountTeamId=@json((string)(request()->user()?->claim?->fantasy_team_id ?? ''));
  const accountTeamSlug=@json(request()->user()?->claim?->team_name ? \Illuminate\Support\Str::slug(request()->user()->claim->team_name) : '');
  const highlightNotificationTeam=()=>{
    const selected=accountTeamId || localStorage.getItem('ecfhl-notification-team-id') || '';
    let selectedCard=null;
    cards().forEach(card=>{
      const matches=(!!selected && (card.dataset.awayTeamId===selected || card.dataset.homeTeamId===selected)) || (!!accountTeamSlug && (card.dataset.awayTeamSlug===accountTeamSlug || card.dataset.homeTeamSlug===accountTeamSlug));
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
      cards().forEach(card=>card.open=saved.includes(card.dataset.matchupKey));
    }
  }catch(e){}

  const saveOpenMatchups=()=>{
    try{
      const open=cards().filter(card=>card.open).map(card=>card.dataset.matchupKey);
      sessionStorage.setItem(stateKey,JSON.stringify(open));
    }catch(e){}
  };

  if(requestedMatchup){
    const selected=cards().find(card=>card.dataset.awayTeamSlug===requestedMatchup || card.dataset.homeTeamSlug===requestedMatchup);
    if(selected){
      cards().forEach(card=>card.open=card===selected);
      saveOpenMatchups();
      requestAnimationFrame(()=>requestAnimationFrame(()=>{
        selected.scrollIntoView({block:'start'});
      }));
    }
  }

  document.addEventListener('toggle',event=>{if(event.target.matches?.('.matchup-card[data-matchup-key]'))saveOpenMatchups();},true);

  const autoRefresh={{ $autoRefresh ? 'true' : 'false' }};
  if(autoRefresh){
    window.EcfhlLiveScoring.start({saveOpenMatchups,highlightNotificationTeam});
  }

  document.addEventListener('click',event=>{
    const link=event.target.closest('.matchup-summary-name a,.team-live-name-row a');
    if(!link)return;
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
