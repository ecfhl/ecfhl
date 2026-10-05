@extends('layouts.app')
@section('content')
<div class="page-head team-page-head">
  <div class="shell team-page-head-shell">
    <div class="team-page-head-logo">
      @include('teams.partials.team-icon-uploader',['slug'=>$slug,'name'=>$teamName])
    </div>
    <div class="team-page-head-copy">
      <div class="eyebrow">2026-27 roster</div>
      <label class="team-title-switcher">
        <span class="sr-only">Team</span>
        <select aria-label="Team" onchange="if(this.value){const url='/teams/current/'+this.value+'?date={{ $date }}';if(window.navigateToTeam)window.navigateToTeam(url,this.selectedOptions[0].text);else location.href=url;}">
          @foreach($teamChoices as $choice)
            <option value="{{ $choice['slug'] }}" {{ $choice['slug']===$slug?'selected':'' }}>{{ $choice['name'] }}</option>
          @endforeach
        </select>
      </label>
      @if($fantraxTeamUrl)
        <p class="team-fantrax-row">
          <a class="team-fantrax-link" href="{{ $fantraxTeamUrl }}" target="_blank" rel="noopener noreferrer">
            <img src="/fantrax-icon.png" alt="">Fantrax ↗
          </a>
          roster for {{ \Carbon\CarbonImmutable::parse($date)->format('l, M j, Y') }}
        </p>
      @else
        <p>Current Fantrax roster for {{ \Carbon\CarbonImmutable::parse($date)->format('l, M j, Y') }}</p>
      @endif
      @if($scoreLastUpdate)
        <p class="team-updated">Updated @include('partials.updated-time',['value'=>$scoreLastUpdate])</p>
      @endif
    </div>
  </div>
