@extends('layouts.app')
@section('title', 'Home · ECFHL')
@section('content')
@auth<div class="shell home-league-chat">@include('communication.chat-widget')</div>@endauth
@push('styles')
<link rel="stylesheet" href="/matchup-scoreboard.css?v={{ hash_file('sha256', base_path('public/matchup-scoreboard.css')) }}">
@endpush
@push('styles')
<link rel="stylesheet" href="/overview-home.css?v={{ hash_file('sha256', base_path('public/overview-home.css')) }}">
@endpush
<section class="overview-hero">
  <div class="shell overview-hero__inner">
    <span class="overview-home__date">{{ \Carbon\CarbonImmutable::parse($today)->format('l, M j') }}</span>
    <button type="button" class="league-logo-viewer overview-hero__logo" data-team-icon-viewer data-league-logo data-team-name="East Coast Fantasy Hockey League" aria-label="View East Coast Fantasy Hockey League logo">
      <img src="{{ \App\Support\TeamImages::url('league-logo',160) }}" data-full-src="{{ \App\Support\TeamImages::url('league-logo') }}" alt="East Coast Fantasy Hockey League logo" width="140" height="140">
    </button>
    <div>
      <div class="eyebrow">Established in 2007</div>
      <h1>Home</h1>
      <p>East Coast Fantasy Hockey League</p>
    </div>
  </div>
