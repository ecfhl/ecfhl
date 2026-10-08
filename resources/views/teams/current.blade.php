@extends('layouts.app')
@section('content')
@php $teamStanding = collect(\App\Support\CurrentTeams::standings())->firstWhere('slug', $slug); @endphp
<div class="shell individual-team-page" data-team-roster>
  <nav class="team-roster-breadcrumb" aria-label="Team navigation"><a href="/teams/league">‹ Teams</a><span>2026–27 roster</span></nav>
<header class="team-page-head">
  <div class="team-page-head-shell">
    <div class="team-page-head-logo">
      @include('teams.partials.team-icon-uploader',['slug'=>$slug,'name'=>$teamName])
    </div>
    <div class="team-page-head-copy">
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
            <img src="/fantrax-icon.png" width="14" height="14" alt="">Fantrax ↗
          </a>
          <span class="team-roster-date" title="{{ \Carbon\CarbonImmutable::parse($date)->format('l, M j, Y') }}">{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j') }}</span>
        </p>
      @else
        <p class="team-roster-date">{{ \Carbon\CarbonImmutable::parse($date)->format('D, M j, Y') }}</p>
      @endif
      @if($teamStanding)
        <div class="team-standing-summary" aria-label="Season record">
          <span>Rank <strong>{{ $teamStanding['rank'] ?? '—' }}</strong></span>
          <span><strong>{{ $teamStanding['w'] === null ? '—' : $teamStanding['w'].'–'.$teamStanding['l'].'–'.$teamStanding['t'] }}</strong></span>
          <span><strong>{{ $teamStanding['fantasy_points_for'] === null ? '—' : number_format($teamStanding['fantasy_points_for'],0) }}</strong> FPts</span>
        </div>
      @endif
      @if($scoreLastUpdate)
        <p class="team-updated">Updated @include('partials.updated-time',['value'=>$scoreLastUpdate])</p>
      @endif
    </div>
  </div>
</header>
<div class="current-team-page">
  <div class="team-page-controls">
    <div class="team-left-controls">
      <div class="team-date-buttons">
        <a class="button team-date-button {{ $date===$yesterday?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $yesterday }}" @if($date===$yesterday) aria-current="date" @endif>Yesterday</a>
        <a class="button team-date-button {{ $date===$today?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $today }}" @if($date===$today) aria-current="date" @endif>Today</a>
        <a class="button team-date-button {{ $date===$tomorrow?'primary':'team-date-inactive' }}" href="/teams/current/{{ $slug }}?date={{ $tomorrow }}" @if($date===$tomorrow) aria-current="date" @endif>Tomorrow</a>
      </div>
    </div>
  </div>

  <section class="team-roster-section" aria-label="Roster">
  @php
    $hasRows=collect($positions)->sum(fn($g)=>$g['rows']->count())>0;
  @endphp
  @if(!$hasRows)
    <div class="card"><h2>No roster data yet</h2><p class="subtle">Run the Fantasy Team Rosters collector from Collector Status to populate this team.</p>@if(auth()->user()?->is_admin)<a class="button primary" href="/job-status">Collector Status</a>@endif</div>
  @else
  <div class="team-roster-heading"><h2>Roster</h2></div>
    <div class="team-roster-grid">
    @foreach($positions as $code=>$group)
      @php
        $totalCount=$group['rows']->reject(fn($p)=>(bool)$p->is_ir)->count();
      @endphp
      <section class="team-position-section" data-roster-position="{{ $code }}">
        <div class="table-card"><div class="table-scroll"><table class="data-table team-roster-table">
          <colgroup><col><col class="team-roster-score-column"></colgroup>
          <tbody>
            <tr class="team-roster-group team-playing-group">
              <td colspan="2" class="roster-table-heading" data-roster-section="{{ $group['label'] }}">
                <div class="team-playing-header">
                  <span>{{ $group['label'] }} ({{ $totalCount }})</span>
                  <span class="team-score-headings"><span>ECFHL*</span><span>Today</span></span>
                </div>
              </td>
            </tr>
            @foreach($group['rows'] as $player)
                @php
                  $isPlaying=(bool)$player->daily_participant;
                @endphp
                <tr class="team-player-data-row {{ !$isPlaying?'team-not-playing':'' }} {{ $player->is_ir?'team-ir-row':'' }} {{ $player->is_bench?'team-bench-row':'' }} {{ strtoupper((string)$player->roster_status)==='MINORS'?'team-minors-row':'' }} {{ !empty($player->game_in_progress)?'team-game-live-row':'' }} {{ !empty($player->game_finished)?'team-game-finished-row':'' }} {{ ($player->game_status??'')==='1'?'team-game-upcoming-row':'' }}" data-playing="{{ $isPlaying?'1':'0' }}">
                  <td data-label="Player">
                    <div class="team-player-name-wrap">
                      <strong><a class="player-name-link" data-player-stats href="/players/{{ rawurlencode($player->player_id) }}" title="{{ \App\Support\PlayerName::display($player->player_name) }}@if($player->nhl_team) ({{ $player->nhl_team }})@endif"><span class="team-player-display-name">{{ \App\Support\PlayerName::display($player->player_name) }}</span>@if($player->nhl_team)<span class="team-player-nhl-team"> ({{ $player->nhl_team }})</span>@endif</a></strong>
                      @if(!empty($player->contract_label))
                        <span class="pill team-contract-sticker {{ $player->contract_class }}">{{ $player->contract_label }}</span>
                      @endif
                      @if($player->is_ir)
                        <span class="pill team-ir">IR</span>
                      @endif
                      @if(strtoupper((string)$player->position)==='G' && !empty($player->starting_status))
                        <span class="pill goalie-status {{ $player->starting_status_class }}">{{ $player->starting_status }}</span>
                      @endif
                    </div>
                    <div class="team-player-lines">
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
                      @if(!empty($player->live_opponent_display))
                        <span class="{{ !empty($player->game_in_progress)?'team-game-live':'' }} {{ !empty($player->game_finished)?'team-game-finished':'' }}">{{ $player->live_opponent_display }}</span>
                      @elseif($player->opponent)
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

        @if(in_array($code,['F','D','G'],true))
          <details class="team-targets" data-target-position="{{ $code }}">
            <summary>{{ ['F'=>'Forward','D'=>'Defense','G'=>'Goalie'][$code] }} Targets <span>{{ count($targetGroups[$code] ?? []) }}</span></summary>
            <div class="team-target-list">
              @forelse(($targetGroups[$code] ?? []) as $target)
                <div class="team-target-row {{ $code==='G'?'team-goalie-target':'' }} {{ !empty($target['injury_status'])?'team-ir-row':'' }}" data-target-row @if($loop->iteration>5) hidden @endif>
                  <div class="team-target-main">
                    <div class="team-target-name">
                      <strong>{{ \App\Support\PlayerName::display($target['name']) }} ({{ $target['team'] }})</strong>
                      @if($code==='G')@include('account.goalie-bell',['goalie'=>$target])@endif
                      @if(!empty($target['injury_status']))<span class="pill team-ir">IR</span>@endif
                      @if($code==='G')
                    <span class="team-target-lines">
                      <span class="pill team-target-status {{ str_starts_with($target['status'],'FA')?'target-fa':'target-waiver' }}">{{ $target['status'] }}</span>
                      @if(!empty($target['line_number']))
                        @if($code==='G' && $target['line_number']<=2)
                          <span class="pill goalie-{{ $target['line_number'] }}">G{{ $target['line_number'] }}</span>
                        @elseif($target['line_number']<=4)
                          <span class="pill line-{{ $target['line_number'] }}">L{{ $target['line_number'] }}</span>
                        @endif
                      @endif
                      @if(($target['pp_unit']??null)===1)<span class="pill pp1">PP1</span>@elseif(($target['pp_unit']??null)===2)<span class="pill pp2">PP2</span>@endif
                    </span>
                      @endif
                    </div>
                    <div class="team-target-opponent">
                    @if($code!=='G')
                    <span class="team-target-lines">
                      <span class="pill team-target-status {{ str_starts_with($target['status'],'FA')?'target-fa':'target-waiver' }}">{{ $target['status'] }}</span>
                      @if(!empty($target['line_number']))
                        @if($code==='G' && $target['line_number']<=2)
                          <span class="pill goalie-{{ $target['line_number'] }}">G{{ $target['line_number'] }}</span>
                        @elseif($target['line_number']<=4)
                          <span class="pill line-{{ $target['line_number'] }}">L{{ $target['line_number'] }}</span>
                        @endif
                      @endif
                      @if(($target['pp_unit']??null)===1)<span class="pill pp1">PP1</span>@elseif(($target['pp_unit']??null)===2)<span class="pill pp2">PP2</span>@endif
                    </span>
                    @endif
                      @if(!empty($target['opponent']))<span>{{ $target['opponent'] }}@if(!empty($target['game_time'])) · {{ $target['game_time'] }}@endif</span>@endif
                      @if($code==='G')
                        @if(!empty($target['starting_status']))
                          <span class="pill goalie-status {{ $target['starting_status_class'] ?? 'goalie-status-na' }}">{{ $target['starting_status'] }}</span>
                        @else
                          <span class="pill goalie-status goalie-status-na">NA</span>
                        @endif
                      @endif
                    @if(($target['position'] ?? '') === 'G' && array_key_exists('vegas_odds',$target) && $target['vegas_odds']!==null)
                      <span class="pill goalie-vegas-odds {{ $target['vegas_odds_class'] }}">{{ $target['vegas_odds']>0?'+':'' }}{{ (int)$target['vegas_odds'] }}</span>
                    @endif
                    </div>
                  </div>
                  <div class="team-target-actions">
                    <div class="team-target-stats" title="Season / Last 21 / Last 7 / My Projection">
                      <span><small>SEASON</small>{{ isset($target['season_fpts_per_game'])&&$target['season_fpts_per_game']!==null?number_format($target['season_fpts_per_game'],2):'—' }}</span>
                      <span><small>L21</small>{{ isset($target['fpts_per_game_21d'])&&$target['fpts_per_game_21d']!==null?number_format($target['fpts_per_game_21d'],2):'—' }}</span>
                      <span><small>L7</small>{{ isset($target['fpts_per_game_7d'])&&$target['fpts_per_game_7d']!==null?number_format($target['fpts_per_game_7d'],2):'—' }}</span>
                      <strong class="team-target-proj"><small>ECFHL*</small>{{ $target['projected_points']!==null?number_format($target['projected_points'],2):'—' }}</strong>
                    </div>
                    @if(!empty($target['add_url']))
                      <a class="team-target-add" href="{{ $target['add_url'] }}" target="_blank" rel="noopener noreferrer">+ Add</a>
                    @endif
                  </div>
                </div>
              @empty
                <p class="team-target-empty">No available targets for this date.</p>
              @endforelse
              @if(count($targetGroups[$code] ?? [])>5)
                <button type="button" class="team-target-more" data-target-more>View more</button>
              @endif
            </div>
          </details>
        @endif
      </section>
    @endforeach
    </div>
  @endif
  </section>

  <details class="card team-future-picks"><summary>2027 Draft Picks <span>{{ count($futurePicks['picks']) }} available</span></summary>
    @if($futurePicks['franchise_id'])<div class="team-pick-grid">@forelse($futurePicks['picks'] as $pick)<div class="team-pick"><strong>Round {{ $pick['round'] }}</strong><small>{{ $pick['original_team'] }}</small></div>@empty<p class="muted">No picks remaining.</p>@endforelse</div><p class="muted team-pick-note">Based on recorded completed trades. Picks may be passed or traded once the roster is full; draft order will be set after the season.</p>@else<p class="muted">Draft ownership is unavailable for this team.</p>@endif
  </details>

  <div class="team-summary-grid">
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
                <span class="team-live-score team-live-today"><strong class="{{ $teamDayWinning?'winning':'' }}">{{ number_format($liveMatchup['team_today'],0) }}</strong><small>Daily</small></span>
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
                <span class="team-live-score team-live-today"><strong class="{{ $oppDayWinning?'winning':'' }}">{{ number_format($liveMatchup['opponent_today'],0) }}</strong><small>Daily</small></span>
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
              'Defense'=>$playing->filter(fn($p)=>
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
          foreach(['Forwards','Defense','Goalies','Bench','IR','Minors'] as $sectionName){
            $left=$teamSections[$sectionName]??collect();
            $right=$oppSections[$sectionName]??collect();
            $max=max($left->count(),$right->count());
            while($left->count()<$max)$left->push(null);
            while($right->count()<$max)$right->push(null);
            $alignedSections[$sectionName]=['team'=>$left,'opp'=>$right];
          }
        @endphp
        <div class="matchup-expanded team-live-expanded">
          @foreach(['Forwards','Defense','Goalies','Bench','IR','Minors'] as $sectionName)
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
                  <summary class="team-live-section-title team-live-section-toggle roster-table-heading" data-roster-section="{{ $sectionName }}">
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
                <div class="team-live-section-title roster-table-heading" data-roster-section="{{ $sectionName }}">
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
            <div class="team-next-lineup-heading roster-table-heading" data-roster-section="{{ $nextLabel }}">{{ $nextLabel }} ({{ $nextPlayers->reject(fn($p)=>(bool)$p->is_ir)->count() }})</div>
            @forelse($nextPlayers as $nextPlayer)
              <div class="team-next-lineup-player">
                <a class="player-name-link" data-player-stats href="/players/{{ rawurlencode($nextPlayer->player_id) }}">{{ \App\Support\PlayerName::display($nextPlayer->player_name) }}@if($nextPlayer->nhl_team) ({{ $nextPlayer->nhl_team }})@endif</a>
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

  </div>

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

      $advisorNameReplacements=[];
      $advisorPlayerNames=collect($positions)->flatMap(fn($g)=>$g['rows'])->pluck('player_name')
        ->merge(collect($targetGroups)->flatten(1)->pluck('name'));
      foreach($advisorPlayerNames->filter()->unique() as $rawName){
        $formatted=\App\Support\PlayerName::display($rawName);
        $advisorNameReplacements[$rawName]=$formatted;
        if(str_contains($formatted,',')){
          [$last,$first]=array_map('trim',explode(',',$formatted,2));
          $advisorNameReplacements[$first.' '.$last]=$formatted;
        }
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
          'advice'=>strtr($advice,$advisorNameReplacements),
          'thumbnail'=>\App\Support\TeamImages::url($key==='mike'?'lineup-advisor':'lineup-advisor-'.$key,640),
          'fullImage'=>\App\Support\TeamImages::url($key==='mike'?'lineup-advisor':'lineup-advisor-'.$key),
        ];
      }
    @endphp
    <div class="team-lineup-advisor-title roster-table-heading" data-roster-section="Lineup Advisor">
      <button type="button" class="team-lineup-advisor-cycle" data-advisor-prev aria-label="Previous advisor">‹</button>
      <div class="team-lineup-advisor-title-text">Lineup Advisor · <span data-advisor-display-name="{{ $advisorKey }}">{{ $advisorFirstName }}</span></div>
      <button type="button" class="team-lineup-advisor-cycle" data-advisor-next aria-label="Next advisor">›</button>
    </div>
      <div class="team-lineup-meta">
        <div class="team-lineup-roster-counts">
          <span class="{{ ($rosterCounts['F'] ?? 0) != 8 ? 'team-lineup-count-alert' : '' }}">Forwards: <strong>{{ $rosterCounts['F'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['D'] ?? 0) != 4 ? 'team-lineup-count-alert' : '' }}">Defense: <strong>{{ $rosterCounts['D'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['G'] ?? 0) != 1 ? 'team-lineup-count-alert' : '' }}">Goalies: <strong>{{ $rosterCounts['G'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['Bench'] ?? 0) != 3 ? 'team-lineup-count-alert' : '' }}">Bench: <strong>{{ $rosterCounts['Bench'] ?? 0 }}</strong></span>
          <span class="{{ ($rosterCounts['IR'] ?? 0) > 5 ? 'team-lineup-count-alert' : '' }}">IR: <strong>{{ $rosterCounts['IR'] ?? 0 }}/5</strong></span>
          <span>Minors: <strong>{{ $rosterCounts['Minors'] ?? 0 }}</strong></span>
        </div>
        @if($movesLeftToday!==null)
          <div class="team-lineup-moves">Moves: <strong>{{ max(0, 7 - $movesLeftToday) }}/7</strong></div>
        @endif
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
</div>
</div>

@push('styles')
<style data-matchup-scoreboard-style data-style-version="{{ hash_file('sha256', base_path('public/matchup-scoreboard.css')) }}">{!! file_get_contents(base_path('public/matchup-scoreboard.css')) !!}</style>
@endpush
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


});
</script>
@endsection
@push('styles')
@push('styles')
<link rel="stylesheet" href="/team-roster.css?v={{ hash_file('sha256', base_path('public/team-roster.css')) }}">
@endpush
@endpush