</div>
<div class="shell current-team-page">
  <div class="team-page-controls">
    <div class="team-left-controls">
      <div class="team-date-buttons">
        <a class="button team-date-button {{ $date===$yesterday?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $yesterday }}">Yesterday</a>
        <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $today }}">Today</a>
        <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $tomorrow }}">Tomorrow</a>
      </div>
    </div>
  </div>

  @if($liveMatchup)
    @php
      $teamWeekWinning=$liveMatchup['team_week']>$liveMatchup['opponent_week'];
      $oppWeekWinning=$liveMatchup['opponent_week']>$liveMatchup['team_week'];
      $teamDayWinning=$liveMatchup['team_today']>$liveMatchup['opponent_today'];
      $oppDayWinning=$liveMatchup['opponent_today']>$liveMatchup['team_today'];
    @endphp
    <section class="team-live-matchup" aria-label="Live matchup">
      @if(!empty($liveMatchup['caption']))
        <div class="team-live-matchup-label">{{ $liveMatchup['caption'] }}</div>
      @endif
      <details class="team-live-matchup-card ecfhl-scoreboard">
        <summary class="team-live-matchup-summary">
          <div class="team-live-side team-live-score-left">
            <div class="team-live-name-row">
              <button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $slug }}" data-team-name="{{ $liveMatchup['team_name'] }}" aria-label="View {{ $liveMatchup['team_name'] }} logo"><img class="team-viewer-logo-image" data-full-src="{{ \App\Support\TeamImages::url($slug) }}" src="{{ \App\Support\TeamImages::url($slug,160) }}" width="160" height="160" decoding="async" alt="{{ $liveMatchup['team_name'] }} team icon"></button>
              <a href="/teams/current/{{ $slug }}?date={{ $date }}" onclick="event.stopPropagation()">{{ $liveMatchup['team_name'] }}</a>
            </div>
            <div class="team-live-body">
              
              <div class="team-live-scores">
                <span class="team-live-score team-live-today"><strong class="{{ $teamDayWinning?'winning':'' }}">{{ number_format($liveMatchup['team_today'],0) }}</strong><small>Today</small></span>
                <span class="team-live-score team-live-weekly"><strong class="{{ $teamWeekWinning?'winning':'' }}">{{ number_format($liveMatchup['team_week'],0) }}</strong><small>Weekly</small></span>
              </div>
            </div>
          </div>
          <div class="team-live-vs">VS</div>
          <div class="team-live-side team-live-side-right team-live-score-right">
            <div class="team-live-name-row">
              <a href="/teams/current/{{ \Illuminate\Support\Str::slug($liveMatchup['opponent_name']) }}?date={{ $date }}" onclick="event.stopPropagation()">{{ $liveMatchup['opponent_name'] }}</a>
              <button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ \Illuminate\Support\Str::slug($liveMatchup['opponent_name']) }}" data-team-name="{{ $liveMatchup['opponent_name'] }}" aria-label="View {{ $liveMatchup['opponent_name'] }} logo"><img class="team-viewer-logo-image" data-full-src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($liveMatchup['opponent_name'])) }}" src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($liveMatchup['opponent_name']),160) }}" width="160" height="160" decoding="async" alt="{{ $liveMatchup['opponent_name'] }} team icon"></button>
            </div>
            <div class="team-live-body">
              <div class="team-live-scores">
                <span class="team-live-score team-live-today"><strong class="{{ $oppDayWinning?'winning':'' }}">{{ number_format($liveMatchup['opponent_today'],0) }}</strong><small>Today</small></span>
                <span class="team-live-score team-live-weekly"><strong class="{{ $oppWeekWinning?'winning':'' }}">{{ number_format($liveMatchup['opponent_week'],0) }}</strong><small>Weekly</small></span>
              </div>
              
            </div>
          </div>
          <span class="team-live-chevron">▾</span>
        </summary>
        @php
          $teamAll=collect($liveMatchup['team_rows'] ?? []);
          $oppAll=collect($liveMatchup['opponent_rows'] ?? []);

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
                (bool)$p->is_bench && !(bool)$p->is_ir
                && strtoupper((string)$p->roster_status)!=='MINORS'
              )->values(),
              'IR'=>$playing->filter(fn($p)=>
                (bool)$p->is_ir && strtoupper((string)$p->roster_status)!=='MINORS'
              )->values(),
              'Minors'=>$playing->filter(fn($p)=>
                strtoupper((string)$p->roster_status)==='MINORS'
              )->values(),
            ];
          };

          $teamSections=$sectionGroups($teamAll);
          $oppSections=$sectionGroups($oppAll);
          $alignedSections=[];
          foreach(['Forwards','Defensemen','Goalies','Bench','IR','Minors'] as $sectionName){
            $left=$teamSections[$sectionName]??collect();
            $right=$oppSections[$sectionName]??collect();
            $max=max($left->count(),$right->count());
            while($left->count()<$max)$left->push(null);
            while($right->count()<$max)$right->push(null);
            $alignedSections[$sectionName]=['team'=>$left,'opp'=>$right];
          }
        @endphp
        <div class="matchup-expanded team-live-expanded">
          @foreach(['Forwards','Defensemen','Goalies','Bench','IR','Minors'] as $sectionName)
            @php
              $teamSectionRows=$alignedSections[$sectionName]['team'];
              $oppSectionRows=$alignedSections[$sectionName]['opp'];
              $teamSectionCount=$teamSectionRows->filter()->count();
              $oppSectionCount=$oppSectionRows->filter()->count();
              $isCollapsible=in_array($sectionName,['Bench','IR','Minors'],true);
            @endphp
            @if($teamSectionRows->count() || $oppSectionRows->count())
              @if($isCollapsible)
                <details class="team-live-subsection">
                  <summary class="team-live-section-title team-live-section-toggle">
                    <span class="team-live-section-side">{{ $sectionName }} ({{ $teamSectionCount }})</span>
                    <span class="team-live-section-chevron">▾</span>
                    <span class="team-live-section-side team-live-section-side-right">{{ $sectionName }} ({{ $oppSectionCount }})</span>
                  </summary>
                  <div class="matchup-roster-grid">
                    <div class="matchup-roster-col">
                      @forelse($teamSectionRows as $player)
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
                      @forelse($oppSectionRows as $player)
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
                <div class="team-live-section-title">
                  <span class="team-live-section-side">{{ $sectionName }} ({{ $teamSectionCount }})</span>
                  <span class="team-live-section-side team-live-section-side-right">{{ $sectionName }} ({{ $oppSectionCount }})</span>
                </div>
                <div class="matchup-roster-grid">
                  <div class="matchup-roster-col">
                    @forelse($teamSectionRows as $player)
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
                    @forelse($oppSectionRows as $player)
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
    </section>
  @endif

  @if(!empty($nextWeekOpponent))
    @php
      $nextWeekDates=null;
      if(!empty($nextWeekOpponent['start_date'])&&!empty($nextWeekOpponent['end_date'])){
        $nextStart=\Carbon\CarbonImmutable::parse($nextWeekOpponent['start_date'],'America/Halifax');
        $nextEnd=\Carbon\CarbonImmutable::parse($nextWeekOpponent['end_date'],'America/Halifax');
        $nextWeekDates=$nextStart->format('M j').' – '.$nextEnd->format('M j, Y');
      }
    @endphp
    <details class="team-next-opponent">
      <summary class="team-next-opponent-summary">
        <div class="team-next-opponent-meta">
          <span class="team-next-opponent-label">Playing Next Week</span>
          <span class="subtle">Scoring Period {{ $nextWeekOpponent['period'] }}</span>
          @if($nextWeekDates)<span class="subtle">{{ $nextWeekDates }}</span>@endif
        </div>
        <div class="team-next-opponent-team">
          <button type="button" class="team-logo-viewer team-next-opponent-logo" data-team-icon-viewer data-team-slug="{{ \Illuminate\Support\Str::slug($nextWeekOpponent['opponent']) }}" data-team-name="{{ $nextWeekOpponent['opponent'] }}" aria-label="View {{ $nextWeekOpponent['opponent'] }} logo"><img class="team-viewer-logo-image" data-full-src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($nextWeekOpponent['opponent'])) }}" src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($nextWeekOpponent['opponent']),160) }}" width="160" height="160" loading="lazy" decoding="async" alt="{{ $nextWeekOpponent['opponent'] }} team icon"></button>
          <span class="team-next-opponent-vs">{{ $nextWeekOpponent['side']==='HOME' ? 'vs' : '@' }}</span>
          <div><strong>{{ $nextWeekOpponent['opponent'] }}</strong>
          @if(!empty($nextWeekOpponent['standings'])) @php $nextRank=$nextWeekOpponent['standings']; @endphp
          <small class="team-opponent-record">#{{ $nextRank['rank'] ?? '—' }} · {{ $nextRank['w'] ?? 0 }}–{{ $nextRank['l'] ?? 0 }}–{{ $nextRank['t'] ?? 0 }} · {{ isset($nextRank['fantasy_points_for']) ? number_format($nextRank['fantasy_points_for'],0) : '—' }} FPts</small>@endif</div>
        </div>
        <span class="team-next-opponent-chevron">›</span>
      </summary>
      <div class="team-next-lineup">
        <div class="team-next-lineup-label">Next week's current lineup</div>
        @foreach(['F'=>'Forwards','D'=>'Defense','G'=>'Goalies','Minors'=>'Minors'] as $nextCode=>$nextLabel)
          @php
            $nextPlayers = collect($nextWeekLineup[$nextCode] ?? []);
            if($nextCode!=='Minors'){
              $nextPlayers = $nextPlayers->sortBy(fn($p) => $p->is_ir ? 1 : 0)->values();
            }
          @endphp
          <div class="team-next-lineup-group">
            <div class="team-next-lineup-heading">{{ $nextLabel }} ({{ $nextPlayers->count() }})</div>
            @forelse($nextPlayers as $nextPlayer)
              <div class="team-next-lineup-player">
                <span>{{ $nextPlayer->player_name }}@if($nextPlayer->nhl_team) ({{ $nextPlayer->nhl_team }})@endif</span>
                <span class="team-next-lineup-status">
                  @if($nextCode!=='Minors' && $nextPlayer->is_ir)<span class="pill team-ir">IR</span>@endif
                  @if($nextCode!=='Minors' && $nextPlayer->is_bench)<span class="pill team-bench">Bench</span>@endif
                 </span>
              </div>
            @empty
              <div class="team-next-lineup-empty">None</div>
            @endforelse
          </div>
        @endforeach
      </div>
    </details>
  @endif

  <section class="team-lineup-advisor" aria-label="Lineup Advisor">
    @php
      $advisorProfilesByKey=$advisorProfiles->keyBy('advisor_key');
      $advisorStoredKey=strtolower((string)($lineupAdvice->advisor_name ?? ''));
      $advisorKey=$advisorProfilesByKey->has($advisorStoredKey)
        ? $advisorStoredKey
        : (string)($advisorProfiles->first()->advisor_key ?? 'mike');
      $advisorProfile=$advisorProfilesByKey[$advisorKey] ?? $advisorProfiles->first();
      $advisorFirstName=(string)($advisorProfile->first_name ?? ucfirst($advisorKey));
      $advisorImageSlug=$advisorKey==='mike'?'lineup-advisor':'lineup-advisor-'.$advisorKey;

      $storedAdvisorAdvice=[];
      if(!empty($lineupAdvice?->advisor_advice_json)){
        $decoded=json_decode((string)$lineupAdvice->advisor_advice_json,true);
        if(is_array($decoded))$storedAdvisorAdvice=$decoded;
      }

      $advisorCycleData=[];
      foreach($advisorProfiles as $profile){
        $key=(string)$profile->advisor_key;
        $name=(string)$profile->first_name;
        $legacyAdvice=match($key){
          'mike'=>$lineupAdvice?->mike_advice_text ?? null,
          'pierre'=>$lineupAdvice?->pierre_advice_text ?? null,
          'john'=>$lineupAdvice?->john_advice_text ?? null,
          default=>null,
        };
        $advice=$storedAdvisorAdvice[$key]['advice']
          ?? $legacyAdvice
          ?? ($key===$advisorKey ? ($lineupAdvice->advice_text ?? null) : null)
          ?? ('Run Update Advisors to generate '.$name.'\'s version.');
        $advisorCycleData[$key]=[
          'name'=>$name,
          'slug'=>$key==='mike'?'lineup-advisor':'lineup-advisor-'.$key,
          'advice'=>$advice,
          'thumbnail'=>\App\Support\TeamImages::url($key==='mike'?'lineup-advisor':'lineup-advisor-'.$key,640),
          'fullImage'=>\App\Support\TeamImages::url($key==='mike'?'lineup-advisor':'lineup-advisor-'.$key),
        ];
      }
    @endphp
    <div class="team-lineup-advisor-title">
      <button type="button" class="team-lineup-advisor-cycle" data-advisor-prev aria-label="Previous advisor">‹</button>
      <div class="team-lineup-advisor-title-text">Lineup Advisor · <span data-advisor-display-name="{{ $advisorKey }}">{{ $advisorFirstName }}</span></div>
      <button type="button" class="team-lineup-advisor-cycle" data-advisor-next aria-label="Next advisor">›</button>
    </div>
    <div class="team-lineup-advisor-body">
      <div class="team-lineup-advisor-photo">
        <button
          type="button"
          class="team-lineup-advisor-photo-button"
          data-team-icon-viewer
          data-team-slug="{{ $advisorImageSlug }}"
          data-advisor-key="{{ $advisorKey }}"
          data-advisor-first-name="{{ $advisorFirstName }}"
          title="View {{ $advisorFirstName }} advisor image"
          aria-label="View {{ $advisorFirstName }} Lineup Advisor image">
          <img src="{{ \App\Support\TeamImages::url($advisorImageSlug,640) }}" data-full-src="{{ \App\Support\TeamImages::url($advisorImageSlug) }}" loading="lazy" decoding="async" alt="{{ $advisorFirstName }}, Lineup Advisor">
        </button>
      </div>
      <div class="team-lineup-content">
      <div class="team-lineup-meta">
        <div class="team-lineup-roster-counts">
          <span class="{{ ($rosterCounts['F'] ?? 0) != 8 ? 'team-lineup-count-alert' : '' }}">Forwards: <strong>{{ $rosterCounts['F'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['D'] ?? 0) != 4 ? 'team-lineup-count-alert' : '' }}">Defense: <strong>{{ $rosterCounts['D'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['G'] ?? 0) != 1 ? 'team-lineup-count-alert' : '' }}">Goalies: <strong>{{ $rosterCounts['G'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['Bench'] ?? 0) != 3 ? 'team-lineup-count-alert' : '' }}">Bench: <strong>{{ $rosterCounts['Bench'] ?? 0 }}</strong></span>
          <span>IR: <strong>{{ $rosterCounts['IR'] ?? 0 }}</strong></span>
          <span>Minors: <strong>{{ $rosterCounts['Minors'] ?? 0 }}</strong></span>
        </div>
        @if($movesLeftToday!==null)
          <div class="team-lineup-moves">Moves: <strong>{{ max(0, 7 - $movesLeftToday) }}/7</strong></div>
        @endif
      </div>
    <div class="team-lineup-advisor-text" data-advisor-text></div>
    <script type="application/json" id="team-lineup-advisor-data">{!! json_encode([
      'current'=>$advisorKey,
      'advisors'=>$advisorCycleData,
    ], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT) !!}</script>
      </div>
    @if(!empty($lineupAdvice?->generated_at))
      <div class="team-lineup-advisor-updated">Updated @include('partials.updated-time',['value'=>$lineupAdvice->generated_at])</div>
    @endif
    </div>
  </section>

    <details class="card team-future-picks"><summary>2027 Draft Picks <span>{{ count($futurePicks['picks']) }} available</span></summary>
    @if($futurePicks['franchise_id'])<div class="team-pick-grid">@forelse($futurePicks['picks'] as $pick)<div class="team-pick"><strong>Round {{ $pick['round'] }}</strong><small>{{ $pick['original_team'] }}</small></div>@empty<p class="muted">No picks remaining.</p>@endforelse</div><p class="muted team-pick-note">Based on recorded completed trades. Picks may be passed or traded once the roster is full; draft order will be set after the season.</p>@else<p class="muted">Draft ownership is unavailable for this team.</p>@endif
  </details>
  @php
    $hasRows=collect($positions)->sum(fn($g)=>$g['rows']->count())>0;
  @endphp
  @if(!$hasRows)
    <div class="card"><h2>No roster data yet</h2><p class="subtle">Run the Fantasy Team Rosters collector from Collector Status to populate this team.</p>@if(auth()->user()?->is_admin)<a class="button primary" href="/job-status">Collector Status</a>@endif</div>
  @else
  <div class="team-roster-filter-bar">
      <label class="team-roster-filter-switch">
        <input type="checkbox" id="team-hide-non-playing">
        <span class="team-roster-filter-track"><span class="team-roster-filter-thumb"></span></span>
        <span class="team-roster-filter-label">Hide non-playing</span>
      </label>
    </div>
    @foreach($positions as $code=>$group)
      @php
        $playingCount=$group['rows']->filter(fn($p)=>(bool)$p->daily_participant)->reject(fn($p)=>(bool)$p->is_ir)->count();
        $totalCount=$group['rows']->reject(fn($p)=>(bool)$p->is_ir)->count();
      @endphp
      <section class="team-position-section" data-roster-position="{{ $code }}">
        <div class="table-card"><div class="table-scroll"><table class="data-table team-roster-table">
          <tbody>
            <tr class="team-roster-group team-playing-group">
              <td colspan="2">
                <div class="team-playing-header">
                  <span>{{ $group['label'] }} (<span data-position-count data-playing-count="{{ $playingCount }}" data-total-count="{{ $totalCount }}">{{ $totalCount }}</span>)</span>
                  <span class="team-score-headings"><span>Proj./G</span><span>Today</span></span>
                </div>
              </td>
            </tr>
            @foreach($group['rows'] as $player)
                @php
                  $isPlaying=(bool)$player->daily_participant;
                @endphp
                <tr class="team-player-data-row {{ !$isPlaying?'team-not-playing':'' }} {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }} {{ strtoupper((string)$player->roster_status)==='MINORS'?'team-minors-row':'' }}" data-playing="{{ $isPlaying?'1':'0' }}">
                  <td data-label="Player">
                    <div class="team-player-name-wrap">
                      <strong>{{ $player->player_name }}@if($player->nhl_team) ({{ $player->nhl_team }})@endif</strong>
                      @if($player->is_ir)
                        <span class="pill team-ir">IR</span>
                      @endif
                      @if(strtoupper((string)$player->position)==='G' && !empty($player->starting_status))
                        <span class="pill goalie-status {{ $player->starting_status_class }}">{{ $player->starting_status }}</span>
                      @endif
                    </div>
                    <div class="team-player-lines">
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
                      @if($player->is_bench)
                        <span class="pill team-bench">Bench</span>
                      @endif
                    </div>
                    <div class="team-player-opponent">
                      @if($player->opponent)
                        <span class="{{ $player->home_away==='AWAY'?'team-away':'team-home' }}">{{ $player->home_away==='AWAY'?'@':'vs' }} {{ $player->opponent }}@if($player->game_time) · {{ $player->game_time }}@endif</span>
                      @elseif($player->daily_participant)
                        <span class="team-playing-text">Playing</span>
                      @endif
                    </div>
                  </td>
                  <td data-label="Proj." class="num">
                    <div class="team-proj-wrap">
                      @if(strtoupper((string)$player->position)==='G' && $player->vegas_odds!==null)
                        <span class="pill goalie-vegas-odds goalie-roster-vegas-odds {{ $player->vegas_odds_class }}">{{ $player->vegas_odds>0?'+':'' }}{{ $player->vegas_odds }}</span>
                      @endif
                      <div class="team-score-columns">
                        <span class="team-projected-fpts">{{ $player->projected_fpts_per_game!==null?number_format($player->projected_fpts_per_game,2):'—' }}</span>
                        <strong class="team-today-fpts">{{ $isPlaying ? number_format($player->today_fpts ?? 0, 0) : '' }}</strong>
                      </div>

                    </div>
                  </td>
                </tr>
            @endforeach
          </tbody>
        </table></div></div>

        @if(in_array($code,['F','D','G'],true) && count($targetGroups[$code] ?? []))
          <details class="team-targets" data-target-position="{{ $code }}">
            <summary>{{ $group['label'] }} Targets <span>{{ count($targetGroups[$code] ?? []) }}</span></summary>
            <div class="team-target-list">
              @foreach(($targetGroups[$code] ?? []) as $target)
                <div class="team-target-row" data-target-row @if($loop->iteration>5) hidden @endif>
                  <div class="team-target-main">
                    <div class="team-target-name">
                      <strong>{{ $target['name'] }} ({{ $target['team'] }})</strong>
                      @if($code==='G')@include('account.goalie-bell',['goalie'=>$target])@endif
                      @if(!empty($target['injury_status']))<span class="pill team-ir">IR</span>@endif
                    </div>
                    <div class="team-target-lines">
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
                    <div class="team-target-opponent">
                      @if(!empty($target['opponent']))<span>{{ $target['opponent'] }}@if(!empty($target['game_time'])) · {{ $target['game_time'] }}@endif</span>@endif
                      @if($code==='G')
                        @if(!empty($target['starting_status']))
                          <span class="pill goalie-status {{ $target['starting_status_class'] ?? 'goalie-status-na' }}">{{ $target['starting_status'] }}</span>
                        @else
                          <span class="pill goalie-status goalie-status-na">NA</span>
                        @endif
                      @endif
                    </div>
                  </div>
                  <div class="team-target-actions">
                    @if(($target['position'] ?? '') === 'G' && array_key_exists('vegas_odds',$target) && $target['vegas_odds']!==null)
                      <span class="pill goalie-vegas-odds {{ $target['vegas_odds_class'] }}">{{ $target['vegas_odds']>0?'+':'' }}{{ (int)$target['vegas_odds'] }}</span>
                    @endif
                    <div class="team-target-stats" title="Season / Last 21 / Last 7 / My Projection">
                      <span><small>SEASON</small>{{ isset($target['season_fpts_per_game'])&&$target['season_fpts_per_game']!==null?number_format($target['season_fpts_per_game'],2):'—' }}</span>
                      <span><small>L21</small>{{ isset($target['fpts_per_game_21d'])&&$target['fpts_per_game_21d']!==null?number_format($target['fpts_per_game_21d'],2):'—' }}</span>
                      <span><small>L7</small>{{ isset($target['fpts_per_game_7d'])&&$target['fpts_per_game_7d']!==null?number_format($target['fpts_per_game_7d'],2):'—' }}</span>
                      <strong class="team-target-proj"><small>MY PROJ</small>{{ $target['projected_points']!==null?number_format($target['projected_points'],2):'—' }}</strong>
                    </div>
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
@media(max-width:700px){
.page-head{padding:18px 0 10px}
.page-head .eyebrow{margin-bottom:4px;color:#b7791f!important;text-shadow:none!important}
.page-head p{margin-top:3px}
.page-head .team-fantrax-row{margin-top:5px}
.page-head .team-updated{margin-top:5px}
.team-page-controls{margin-top:4px!important;margin-bottom:8px!important}
.team-date-buttons{gap:6px!important}
.team-date-button{padding:8px 10px!important;min-height:0!important}
}
.team-next-opponent{margin:8px 0;border:1px solid var(--line);border-radius:10px;background:#fff;overflow:hidden}.team-next-opponent-summary{position:relative;display:grid;grid-template-columns:auto 1fr;align-items:center;gap:2px 8px;padding:9px 34px 9px 10px;cursor:pointer;list-style:none}.team-next-opponent-summary::-webkit-details-marker{display:none}.team-next-opponent-label{font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.04em;color:#64748b}.team-next-opponent-summary>strong{font-size:13px;color:#172033}.team-next-opponent .subtle{grid-column:1/-1;font-size:10px;line-height:1.2;color:#64748b}.team-next-opponent-chevron{position:absolute;right:12px;top:50%;transform:translateY(-50%);color:#2563eb;font-weight:900;transition:.15s}.team-next-opponent[open] .team-next-opponent-chevron{transform:translateY(-50%) rotate(180deg)}.team-next-lineup{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:6px;padding:9px;border-top:1px solid #e5e7eb;background:#fff}.team-next-lineup-label{grid-column:1/-1;font-size:10px;font-weight:800;color:#64748b}.team-next-lineup-group{min-width:0;border:1px solid #dbeafe;border-radius:7px;overflow:hidden}.team-next-lineup-heading{padding:5px 6px;background:#dbeafe;color:#1d4ed8;font-size:10px;font-weight:900}.team-next-lineup-player{display:flex;align-items:center;justify-content:space-between;gap:4px;padding:5px 6px;border-top:1px solid #e5e7eb;color:#172033;font-size:9px;font-weight:700}.team-next-lineup-status{display:flex;gap:2px}.team-next-lineup-player .pill{padding:1px 3px!important;font-size:7px!important}.team-next-lineup-empty{padding:6px;color:#94a3b8;font-size:9px}@media(max-width:700px){.team-next-lineup{grid-template-columns:1fr 1fr}.team-next-lineup-player{font-size:9px}}.team-roster-filter-bar{display:flex;justify-content:flex-end;align-items:center;margin:0 0 10px}.team-roster-filter-switch{display:inline-flex;align-items:center;gap:8px;cursor:pointer;font-size:12px;font-weight:800;color:var(--muted)}.team-roster-filter-switch input{position:absolute;opacity:0;pointer-events:none}.team-roster-filter-track{position:relative;width:38px;height:20px;border-radius:999px;background:#cbd5e1;transition:.18s ease}.team-roster-filter-thumb{position:absolute;top:3px;left:3px;width:14px;height:14px;border-radius:50%;background:#fff;box-shadow:0 1px 3px rgba(0,0,0,.25);transition:.18s ease}.team-roster-filter-switch input:checked+.team-roster-filter-track{background:#2563eb}.team-roster-filter-switch input:checked+.team-roster-filter-track .team-roster-filter-thumb{transform:translateX(18px)}.team-roster-filter-switch input:focus-visible+.team-roster-filter-track{outline:2px solid #60a5fa;outline-offset:2px}.team-hide-non-playing .team-player-data-row[data-playing="0"]{display:none}@media(max-width:700px){.team-hide-non-playing .team-player-data-row[data-playing="0"]{display:none}.team-roster-filter-bar{padding:0 4px}}
.team-lineup-advisor{margin:0 0 16px;border:1px solid var(--line);border-radius:10px;background:#fff;overflow:hidden}.team-lineup-advisor-title{display:grid;grid-template-columns:28px minmax(0,1fr) 28px;align-items:center;gap:6px;padding:6px 8px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.team-lineup-advisor-title-text{text-align:center;min-width:0}.team-lineup-advisor-cycle{display:flex;align-items:center;justify-content:center;width:28px;height:28px;padding:0;border:0;border-radius:7px;background:rgba(29,78,216,.1);color:#1d4ed8;font-size:20px;font-weight:900;line-height:1;cursor:pointer}.team-lineup-advisor-cycle:hover{background:rgba(29,78,216,.18)}.team-lineup-advisor-cycle:focus-visible{outline:2px solid #2563eb;outline-offset:1px}.team-lineup-advisor-body{position:relative;display:grid;grid-template-columns:112px minmax(0,1fr);gap:12px;align-items:stretch;padding:0 12px 24px 0;background:#fff;min-height:118px}.team-lineup-content{min-width:0}.team-lineup-meta{float:right;max-width:62%;margin:0 0 8px 18px;text-align:right}.team-lineup-roster-counts{display:grid;grid-template-columns:repeat(3,max-content);justify-content:end;gap:3px 14px;font-size:11px;margin-bottom:4px;color:#111827}.team-lineup-roster-counts span{text-align:right}.team-lineup-roster-counts strong{display:inline-block;min-width:1.2em;text-align:right;color:inherit}.team-lineup-roster-counts .team-lineup-count-alert,.team-lineup-roster-counts .team-lineup-count-alert strong{color:#dc2626}.team-lineup-moves{font-size:11px;color:#dc2626;font-weight:800}.team-lineup-moves strong{color:#dc2626}.team-lineup-advisor-text{font-size:13px;font-weight:700;min-width:0;overflow-wrap:anywhere;word-break:normal}.team-lineup-player-link{color:#2563eb;text-decoration:underline;text-underline-offset:2px;font-weight:900}.team-lineup-player-link:hover{color:#1d4ed8}html[data-theme="dark"] .team-lineup-player-link{color:#93c5fd}html[data-theme="dark"] .team-lineup-player-link:hover{color:#bfdbfe}.team-lineup-advisor-text .goalie-status{margin:0 2px;vertical-align:1px}.team-lineup-advisor-updated{position:absolute;left:12px;bottom:5px;font-size:11px;color:var(--muted);text-align:left}.team-lineup-advisor-photo{display:flex;align-self:start;overflow:hidden}.team-lineup-advisor-photo-button{display:block;width:100%;height:100%;padding:0;border:0;background:transparent;cursor:zoom-in}.team-lineup-advisor-photo-button:focus-visible{outline:2px solid #60a5fa;outline-offset:-2px}.team-lineup-advisor-photo img{display:block;width:100%;height:100%;min-height:118px;object-fit:cover;object-position:center top;border-radius:0}html[data-theme="dark"] .team-next-opponent,html[data-theme="dark"] .team-next-lineup{background:#fff}html[data-theme="dark"] .team-next-opponent-summary>strong,html[data-theme="dark"] .team-next-lineup-player{color:#172033}html[data-theme="dark"] .team-lineup-advisor{background:#111827}html[data-theme="dark"] .team-lineup-advisor-title{background:#15365f;color:#dbeafe}html[data-theme="dark"] .team-lineup-advisor-cycle{background:rgba(219,234,254,.12);color:#dbeafe}html[data-theme="dark"] .team-lineup-advisor-body{background:#111827}@media(max-width:700px){.team-lineup-advisor-body{grid-template-columns:86px minmax(0,1fr);gap:9px;padding:0 10px 22px 0;min-height:104px}.team-lineup-advisor-photo{display:flex!important;visibility:visible!important;align-self:stretch!important;min-width:86px!important;overflow:hidden!important}.team-lineup-advisor-photo img{display:block!important;visibility:visible!important;width:86px!important;height:104px!important;min-height:104px!important;max-height:none!important;object-fit:cover!important;object-position:center top!important}.team-lineup-advisor-updated{left:10px;right:auto;bottom:4px;text-align:left}.team-lineup-meta{float:none;max-width:none;margin:0 0 8px;text-align:right}.team-lineup-roster-counts{grid-template-columns:repeat(3,max-content);justify-content:end}.team-lineup-moves{position:absolute;right:10px;bottom:0;width:auto;text-align:right;white-space:nowrap;color:#dc2626;font-weight:800}.team-lineup-moves strong{color:#dc2626}.team-lineup-content{align-self:start!important;padding-top:0!important}.team-lineup-meta{margin-top:0!important}.team-lineup-advisor-text{clear:both;margin-top:0!important;margin-left:0;padding-top:0!important;padding-bottom:12px;min-width:0;overflow-wrap:anywhere}}
.team-updated{font-size:12px;opacity:.8;margin-top:3px}.team-live-matchup{margin:0 0 14px}.team-live-matchup-label{font-size:10px;font-weight:900;letter-spacing:.06em;text-transform:uppercase;color:var(--muted);margin:0 0 5px}.team-live-matchup-card{border:1px solid var(--line);border-radius:18px;background:#fff;overflow:hidden;box-shadow:none}.team-live-matchup-card[open]{border:3px solid #2563eb}.team-live-matchup-summary{position:relative;display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:10px;padding:11px 32px 11px 11px;cursor:pointer;list-style:none}.team-live-matchup-summary::-webkit-details-marker{display:none}.team-live-side{min-width:0;display:flex;align-items:stretch;justify-content:flex-start;gap:8px}.team-live-side-content{justify-content:space-between}.team-live-side-right{flex-direction:row-reverse;text-align:right}.team-live-logo{width:38px;height:38px;flex:0 0 38px;object-fit:cover;border-radius:8px}.team-live-side-content{min-width:0;flex:1;display:flex;flex-direction:column;gap:5px}.team-live-side-right .team-live-side-content{align-items:flex-end}.team-live-score-left .team-live-scores{align-self:flex-end}.team-live-score-right .team-live-scores{align-self:flex-start}.team-live-name-row{min-width:0;display:flex;align-items:center;gap:6px}.team-live-side-right .team-live-name-row{flex-direction:row-reverse}.team-live-name-row a{font-weight:800;color:#172033;text-decoration:none;white-space:normal;overflow:visible;text-overflow:clip;line-height:1.05}.team-live-side-pill{font-size:8px;font-weight:900;line-height:1;padding:4px 6px;border:1px solid;border-radius:999px}.team-live-side-pill.home{background:#dcfce7;color:#166534;border-color:#86efac}.team-live-side-pill.away{background:#fef3c7;color:#92400e;border-color:#fcd34d}.team-live-scores{display:flex;align-items:flex-start;gap:4px;white-space:nowrap}.team-live-score{display:flex;align-items:baseline;gap:2px;color:#172033}.team-live-score small{font-size:8px;font-weight:800;text-transform:uppercase;color:#64748b}.team-live-score strong{line-height:1;font-weight:500;color:#172033}.team-live-score+.team-live-score{transform:translateY(-3px)}.team-live-score+.team-live-score small{font-size:7px}.team-live-score+.team-live-score strong{}.team-live-score strong.winning{font-weight:900}.team-live-vs{font-size:9px;font-weight:900;color:#64748b}.team-live-chevron{position:absolute;right:11px;top:50%;transform:translateY(-50%);font-size:12px;color:#64748b;transition:transform .15s ease}.team-live-matchup-card[open] .team-live-chevron{transform:translateY(-50%) rotate(180deg)}.team-live-expanded{border-top:1px solid #e5e7eb;background:#fff}.team-live-expanded .matchup-section-title{grid-column:1/-1;text-align:center;background:#e5e7eb;color:#374151;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;padding:5px 8px;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb}.team-live-expanded .matchup-roster-grid{display:grid;grid-template-columns:1fr 1fr}.team-live-expanded .matchup-roster-col{min-width:0;border-right:1px solid #e5e7eb}.team-live-expanded .matchup-roster-col:last-child{border-right:0}.team-live-expanded .matchup-player-row{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 10px;border-bottom:1px solid #e5e7eb;min-height:58px}.team-live-expanded .matchup-player-row:last-child{border-bottom:0}.team-live-expanded .matchup-player-main{min-width:0}.team-live-expanded .matchup-player-name{display:flex;align-items:center;gap:4px;flex-wrap:wrap;font-size:13px;line-height:1.15}.team-live-expanded .matchup-player-name .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}.team-live-expanded .matchup-player-opponent{margin-top:4px;font-size:10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}.team-live-expanded .matchup-player-cats{font-size:8px;color:var(--muted);font-weight:800;white-space:nowrap}.team-live-expanded .matchup-player-metrics{display:flex;align-items:flex-start;align-self:flex-start;gap:10px;flex:0 0 auto;text-align:right}.team-live-expanded .matchup-player-metrics span{display:block;font-size:8px;color:var(--muted);font-weight:700}.team-live-expanded .matchup-player-metrics strong{display:block;font-size:14px}.team-live-expanded .matchup-player-today strong{font-size:17px}.team-live-expanded .matchup-empty{padding:18px 12px;color:var(--muted);font-size:12px}.team-live-expanded .matchup-player-blank{min-height:58px;background:transparent!important}.team-live-section-title{display:grid;grid-template-columns:1fr 1fr;align-items:center;background:#e5e7eb;color:#374151;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;padding:7px 12px;border-top:1px solid #e5e7eb;border-bottom:1px solid #e5e7eb}.team-live-section-side{text-align:left}.team-live-section-side-right{text-align:right}.team-live-subsection{margin:0}.team-live-section-toggle{grid-template-columns:1fr auto 1fr;cursor:pointer;list-style:none}.team-live-section-toggle::-webkit-details-marker{display:none}.team-live-section-chevron{font-size:11px;transition:transform .15s ease}.team-live-subsection[open] .team-live-section-chevron{transform:rotate(180deg)}.team-live-expanded .matchup-bench{background:#f8fafc;border-top:12px solid #eef2f7}.team-live-expanded .matchup-bench-title{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;font-size:13px;font-weight:900;text-transform:uppercase;color:#4b5563}.team-page-head-shell{display:flex;align-items:center;gap:16px}.team-page-head-logo{display:flex;align-items:center;justify-content:center;flex:0 0 auto}.team-page-head-logo .team-icon-uploader img{width:104px;height:104px;border-radius:18px}.team-page-head-copy{display:flex;flex-direction:column;align-items:flex-start;justify-content:center;min-width:0}.team-page-head-copy .eyebrow{margin:0 0 4px}.team-page-head-copy p{margin-left:0}.team-page-head-copy .team-updated{margin-top:5px}
.team-title-switcher{display:flex;align-items:center;gap:10px;width:min(620px,100%)}.team-title-switcher select{appearance:auto;width:auto;max-width:100%;border:0;background:transparent;color:var(--text);font:inherit;font-size:34px;font-weight:800;line-height:1.05;letter-spacing:-1px;padding:0 30px 0 0;cursor:pointer}.team-title-switcher select:focus{outline:none}.team-left-controls{display:flex;flex-direction:column;gap:8px}.team-page-controls{display:flex;align-items:end;justify-content:flex-start;gap:12px;margin:2px 0 12px}.team-fantrax-row{margin:3px 0 0}.team-fantrax-link{display:inline-flex;align-items:center;gap:4px;margin-left:0;font-weight:800;text-decoration:none}.team-fantrax-link img{width:15px;height:15px;object-fit:contain}.team-date-buttons{display:flex;gap:5px;margin:0}.team-date-buttons .button{padding:4px 8px;font-size:11px;min-height:30px}.team-date-inactive{background:#e5e7eb!important;border-color:#d1d5db!important;color:#374151!important}.team-date-inactive:hover{background:#d1d5db!important;color:#111827!important}.team-not-playing-toggle{cursor:pointer}.team-not-playing-toggle td{cursor:pointer}.team-not-playing-label{font:inherit;font-weight:900;text-transform:uppercase;letter-spacing:.06em;display:inline-flex;align-items:center;gap:6px}.team-not-playing-chevron{font-size:11px}.team-position-section{margin:22px 0}.team-position-section .table-card{border-radius:10px 10px 0 0}/* Improve target-section readability */
html[data-theme="dark"] .team-targets{background:#0b1728!important;border-color:#31547b!important}
html[data-theme="dark"] .team-targets summary{background:#183b65!important;color:#e8f2ff!important;border-bottom:1px solid #31547b!important}
html[data-theme="dark"] .team-target-row{background:#0d1b2d!important;border-top-color:#263e5a!important;padding:10px 10px!important}
html[data-theme="dark"] .team-target-row:nth-child(even){background:#101f33!important}
html[data-theme="dark"] .team-target-name strong{color:#f8fafc!important}
html[data-theme="dark"] .team-target-opponent{color:#aebdd0!important}
html[data-theme="dark"] .team-target-proj{color:#f8fafc!important}
html[data-theme="dark"] .team-target-add{background:#1268bd!important;border-color:#2580d4!important;box-shadow:0 2px 6px rgba(0,0,0,.22)}
html[data-theme="dark"] .team-target-more{background:#183b65!important;color:#e8f2ff!important;border-color:#31547b!important}
@media(max-width:700px){
 html[data-theme="dark"] .team-targets summary{padding:8px 10px!important;font-size:10px!important}
 html[data-theme="dark"] .team-target-row{padding:9px 10px!important;gap:7px!important}
 html[data-theme="dark"] .team-target-name{font-size:13px!important;line-height:1.15!important}
 html[data-theme="dark"] .team-target-opponent{font-size:10px!important;margin-top:5px!important}
 html[data-theme="dark"] .team-target-proj{font-size:18px!important;font-weight:900!important}
 html[data-theme="dark"] .team-target-add{font-size:9px!important;padding:5px 7px!important}
}
.team-targets{margin-top:0;border:1px solid #f5dea1;border-top:0;border-radius:0 0 10px 10px;background:#fffdf2;overflow:hidden}.team-targets summary{cursor:pointer;list-style:none;padding:7px 9px;background:#fff3bf;color:#7c5a00;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;display:flex;align-items:center;justify-content:space-between}.team-targets summary::-webkit-details-marker{display:none}.team-targets summary:after{content:"▾";font-size:10px}.team-targets[open] summary:after{content:"▴"}.team-targets summary span{margin-left:auto;margin-right:8px;font-size:11px;font-weight:900}.team-target-list{display:block}.team-target-row{display:flex;align-items:center;justify-content:space-between;gap:8px;padding:7px 9px;border-top:1px solid var(--line)}.team-target-main{min-width:0}.team-target-name{display:flex;align-items:center;gap:3px;flex-wrap:wrap;font-size:12px}.team-target-name .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}.team-target-opponent{margin-top:3px;font-size:10px;color:var(--muted);display:flex;align-items:center;gap:5px;flex-wrap:wrap}.goalie-status{padding:1px 5px!important;font-size:9px!important;font-weight:900;line-height:1.1}.goalie-status-confirmed{background:#dcfce7;color:#166534;border-color:#86efac}.goalie-status-likely{background:#ffedd5;color:#9a3412;border-color:#fdba74}.goalie-status-unconfirmed{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.goalie-status-na{background:#e5e7eb;color:#4b5563;border-color:#cbd5e1}.goalie-status-not-starting{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.goalie-vegas-odds{padding:1px 5px!important;font-size:9px!important;font-weight:900;line-height:1.1}.goalie-roster-vegas-odds{margin-right:6px;vertical-align:middle}.vegas-odds-good{background:#dcfce7;color:#166534;border-color:#86efac}.vegas-odds-even{background:#fef3c7;color:#92400e;border-color:#fcd34d}.vegas-odds-bad{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-target-actions{display:flex;align-items:center;gap:7px;flex:0 0 auto}.team-target-proj{flex:0 0 auto;font-size:18px!important;font-weight:900!important}.team-target-stats{display:flex;align-items:flex-end;gap:7px;white-space:nowrap}.team-target-stats span,.team-target-stats strong{display:flex;flex-direction:column;align-items:center;font-size:12px;line-height:1.05}.team-target-stats small{font-size:7px;line-height:1;margin-bottom:3px;color:var(--muted);font-weight:900;letter-spacing:.03em}.team-target-add{display:inline-flex;align-items:center;justify-content:center;background:#0055A7;color:#fff!important;border:1px solid #004786;border-radius:7px;padding:3px 6px;font-size:9px;font-weight:900;text-decoration:none;white-space:nowrap}.team-target-add:hover{background:#004786;text-decoration:none}.team-target-more{display:block;margin:8px auto 2px;border:1px solid #efd77a;background:#fff8d9;color:#6b5200;border-radius:7px;padding:5px 10px;font-size:10px;font-weight:900;cursor:pointer}.team-target-more:hover{background:#fff1b8}.team-target-status{font-weight:800}.target-fa{background:#dcfce7;color:#166534;border-color:#86efac}.target-waiver{background:#fef3c7;color:#92400e;border-color:#fcd34d}.team-roster-table td,.team-roster-table th{padding:6px 8px}.team-roster-table .team-proj-head{text-align:right!important;width:122px}.team-roster-table td[data-label="Proj."]{width:122px;text-align:right!important}.team-player-opponent{margin-top:4px;font-size:11px;line-height:1.2}.team-proj-wrap{width:100%;display:flex;flex-direction:column;align-items:flex-end;justify-content:center;gap:3px;text-align:right}.team-score-headings{display:grid;grid-template-columns:48px 56px;gap:8px;text-align:right}.team-score-columns{display:grid;grid-template-columns:48px 56px;gap:8px;align-items:baseline;text-align:right}.team-today-fpts{font-size:16px}.team-projected-fpts{font-size:13px;color:var(--muted);font-weight:800}.team-today-total{margin-left:5px;white-space:nowrap}.team-proj-badges{width:100%;display:flex;justify-content:flex-end;align-items:center;gap:2px;flex-wrap:nowrap}.team-proj-badges .pill{padding:1px 3px!important;font-size:8px!important;line-height:1!important;min-height:0!important;border-radius:999px}.team-roster-table td:first-child{white-space:normal}.team-roster-group td{background:var(--surface-2,#f1f5f9);font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.05em;color:var(--muted);padding:5px 8px!important}.team-playing-group td{background:#dbeafe;color:#1d4ed8}.team-playing-header{display:flex;align-items:center;justify-content:space-between;width:100%;gap:12px}.team-not-playing{opacity:.62}.team-bench-row{background:#f3f4f6}.team-bench-row td{background:#f3f4f6!important}.team-minors-row{background:#eff6ff}.team-minors-row td{background:#eff6ff!important}.team-ir-row{background:#fff1f2}.team-ir-row td{background:#fff1f2!important}.team-player-name-wrap{display:flex;align-items:center;gap:3px;flex-wrap:wrap;font-size:12px;line-height:1.2}.team-player-name-wrap .pill{padding:1px 4px!important;font-size:9px!important;line-height:1.05}.team-ir{background:#dc2626;color:#fff;border-color:#dc2626;margin:0;padding:2px 5px!important;font-size:9px!important;line-height:1}.team-bench{background:#e5e7eb;color:#374151;border-color:#d1d5db}.team-minors{background:#dbeafe;color:#1d4ed8;border-color:#93c5fd}.team-active{background:#dcfce7;color:#166534;border-color:#86efac}.line-1,.pp1,.goalie-1{background:#dcfce7;color:#166534;border-color:#86efac}.line-2,.pp2,.goalie-2{background:#fef3c7;color:#92400e;border-color:#fcd34d}.line-3{background:#ffedd5;color:#9a3412;border-color:#fdba74}.line-4{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-contract-sticker{font-weight:800;padding:3px 7px}.contract-green{background:#dcfce7;color:#166534;border-color:#86efac}.contract-yellow{background:#fef3c7;color:#92400e;border-color:#fcd34d}.contract-red{background:#fee2e2;color:#b91c1c;border-color:#fca5a5}.team-away{color:#a16207;font-weight:800}.team-home{color:#15803d;font-weight:800}.team-playing-text{color:#15803d;font-weight:800}
html[data-theme="dark"] .team-playing-group td{background:#15365f;color:#dbeafe}
html[data-theme="dark"] .team-roster-group:not(.team-playing-group) td{background:#182333;color:#b9c4d2}
html[data-theme="dark"] .team-ir-row,html[data-theme="dark"] .team-ir-row td{background:#3a1f26!important;color:#f8e7eb}
html[data-theme="dark"] .team-bench-row,html[data-theme="dark"] .team-bench-row td{background:#1d2735!important}html[data-theme="dark"] .team-minors-row,html[data-theme="dark"] .team-minors-row td{background:#17263a!important}
html[data-theme="dark"] .team-not-playing{opacity:.78}
html[data-theme="dark"] .team-targets{background:#2a2516;border-color:#665622}
html[data-theme="dark"] .team-targets summary{background:#4a3d12;color:#ffe89a}
html[data-theme="dark"] .team-target-row{border-top-color:#54491f}
html[data-theme="dark"] .team-target-more{background:#3b3215;border-color:#7d6924;color:#ffe89a}
html[data-theme="dark"] .team-target-more:hover{background:#4a3d12}
html[data-theme="dark"] .team-home{color:#4ade80}
html[data-theme="dark"] .team-away{color:#fbbf24}
@media(max-width:700px){
.current-team-page{width:100%;min-width:0;max-width:100%;overflow-x:hidden;contain:inline-size}.team-live-matchup-summary{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:5px;padding:9px 25px 9px 7px}.team-live-side{gap:5px;align-items:flex-start}.team-live-logo{width:30px;height:30px;flex-basis:30px;border-radius:6px}.team-live-side-content{gap:4px}.team-live-name-row{align-items:flex-start;gap:3px}.team-live-name-row a{font-size:11px;line-height:1.05}.team-live-side-pill{font-size:6px;padding:3px 4px}.team-live-scores{gap:3px}.team-live-score{display:block;line-height:1}.team-live-score small{display:block;font-size:6px;margin-bottom:2px}.team-live-score strong{}.team-live-score+.team-live-score{transform:translateY(-3px)}.team-live-score+.team-live-score small{font-size:5px}.team-live-score+.team-live-score strong{}.team-live-vs{font-size:7px;margin-top:10px}
.team-position-section,.table-card,.table-scroll{width:100%;min-width:0;max-width:100%;overflow-x:hidden;contain:inline-size}
.team-page-controls{align-items:stretch;flex-direction:column;padding:0 14px}.team-page-head-shell{align-items:flex-start;gap:12px}.team-page-head-logo .team-icon-uploader img{width:84px;height:84px;border-radius:14px}.team-page-head-copy{padding-top:1px}.team-page-head-copy .eyebrow{margin-bottom:2px}.team-page-head-copy .team-fantrax-row,.team-page-head-copy .team-updated{margin-top:3px}.team-title-switcher select{font-size:30px;max-width:100%}
.team-roster-table,.team-roster-table tbody{display:block!important;width:100%!important;min-width:0!important;max-width:100%!important}
.team-roster-table thead{display:none}
.team-roster-table tr{display:grid;width:100%;min-width:0;max-width:100%;grid-template-columns:minmax(0,1fr) 122px;gap:4px 8px;padding:8px;border-bottom:1px solid var(--line);overflow:hidden}
.team-roster-table td{border:0!important;padding:0!important;min-width:0;max-width:100%;overflow-wrap:anywhere}
.team-roster-table td::before{display:none}
.team-roster-table td[data-label="Player"]{grid-column:1;grid-row:1 / span 2;min-width:0}
.team-player-name-wrap{min-width:0;max-width:100%}
.team-player-name-wrap strong{min-width:0;overflow-wrap:anywhere}
.team-roster-table td[data-label="Proj."]{grid-column:2;grid-row:1 / span 2;text-align:right;white-space:nowrap;align-self:start}
.team-roster-table .num{text-align:right!important}
.team-roster-table tr.team-roster-group{display:block;width:100%;padding:0;overflow:hidden}
.team-roster-table tr.team-roster-group td{display:block!important;width:100%;max-width:100%;padding:5px 8px!important}
}
.matchup-player-row.team-game-finished-row{background:#fffbea!important}
html[data-theme="dark"] .matchup-player-row.team-game-finished-row{background:#3a3217!important}

/* Dark-mode bridge between the page header and white matchup card. */
html[data-theme="dark"] .team-live-matchup-wrap,
html[data-theme="dark"] .team-matchup-section,
html[data-theme="dark"] .team-current-matchup-section{background:transparent!important}
html[data-theme="dark"] .team-live-matchup-label,
html[data-theme="dark"] .team-matchup-period-label{color:#94a3b8!important}

html[data-theme="dark"] .team-live-matchup{background:transparent!important}
html[data-theme="dark"] .team-live-matchup-label{background:transparent!important;color:#94a3b8!important}
.team-player-lines{display:flex;align-items:center;gap:4px;margin-top:4px;min-height:18px}.team-player-lines:empty{display:none}.team-player-lines .pill{font-size:9px;padding:1px 5px}
.team-roster-table .team-today-fpts{font-size:24px!important;font-weight:900!important;line-height:1!important}
.team-roster-table .team-player-name-wrap>strong{font-size:18px!important;line-height:1.15!important}

/* Non-playing roster rows use the same background treatment as bench rows. */
.team-not-playing,.team-not-playing td{background:#f3f4f6!important}
html[data-theme="dark"] .team-not-playing,html[data-theme="dark"] .team-not-playing td{background:#1d2735!important}

/* Keep player name/team and IR badge together on the first line on mobile. */
.team-player-name-wrap>strong{display:inline!important}
.team-player-name-wrap>.team-ir{display:inline-flex!important;flex:0 0 auto}

/* Roster name/IR line and target player typography. */
.team-player-name-wrap{display:block!important}
.team-player-name-wrap>strong,.team-player-name-wrap>.team-ir{vertical-align:middle}
.team-player-name-wrap>.team-ir{display:inline-flex!important;margin-left:3px!important}
.team-target-name{display:block!important;font-size:18px!important;line-height:1.15!important}
.team-target-name>strong,.team-target-name>.team-ir{vertical-align:middle}
.team-target-name>.team-ir{display:inline-flex!important;margin-left:3px!important}
.team-target-lines{display:flex;align-items:center;gap:4px;margin-top:4px;min-height:18px}
.team-target-lines .pill{padding:1px 5px!important;font-size:9px!important;line-height:1.05}

/* Soften the advisor portrait inside the card. */
.team-lineup-advisor-photo{padding:0 0 0 4px;box-sizing:border-box;overflow:visible;align-self:start;justify-self:start}
.team-lineup-advisor-photo-button{overflow:hidden;border-radius:6%;box-shadow:0 3px 10px rgba(15,23,42,.14)}
.team-lineup-advisor-photo img{border-radius:6%!important}
html[data-theme="dark"] .team-lineup-advisor-photo-button{box-shadow:0 4px 14px rgba(0,0,0,.35)}

/* Next-week lineup: forwards left; defense, goalies and minors stacked right */
.team-next-lineup{grid-template-columns:minmax(0,1fr) minmax(0,1fr)!important;align-items:start!important}
.team-next-lineup-label{grid-column:1/-1!important}
.team-next-lineup-group:nth-of-type(2){grid-column:1!important;grid-row:2 / span 3!important}
.team-next-lineup-group:nth-of-type(3){grid-column:2!important;grid-row:2!important}
.team-next-lineup-group:nth-of-type(4){grid-column:2!important;grid-row:3!important}
.team-next-lineup-group:nth-of-type(5){grid-column:2!important;grid-row:4!important}

/* Redesigned next-week opponent header */
.team-next-opponent-summary{display:grid!important;grid-template-columns:minmax(120px,.8fr) minmax(0,1.6fr)!important;align-items:center!important;gap:12px!important;padding:12px 42px 12px 14px!important}
.team-next-opponent-meta{display:flex;flex-direction:column;gap:2px;min-width:0}
.team-next-opponent-meta .subtle{grid-column:auto!important}
.team-next-opponent-team{display:flex;align-items:center;gap:10px;min-width:0}
.team-next-opponent-logo{width:54px;height:54px;flex:0 0 54px;object-fit:cover;border-radius:12px}
.team-next-opponent-vs{font-size:11px;font-weight:900;text-transform:uppercase;color:#64748b}
.team-next-opponent-team strong{font-size:18px;line-height:1.05;color:#172033}
.team-next-opponent-chevron{font-size:24px!important}
html[data-theme="dark"] .team-next-opponent-logo{box-shadow:0 3px 12px rgba(0,0,0,.4)}
html[data-theme="dark"] .team-next-opponent-vs{color:#93c5fd!important}
html[data-theme="dark"] .team-next-opponent-team strong{color:#f8fafc!important}
@media(max-width:700px){
 .team-next-opponent-summary{grid-template-columns:minmax(100px,.85fr) minmax(0,1.5fr)!important;gap:8px!important;padding:10px 32px 10px 10px!important}
 .team-next-opponent-logo{width:46px;height:46px;flex-basis:46px;border-radius:10px}
 .team-next-opponent-team{gap:6px}
 .team-next-opponent-vs{font-size:8px}
 .team-next-opponent-team strong{font-size:13px!important}
 .team-next-opponent-meta .subtle{font-size:9px!important}
 .team-next-opponent-chevron{right:9px!important;font-size:20px!important}
}

/* Dark-friendly next-week lineup */
html[data-theme="dark"] .team-next-opponent{background:#0b1728!important;border:1px solid #1d4f91!important;border-radius:16px!important;box-shadow:0 8px 24px rgba(0,0,0,.22)}
html[data-theme="dark"] .team-next-opponent-summary{background:linear-gradient(135deg,#0d1d32,#081524)!important;border-bottom-color:#1e3a5f!important}
html[data-theme="dark"] .team-next-opponent-label{color:#93c5fd!important}
html[data-theme="dark"] .team-next-opponent-summary>strong{color:#f8fafc!important}
html[data-theme="dark"] .team-next-opponent .subtle{color:#94a3b8!important}
html[data-theme="dark"] .team-next-opponent-chevron{color:#60a5fa!important}
html[data-theme="dark"] .team-next-lineup{background:#081321!important;border-top:1px solid #1e3a5f!important;gap:8px!important}
html[data-theme="dark"] .team-next-lineup-label{color:#cbd5e1!important;font-size:11px!important}
html[data-theme="dark"] .team-next-lineup-group{background:#0c1929!important;border:1px solid #203d5d!important;border-radius:10px!important}
html[data-theme="dark"] .team-next-lineup-heading{background:#15365f!important;color:#bfdbfe!important;padding:7px 8px!important}
html[data-theme="dark"] .team-next-lineup-player{background:#0c1929!important;color:#f1f5f9!important;border-top:1px solid #20354d!important;padding:7px 8px!important}
html[data-theme="dark"] .team-next-lineup-player:nth-child(odd){background:#0e1d30!important}
html[data-theme="dark"] .team-next-lineup-empty{color:#64748b!important}
html[data-theme="dark"] .team-next-lineup-player .team-minors{background:#15365f!important;color:#bfdbfe!important;border-color:#3b82f6!important}
@media(max-width:700px){
 html[data-theme="dark"] .team-next-opponent{border-radius:14px!important}
 html[data-theme="dark"] .team-next-opponent-summary{padding:12px 38px 12px 12px!important}
 html[data-theme="dark"] .team-next-opponent-label{font-size:9px!important}
 html[data-theme="dark"] .team-next-opponent-summary>strong{font-size:14px!important}
 html[data-theme="dark"] .team-next-opponent .subtle{font-size:10px!important;margin-top:2px}
 html[data-theme="dark"] .team-next-lineup{padding:10px!important;gap:9px!important}
 html[data-theme="dark"] .team-next-lineup-group{border-radius:10px!important}
 html[data-theme="dark"] .team-next-lineup-heading{font-size:10px!important}
 html[data-theme="dark"] .team-next-lineup-player{font-size:10px!important;line-height:1.2!important}
}

/* Mobile player metric layout */
@media(max-width:700px){
 .team-live-expanded .matchup-player-row{position:relative!important;display:block!important;padding:7px 9px 5px!important;min-height:72px!important}
 .team-live-expanded .matchup-player-main{display:block!important;width:100%!important;min-width:0!important}
 .team-live-expanded .matchup-player-name{display:flex!important;width:100%!important;padding-right:0!important;font-size:15px!important;line-height:1.1!important}
 .team-live-expanded .matchup-player-name strong{flex-basis:100%!important;width:100%!important;order:-10}
 .team-live-expanded .matchup-player-opponent{padding-right:34px!important;margin-top:3px!important}
 .team-live-expanded .matchup-player-metrics{position:static!important;display:block!important}
 .team-live-expanded .matchup-player-today{position:absolute!important;right:9px!important;bottom:5px!important;text-align:right!important}
 .team-live-expanded .matchup-player-today span{display:inline!important;font-size:8px!important;margin-right:3px}
 .team-live-expanded .matchup-player-today strong{font-size:28px!important;line-height:.9!important}
}

/* Keep matchup logos aligned when team names wrap */
.team-live-name-row{min-height:31px!important;display:flex!important;align-items:flex-start!important}
.team-live-name-row a{width:100%}
@media(max-width:700px){
 .team-live-name-row{min-height:24px!important}
 .team-live-body{align-items:center!important}
}

/* Increase matchup score numbers by 4px */
.team-live-score strong{}
.team-live-score.team-live-weekly strong{}
@media(max-width:700px){
 .team-live-score strong,.team-live-score+.team-live-score strong{}
 .team-live-score.team-live-weekly strong{}
}

/* Larger matchup scores, with weekly score emphasized */
.team-live-score strong{line-height:.95!important}
.team-live-score.team-live-weekly strong{font-weight:900!important}
@media(max-width:700px){
 .team-live-score strong,.team-live-score+.team-live-score strong{line-height:.9!important}
 .team-live-score.team-live-weekly strong{line-height:.9!important}
}

/* Compact mobile matchup scoreboard */
@media(max-width:700px){
 .team-live-matchup-summary{min-height:0!important;padding:8px 24px 8px 8px!important;gap:4px!important}
 .team-live-side{gap:3px!important}
 .team-live-name-row a{font-size:10px!important;line-height:1.05!important}
 .team-live-body{min-height:52px!important;align-items:center!important;gap:4px!important}
 .team-live-logo{width:38px!important;height:38px!important;flex-basis:38px!important;border-radius:7px!important}
 .team-live-scores{gap:1px!important}
 .team-live-score strong,.team-live-score+.team-live-score strong{line-height:.9!important}
 .team-live-score small,.team-live-score+.team-live-score small{font-size:6px!important;margin-top:1px!important}
 .team-live-vs{font-size:7px!important;padding:5px 4px!important;margin-top:7px!important}
 .team-live-chevron{right:7px!important;font-size:9px!important}
}

/* Dark scoreboard treatment */
html[data-theme="dark"] .team-live-matchup-card{background:linear-gradient(135deg,#0b1728 0%,#07111f 100%)!important;border:1px solid #1d4f91!important;box-shadow:0 10px 28px rgba(0,0,0,.28)}
html[data-theme="dark"] .team-live-matchup-card[open]{border:2px solid #2563eb!important;box-shadow:0 0 0 1px rgba(37,99,235,.22),0 12px 32px rgba(0,0,0,.34)}
html[data-theme="dark"] .team-live-matchup-summary{background:linear-gradient(135deg,#0d1d32 0%,#07111f 58%,#0b192b 100%)!important}
html[data-theme="dark"] .team-live-name-row a{color:#f8fafc!important}
html[data-theme="dark"] .team-live-score strong,
html[data-theme="dark"] .team-live-score+.team-live-score strong{color:#f8fafc!important;text-shadow:0 1px 8px rgba(255,255,255,.06)}
html[data-theme="dark"] .team-live-score small{color:#94a3b8!important}
html[data-theme="dark"] .team-live-vs{color:#bfdbfe!important;background:#0b1f3a;border:1px solid #2563eb;border-radius:999px;padding:7px 6px;box-shadow:0 0 12px rgba(37,99,235,.25)}
html[data-theme="dark"] .team-live-chevron{color:#94a3b8!important}
html[data-theme="dark"] .team-live-logo{box-shadow:0 3px 12px rgba(0,0,0,.4)}
html[data-theme="dark"] .team-live-expanded{background:#081321!important;border-top:1px solid #1e3a5f!important}
html[data-theme="dark"] .team-live-section-title,
html[data-theme="dark"] .team-live-expanded .matchup-section-title{background:#142943!important;color:#dbeafe!important;border-color:#24415f!important}
html[data-theme="dark"] .team-live-expanded .matchup-roster-col{border-color:#20354d!important}
html[data-theme="dark"] .team-live-expanded .matchup-player-row{background:#0a1625!important;border-color:#20354d!important;color:#f8fafc!important}
html[data-theme="dark"] .team-live-expanded .matchup-player-row:nth-child(even){background:#0c1a2b!important}
html[data-theme="dark"] .team-live-expanded .matchup-player-name,
html[data-theme="dark"] .team-live-expanded .matchup-player-name strong,
html[data-theme="dark"] .team-live-expanded .matchup-player-metrics strong{color:#f8fafc!important}
html[data-theme="dark"] .team-live-expanded .matchup-player-metrics span,
html[data-theme="dark"] .team-live-expanded .matchup-player-cats{color:#94a3b8!important}
html[data-theme="dark"] .team-live-expanded .matchup-bench{background:#0b1726!important;border-top-color:#142943!important}
html[data-theme="dark"] .team-live-expanded .matchup-bench-title{color:#cbd5e1!important}

/* Symmetric team-page scoreboard layout */
.team-live-matchup-summary{align-items:stretch}
.team-live-side{display:flex;flex-direction:column!important;align-items:stretch!important;gap:7px;min-width:0}
.team-live-name-row,.team-live-side-right .team-live-name-row{display:block;min-width:0;text-align:left}
.team-live-side-right .team-live-name-row{text-align:right}
.team-live-name-row a{display:block;font-size:14px;font-weight:900;line-height:1.1;white-space:normal;overflow:visible;text-overflow:clip}
.team-live-body{display:flex;align-items:flex-end;justify-content:space-between;gap:8px;flex:1;min-width:0}
.team-live-side-right .team-live-body{flex-direction:row}
.team-live-logo{width:58px;height:58px;flex:0 0 58px;object-fit:cover;border-radius:10px}
.team-live-scores{display:flex!important;flex-direction:column;align-items:flex-end!important;gap:3px!important;white-space:nowrap;align-self:flex-end!important}
.team-live-score{display:flex!important;flex-direction:column;align-items:flex-end;gap:0!important;line-height:1!important;transform:none!important}
.team-live-score strong,.team-live-score+.team-live-score strong{line-height:.95!important;font-weight:800;color:#172033}
.team-live-score small,.team-live-score+.team-live-score small{display:block!important;margin:2px 0 0!important;font-size:8px!important;line-height:1!important;font-weight:900;text-transform:uppercase;color:#64748b}
.team-live-score strong.winning{font-weight:900}
.team-live-score-left .team-live-scores{margin-left:auto}
.team-live-score-right .team-live-scores{order:0;align-items:flex-start!important;margin-right:auto}
.team-live-score-right .team-live-score{align-items:flex-start}
.team-live-score-right .team-live-logo{order:1}
.team-live-vs{align-self:center}
@media(max-width:700px){
  .team-live-matchup-summary{gap:5px;padding:9px 25px 9px 7px}
  .team-live-side{gap:5px!important}
  .team-live-name-row a{font-size:11px!important}
  .team-live-body{gap:4px}
  .team-live-logo{width:42px!important;height:42px!important;flex-basis:42px!important;border-radius:7px!important}
  .team-live-score strong,.team-live-score+.team-live-score strong{}
  .team-live-score small,.team-live-score+.team-live-score small{font-size:7px!important}
  .team-live-vs{font-size:7px;margin-top:12px}
}
.team-live-matchup{margin-bottom:10px}.team-live-matchup-summary{padding:7px 28px 7px 9px!important;gap:6px!important}.team-live-body{min-height:0!important;padding-top:4px!important;gap:6px!important}.team-live-logo{width:48px!important;height:48px!important;flex-basis:48px!important}.team-live-matchup-label{margin-bottom:3px!important}.team-matchup-period-label{padding:4px 7px!important}.team-opponent-record{display:block;margin-top:5px;font-size:11px;color:var(--muted);line-height:1.4}.team-future-picks{padding:10px 12px;margin:10px 0 14px}.team-future-picks summary{cursor:pointer;font-weight:800;font-size:13px}.team-future-picks summary span{font-size:11px;color:var(--muted);font-weight:500;margin-left:8px}.team-pick-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(110px,1fr));gap:6px;margin-top:10px}.team-pick{padding:8px;border:1px solid var(--line);border-radius:8px;background:var(--panel-2)}.team-pick strong,.team-pick small{display:block}.team-pick strong{font-size:12px}.team-pick small,.team-pick-note{font-size:10px;color:var(--muted)}

</style>
<style data-matchup-scoreboard-style data-style-version="{{ hash_file('sha256', base_path('public/matchup-scoreboard.css')) }}">{!! file_get_contents(base_path('public/matchup-scoreboard.css')) !!}</style>
<script>
document.addEventListener('DOMContentLoaded',()=>{
  const liveMatchup=document.querySelector('.team-live-matchup-card');
  if(liveMatchup){
    const storageKey='ecfhl-live-matchup-expanded';
    try{
      if(sessionStorage.getItem(storageKey)==='1') liveMatchup.open=true;
      liveMatchup.addEventListener('toggle',()=>sessionStorage.setItem(storageKey,liveMatchup.open?'1':'0'));
    }catch(e){}
  }
  const targetStatePrefix='ecfhl-team-targets:{{ $slug }}:{{ $date }}:';
  document.querySelectorAll('details.team-targets[data-target-position]').forEach(details=>{
    const key=targetStatePrefix+details.dataset.targetPosition;
    details.open=sessionStorage.getItem(key)==='1';
    details.addEventListener('toggle',()=>{
      sessionStorage.setItem(key,details.open?'1':'0');
    });
  });

  const advisorDataElement=document.getElementById('team-lineup-advisor-data');
  const advisorCard=document.querySelector('.team-lineup-advisor');
  if(advisorDataElement&&advisorCard){
    try{
      const advisorData=JSON.parse(advisorDataElement.textContent||'{}');
      const advisors=advisorData.advisors||{};
      const order=Object.keys(advisors);
      let currentKey=order.includes(advisorData.current)?advisorData.current:(order[0]||'');
      const nameSpan=advisorCard.querySelector('[data-advisor-display-name]');
      const imageButton=advisorCard.querySelector('[data-team-icon-viewer][data-advisor-key]');
      const image=imageButton?.querySelector('img');
      const adviceEl=advisorCard.querySelector('[data-advisor-text]');
      const prev=advisorCard.querySelector('[data-advisor-prev]');
      const next=advisorCard.querySelector('[data-advisor-next]');

      const appendLinkedText=(parent,text)=>{
        const leagueId='092zcn40molvao69';
        const playerPattern=/\b(Add|add)\s+(.+?)\s+\(([A-Z]{2,3})\)/g;
        let last=0;
        let match;
        while((match=playerPattern.exec(text))!==null){
          parent.append(document.createTextNode(text.slice(last,match.index)+match[1]+' '));
          const playerName=(match[2]||'').trim();
          const link=document.createElement('a');
          link.href='https://www.fantrax.com/fantasy/league/'+leagueId+'/players;searchName='+encodeURIComponent(playerName)+';positionOrGroup=ALL;';
          link.target='_blank';
          link.rel='noopener noreferrer';
          link.className='team-lineup-player-link';
          link.textContent=playerName;
          parent.append(link,document.createTextNode(' ('+match[3]+')'));
          last=playerPattern.lastIndex;
        }
        parent.append(document.createTextNode(text.slice(last)));
      };

      const renderAdvice=(raw)=>{
        if(!adviceEl)return;
        const text=String(raw||'No moves to suggest.').replaceAll('—',',');
        adviceEl.replaceChildren();
        const marker=/\s*\[GOALIE_STATUS:([^\]]+)\]/ig;
        let last=0;
        let match;
        let found=false;
        while((match=marker.exec(text))!==null){
          found=true;
          appendLinkedText(adviceEl,text.slice(last,match.index));
          let status=(match[1]||'').trim().toLowerCase();
          status=status.charAt(0).toUpperCase()+status.slice(1);
          if(status==='Probable')status='Likely';
          const pill=document.createElement('span');
          pill.className='pill goalie-status '+(
            status==='Confirmed'?'goalie-status-confirmed':
            status==='Likely'?'goalie-status-likely':
            status==='Unconfirmed'?'goalie-status-unconfirmed':'goalie-status-na'
          );
          pill.textContent=status;
          adviceEl.append(document.createTextNode(' '),pill);
          last=marker.lastIndex;
        }
        appendLinkedText(adviceEl,text.slice(last));
        if(!found){
          const original=adviceEl.textContent||'';
          const legacy=/\s+(Confirmed|Likely|Probable|Unconfirmed)\b/i.exec(original);
          if(legacy){
            const before=original.slice(0,legacy.index);
            let status=legacy[1];
            if(status.toLowerCase()==='probable')status='Likely';
            else status=status.charAt(0).toUpperCase()+status.slice(1).toLowerCase();
            const after=original.slice(legacy.index+legacy[0].length);
            const pill=document.createElement('span');
            pill.className='pill goalie-status '+(
              status==='Confirmed'?'goalie-status-confirmed':
              status==='Likely'?'goalie-status-likely':
              status==='Unconfirmed'?'goalie-status-unconfirmed':'goalie-status-na'
            );
            pill.textContent=status;
            adviceEl.replaceChildren();
            appendLinkedText(adviceEl,before+' ');
            adviceEl.append(pill);
            appendLinkedText(adviceEl,after);
          }
        }
      };

      const renderAdvisor=(key)=>{
        if(!advisors[key])return;
        if(advisors[currentKey]&&nameSpan)advisors[currentKey].name=nameSpan.textContent.trim();
        currentKey=key;
        const advisor=advisors[key];
        if(nameSpan){
          nameSpan.textContent=advisor.name;
          nameSpan.dataset.advisorDisplayName=key;
        }
        if(imageButton&&image){
          imageButton.dataset.teamSlug=advisor.slug;
          imageButton.dataset.advisorKey=key;
          imageButton.dataset.advisorFirstName=advisor.name;
          imageButton.title='View '+advisor.name+' advisor image';
          imageButton.setAttribute('aria-label','View '+advisor.name+' Lineup Advisor image');
          image.src=advisor.thumbnail;
          image.dataset.fullSrc=advisor.fullImage;
          image.alt=advisor.name+', Lineup Advisor';
        }
        renderAdvice(advisor.advice);
      };

      let advisorAutoRotate=null;
      const restartAdvisorAutoRotate=()=>{
        if(advisorAutoRotate) clearInterval(advisorAutoRotate);
        if(order.length>1){
          advisorAutoRotate=setInterval(()=>move(1),30000);
        }
      };

      const move=(direction)=>{
        const index=order.indexOf(currentKey);
        const nextIndex=(index+direction+order.length)%order.length;
        renderAdvisor(order[nextIndex]);
      };
      restartAdvisorAutoRotate();
      prev?.addEventListener('click',()=>{move(-1);restartAdvisorAutoRotate();});
      next?.addEventListener('click',()=>{move(1);restartAdvisorAutoRotate();});
      renderAdvisor(currentKey);
    }catch(error){
      console.error('Could not initialize advisor cycle',error);
    }
  }

  const refreshLiveScoreboard=async()=>{
    const current=document.querySelector('.team-live-matchup');
    if(!current)return;

    const mainOpen=current.querySelector('.team-live-matchup-card')?.open??false;
    const subsectionStates=[...current.querySelectorAll('.team-live-subsection')].map((el,index)=>({index,open:el.open}));

    try{
      const response=await fetch(window.location.href,{
        headers:{'X-Requested-With':'XMLHttpRequest','Accept':'text/html'},
        cache:'no-store'
      });
      if(!response.ok)return;

      const html=await response.text();
      const doc=new DOMParser().parseFromString(html,'text/html');
      const fresh=doc.querySelector('.team-live-matchup');
      if(!fresh)return;

      const freshMain=fresh.querySelector('.team-live-matchup-card');
      if(freshMain)freshMain.open=mainOpen;
      subsectionStates.forEach(state=>{
        const subsection=fresh.querySelectorAll('.team-live-subsection')[state.index];
        if(subsection)subsection.open=state.open;
      });

      // Keep the shared scoreboard styles current without reloading the page.
      const currentStyle=document.querySelector('[data-matchup-scoreboard-style]');
      const freshStyle=doc.querySelector('[data-matchup-scoreboard-style]');
      if(currentStyle&&freshStyle&&currentStyle.dataset.styleVersion!==freshStyle.dataset.styleVersion){
        currentStyle.textContent=freshStyle.textContent;
        currentStyle.dataset.styleVersion=freshStyle.dataset.styleVersion;
      }
      current.replaceWith(fresh);
    }catch(error){
      console.warn('Live scoreboard refresh failed',error);
    }
  };

  setInterval(refreshLiveScoreboard,60000);
  document.querySelectorAll('[data-target-more]').forEach(button=>{
    button.addEventListener('click',()=>{
      const list=button.closest('.team-target-list');
      const hidden=[...list.querySelectorAll('[data-target-row][hidden]')];
      hidden.slice(0,10).forEach(row=>row.hidden=false);
      if(hidden.length<=10)button.remove();
    });
  });

  const showNonPlaying=document.getElementById('team-hide-non-playing');
  if(showNonPlaying){
    showNonPlaying.checked=false;

    const applyNonPlaying=()=>{
      const show=!showNonPlaying.checked;
      document.querySelector('.current-team-page')?.classList.toggle('team-hide-non-playing',!show);
      document.querySelectorAll('[data-position-count]').forEach(el=>{
        el.textContent=show?(el.dataset.totalCount||'0'):(el.dataset.playingCount||'0');
      });

    };

    showNonPlaying.addEventListener('change',applyNonPlaying);
    applyNonPlaying();
  }
});
</script>
@endsection