</section>
<div class="shell overview-home">
  @php
    $myTeamName = request()->user()?->claim?->team_name;
    $liveTeams=$snapshot ? app(\App\Support\LiveScoring\ViewData::class)->teams($snapshot) : [];
    $scoreboardStandings=collect($standings)->keyBy('slug');
    if ($myTeamName) {
      $matchups = $matchups->sortByDesc(fn($m) => $m->away_team_name === $myTeamName || $m->home_team_name === $myTeamName)->values();
    }
  @endphp
  {{-- The default DOM is the mobile reading order. Desktop groups these same cards into two stacks. --}}
  <div class="overview-home__cards" data-overview-cards>
    <section class="overview-home__card" data-overview-card="week">
      <div class="overview-home__title"><h2>This Week</h2><a href="/teams/current">Live Scoring →</a></div>
      @forelse($matchups as $matchup)
        @php
          $awaySlug=\Illuminate\Support\Str::slug($matchup->away_team_name);
          $homeSlug=\Illuminate\Support\Str::slug($matchup->home_team_name);
          $awayLive=collect($liveTeams)->firstWhere('name',$matchup->away_team_name);
          $homeLive=collect($liveTeams)->firstWhere('name',$matchup->home_team_name);
          $awayStanding=\App\Support\CurrentTeams::scoreboardStanding($scoreboardStandings->get($awaySlug));
          $homeStanding=\App\Support\CurrentTeams::scoreboardStanding($scoreboardStandings->get($homeSlug));
        @endphp
        <div class="overview-matchup ecfhl-scoreboard {{ $myTeamName && ($matchup->away_team_name === $myTeamName || $matchup->home_team_name === $myTeamName) ? 'overview-matchup--mine' : '' }}">
          <a class="overview-matchup-link" href="/teams/current?matchup={{ rawurlencode($awaySlug) }}" aria-label="View {{ $matchup->away_team_name }} versus {{ $matchup->home_team_name }} matchup"></a>
          <div class="team-live-matchup-summary">
            <div class="team-live-side team-live-score-left">
              <div class="team-live-name-row">
                <a href="/teams/current/{{ $awaySlug }}" title="{{ $matchup->away_team_name }}{{ $awayStanding['rank_label'] ? ' ('.$awayStanding['rank_label'].')' : '' }}"><span class="team-live-team-name">{{ $matchup->away_team_name }}</span>@if($awayStanding['rank_label'])<span class="team-live-rank">({{ $awayStanding['rank_label'] }})</span>@endif</a>
                <button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $awaySlug }}" data-team-name="{{ $matchup->away_team_name }}" aria-label="View {{ $matchup->away_team_name }} logo"><img src="{{ \App\Support\TeamImages::url($awaySlug,160) }}" data-full-src="{{ \App\Support\TeamImages::url($awaySlug) }}" alt="{{ $matchup->away_team_name }} team icon" width="160" height="160" loading="lazy" decoding="async"></button>
              </div>
              @if($awayStanding['record'] !== null)<span class="team-live-record" aria-label="{{ $matchup->away_team_name }} win-loss-tie record">{{ $awayStanding['record'] }}</span>@endif
              <div class="team-live-body">
                <div class="team-live-scores">
                  <span class="team-live-score team-live-weekly overview-matchup-weekly"><strong>{{ number_format($awayLive['week_fpts'] ?? $matchup->away_score ?? 0,0) }}</strong><small>Weekly</small></span>
                  <span class="team-live-score team-live-today overview-matchup-daily"><strong>{{ number_format($awayLive['today_fpts'] ?? 0,0) }}</strong><small>Daily</small></span>
                </div>
              </div>
            </div>
            <div class="team-live-vs">VS</div>
            <div class="team-live-side team-live-side-right team-live-score-right">
              <div class="team-live-name-row">
                <a href="/teams/current/{{ $homeSlug }}" title="{{ $matchup->home_team_name }}{{ $homeStanding['rank_label'] ? ' ('.$homeStanding['rank_label'].')' : '' }}"><span class="team-live-team-name">{{ $matchup->home_team_name }}</span>@if($homeStanding['rank_label'])<span class="team-live-rank">({{ $homeStanding['rank_label'] }})</span>@endif</a>
                <button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $homeSlug }}" data-team-name="{{ $matchup->home_team_name }}" aria-label="View {{ $matchup->home_team_name }} logo"><img src="{{ \App\Support\TeamImages::url($homeSlug,160) }}" data-full-src="{{ \App\Support\TeamImages::url($homeSlug) }}" alt="{{ $matchup->home_team_name }} team icon" width="160" height="160" loading="lazy" decoding="async"></button>
              </div>
              @if($homeStanding['record'] !== null)<span class="team-live-record" aria-label="{{ $matchup->home_team_name }} win-loss-tie record">{{ $homeStanding['record'] }}</span>@endif
              <div class="team-live-body">
                <div class="team-live-scores">
                  <span class="team-live-score team-live-today overview-matchup-daily"><strong>{{ number_format($homeLive['today_fpts'] ?? 0,0) }}</strong><small>Daily</small></span>
                  <span class="team-live-score team-live-weekly overview-matchup-weekly"><strong>{{ number_format($homeLive['week_fpts'] ?? $matchup->home_score ?? 0,0) }}</strong><small>Weekly</small></span>
                </div>
              </div>
            </div>
          </div>
        </div>
      @empty
        <p class="overview-home__empty">Current matchups will appear after the schedule collector refreshes.</p>
      @endforelse
    </section>
    <section class="overview-home__card" data-overview-card="standings">
      <div class="overview-home__title"><h2>Regular Season</h2><a href="/standings">Full standings →</a></div>
      @forelse(array_slice($standings,0,14) as $team)
        <a class="overview-standing {{ $loop->iteration===8 ? 'overview-standing--cut' : '' }} {{ $myTeamName === $team['team'] ? 'overview-standing--mine' : '' }}" href="/teams/current/{{ $team['slug'] }}">
          <span class="overview-rank">{{ $team['rank'] ?? '—' }}</span>
          <img src="{{ \App\Support\TeamImages::url($team['slug'],64) }}" width="32" height="32" alt="" loading="lazy">
          <div><strong>{{ $team['team'] }}</strong><small>{{ $team['w'] ?? 0 }}–{{ $team['l'] ?? 0 }}–{{ $team['t'] ?? 0 }} ({{ isset($team['fantasy_points_for']) ? number_format($team['fantasy_points_for'],0) : '—' }} Fpts)</small></div>
          <b>{{ 2 * (int)($team['w'] ?? 0) + (int)($team['t'] ?? 0) }}<small>Points</small></b>
        </a>
      @empty
        <p class="overview-home__empty">Standings are awaiting the next refresh.</p>
      @endforelse
    </section>
    <section class="overview-home__card" data-overview-card="watch">
      <div class="overview-home__title"><h2>Player Watch</h2><a href="/players?availability=available&amp;positions=F,D,G&amp;dfo_sort=1&amp;sort=ec_proj&amp;direction=desc">Available players →</a></div>
      @foreach(['F'=>'Forwards','D'=>'Defense','G'=>'Goalies'] as $position=>$label)
        <div class="overview-player-group overview-player-group--{{ strtolower($position) }}">
          <h3>{{ $label }}</h3>
          @forelse($scoringLeaders->get($position,collect()) as $player)
            <a class="overview-player player-name-link" data-player-stats href="/players/{{ rawurlencode($player->player_id) }}">
              <span class="overview-rank">{{ $loop->iteration }}</span>
              <span class="overview-player__name"><strong>{{ \App\Support\PlayerName::display($player->player_name) }}</strong><small>{{ $player->nhl_team }}</small></span>
              <b>{{ number_format($player->season_fpts,0) }} <small>FPts</small></b>
            </a>
          @empty
            <p class="overview-home__empty">No available players listed.</p>
          @endforelse
        </div>
      @endforeach
    </section>
    <section class="overview-home__card" data-overview-card="league">
      <div class="overview-home__title">
        <h2>Around the League</h2>
        <a class="overview-score-link" href="https://www.thescore.com/nhl/events/{{ $today }}" target="_blank" rel="noopener" aria-label="View today's NHL games on theScore"><span class="overview-score-mark" aria-hidden="true">S</span></a>
      </div>
      <p class="overview-time-note">Times in Atlantic · Fantasy day follows Pacific time</p>
      @php $shownGames=[]; @endphp
      @forelse($games as $team=>$game)
        @php $pair=[$team,$game['opponent']]; sort($pair); $key=implode('|',$pair); @endphp
        @if(isset($shownGames[$key])) @continue @endif
        @php $shownGames[$key]=true; @endphp
        <div class="overview-game"><strong>{{ $game['away'] ? $team : $game['opponent'] }} <span class="muted">@</span> {{ $game['away'] ? $game['opponent'] : $team }}</strong><span>{{ $game['time'] ?: 'Time unavailable' }}</span></div>
      @empty
        <p class="overview-home__empty">No games listed for today.</p>
      @endforelse
      <a class="overview-target-link" href="/players?playing=today&amp;dfo_sort=1&amp;sort=ec_proj&amp;direction=desc">Find players for today's games →</a>
    </section>
  </div>
  <div class="overview-home__history"><span>19 years of league history</span><a href="/seasons">Explore the archive →</a></div>
</div>
<script src="/overview-home.js?v={{ hash_file('sha256', base_path('public/overview-home.js')) }}"></script>

@endsection
