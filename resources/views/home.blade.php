@extends('layouts.app')
@section('title', 'Overview: Home')
@section('content')
<link rel="stylesheet" href="/overview-home.css?v={{ hash_file('sha256', base_path('public/overview-home.css')) }}">
<link rel="stylesheet" href="/matchup-scoreboard.css?v={{ hash_file('sha256', base_path('public/matchup-scoreboard.css')) }}">
<section class="overview-hero">
  <div class="shell overview-hero__inner">
    <button type="button" class="league-logo-viewer overview-hero__logo" data-team-icon-viewer data-league-logo data-team-name="East Coast Fantasy Hockey League" aria-label="View East Coast Fantasy Hockey League logo">
      <img src="{{ \App\Support\TeamImages::url('league-logo',160) }}" data-full-src="{{ \App\Support\TeamImages::url('league-logo') }}" alt="East Coast Fantasy Hockey League logo" width="140" height="140">
    </button>
    <div>
      <div class="eyebrow">Established in 2007</div>
      <h1>East Coast Fantasy Hockey League</h1>
      <p>Your 2026–27 season hub. Follow the matchups, track the standings, and build your next winning lineup.</p>
      <span class="overview-hero__season">2026–27 Season</span>
    </div>
  </div>
</section>
<div class="shell overview-home">
  <div class="overview-home__heading">
    <h2>Overview: Home</h2>
    <span class="muted">{{ \Carbon\CarbonImmutable::parse($today)->format('l, M j') }}</span>
  </div>
  @php
    $myTeamName = request()->user()?->claim?->team_name;
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
          $liveTeams=$snapshot ? app(\App\Support\LiveScoring\ViewData::class)->teams($snapshot) : [];
          $awayLive=collect($liveTeams)->firstWhere('name',$matchup->away_team_name);
          $homeLive=collect($liveTeams)->firstWhere('name',$matchup->home_team_name);
        @endphp
        <div class="overview-matchup ecfhl-scoreboard {{ $myTeamName && ($matchup->away_team_name === $myTeamName || $matchup->home_team_name === $myTeamName) ? 'overview-matchup--mine' : '' }}">
          <div class="team-live-matchup-summary">
            <div class="team-live-side team-live-score-left">
              <div class="team-live-name-row">
                <a href="/teams/current/{{ $awaySlug }}">{{ $matchup->away_team_name }}</a>
                <button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $awaySlug }}" data-team-name="{{ $matchup->away_team_name }}" aria-label="View {{ $matchup->away_team_name }} logo"><img src="{{ \App\Support\TeamImages::url($awaySlug,160) }}" data-full-src="{{ \App\Support\TeamImages::url($awaySlug) }}" alt="{{ $matchup->away_team_name }} team icon" width="160" height="160" loading="lazy" decoding="async"></button>
              </div>
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
                <a href="/teams/current/{{ $homeSlug }}">{{ $matchup->home_team_name }}</a>
                <button type="button" class="team-logo-viewer team-live-logo" data-team-icon-viewer data-team-slug="{{ $homeSlug }}" data-team-name="{{ $matchup->home_team_name }}" aria-label="View {{ $matchup->home_team_name }} logo"><img src="{{ \App\Support\TeamImages::url($homeSlug,160) }}" data-full-src="{{ \App\Support\TeamImages::url($homeSlug) }}" alt="{{ $matchup->home_team_name }} team icon" width="160" height="160" loading="lazy" decoding="async"></button>
              </div>
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
        <a class="overview-standing {{ $loop->iteration===8 ? 'overview-standing--cut' : '' }}" href="/teams/current/{{ $team['slug'] }}">
          <span class="overview-rank">{{ $team['rank'] ?? '—' }}</span>
          <img src="{{ \App\Support\TeamImages::url($team['slug'],64) }}" width="32" height="32" alt="" loading="lazy">
          <div><strong>{{ $team['team'] }}</strong><small>{{ $team['w'] ?? 0 }}–{{ $team['l'] ?? 0 }}–{{ $team['t'] ?? 0 }}</small></div>
          <b>{{ isset($team['fantasy_points_for']) ? number_format($team['fantasy_points_for'],0) : '—' }}<small>FPts</small></b>
        </a>
      @empty
        <p class="overview-home__empty">Standings are awaiting the next refresh.</p>
      @endforelse
    </section>
    <section class="overview-home__card" data-overview-card="watch">
      <div class="overview-home__title"><h2>Player Watch</h2><a href="/players?availability=available&amp;positions=F,D,G&amp;dfo_sort=1&amp;sort=ec_proj&amp;direction=desc">Available players →</a></div>
      @foreach(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $position=>$label)
        <div class="overview-player-group">
          <h3>{{ $label }}</h3>
          @forelse($scoringLeaders->get($position,collect()) as $player)
            <a class="overview-player player-name-link" data-player-stats href="/players/{{ rawurlencode($player->player_id) }}">
              <span class="overview-rank">{{ $loop->iteration }}</span>
              <span class="overview-player__name"><strong>{{ $player->player_name }}</strong><small>{{ $player->nhl_team }}</small></span>
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
<style>
.overview-matchup .team-live-name-row{min-width:0}
.overview-matchup .team-live-name-row>a{display:block;min-width:0;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
</style>
@endsection
