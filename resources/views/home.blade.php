@extends('layouts.app')
@section('title','Overview: Home')
@section('content')
<section class="home-hero"><div class="shell home-hero-inner">
  <button type="button" class="league-logo-viewer home-league-logo" data-team-icon-viewer data-league-logo data-team-name="East Coast Fantasy Hockey League" aria-label="View East Coast Fantasy Hockey League logo"><img src="/ecfhl-logo.png" alt="East Coast Fantasy Hockey League logo" width="140" height="140"></button>
  <div><div class="eyebrow">Established in 2007</div><h1>East Coast Fantasy Hockey League</h1><p>Your 2026–27 season hub. Follow the matchups, track the standings, and build your next winning lineup.</p><span class="home-season-chip">2026–27 Season</span></div>
</div></section>
<div class="shell season-home">
  <div class="section-title"><h2>Overview: Home</h2><span class="muted">{{ \Carbon\CarbonImmutable::parse($today)->format('l, M j') }}</span></div>
  <div class="home-shortcuts"><a href="#" data-my-team-link><span>★</span><strong>My Team</strong><small>Roster & lineup advisor</small></a><a href="/teams/current"><span>●</span><strong>Live Scoring</strong><small>Today's matchup scores</small></a><a href="/players"><span>🏒</span><strong>Players</strong><small>Stats & projections</small></a><a href="/daily-targets"><span>🎯</span><strong>Daily Targets</strong><small>Available players to add</small></a></div>
  <div class="home-season-grid">
    <section class="card home-section"><div class="section-title"><div class="eyebrow">This week</div><a href="/teams/current">Live Scoring →</a></div><h2>Current Matchups</h2>
      @forelse($matchups as $matchup)
        @php
          $awaySlug=\Illuminate\Support\Str::slug($matchup->away_team_name);$homeSlug=\Illuminate\Support\Str::slug($matchup->home_team_name);
          $awayLive=collect($snapshot['teams']??[])->firstWhere('name',$matchup->away_team_name);$homeLive=collect($snapshot['teams']??[])->firstWhere('name',$matchup->home_team_name);
        @endphp
        <div class="home-matchup"><a href="/teams/current/{{ $awaySlug }}"><img src="{{ \App\Support\TeamImages::url($awaySlug,64) }}" alt="" width="32" height="32" loading="lazy"><span>{{ $matchup->away_team_name }}</span></a><strong>{{ number_format($awayLive['period_fpts'] ?? $matchup->away_score ?? 0,0) }} <small>–</small> {{ number_format($homeLive['period_fpts'] ?? $matchup->home_score ?? 0,0) }}</strong><a href="/teams/current/{{ $homeSlug }}"><span>{{ $matchup->home_team_name }}</span><img src="{{ \App\Support\TeamImages::url($homeSlug,64) }}" alt="" width="32" height="32" loading="lazy"></a></div>
      @empty<p class="muted">Current matchups will appear after the schedule collector refreshes.</p>@endforelse
    </section>
    <section class="card home-section"><div class="section-title"><div class="eyebrow">Regular season</div><a href="/standings">Full standings →</a></div><h2>League Standings</h2>
      @forelse(array_slice($standings,0,5) as $team)<a class="home-leader" href="/teams/current/{{ $team['slug'] }}"><span class="home-rank">{{ $team['rank'] ?? '—' }}</span><img src="{{ \App\Support\TeamImages::url($team['slug'],64) }}" width="32" height="32" alt="" loading="lazy"><div><strong>{{ $team['team'] }}</strong><small>{{ $team['w'] ?? 0 }}–{{ $team['l'] ?? 0 }}–{{ $team['t'] ?? 0 }}</small></div><b>{{ isset($team['fantasy_points_for']) ? number_format($team['fantasy_points_for'],0) : '—' }}<small>FPts</small></b></a>@empty<p class="muted">Standings are awaiting the next refresh.</p>@endforelse
    </section>
    <section class="card home-section"><div class="section-title"><div class="eyebrow">Player watch</div><a href="/players?availability=all">All players →</a></div><h2>Season Scoring Leaders</h2>
      @forelse($scoringLeaders as $player)<a class="home-leader" href="/players/{{ rawurlencode($player->player_id) }}"><span class="home-rank">{{ $loop->iteration }}</span><div><strong>{{ $player->player_name }}</strong><small>{{ $player->position }} · {{ $player->nhl_team }}</small></div><b>{{ number_format($player->season_fpts,0) }}<small>FPts</small></b></a>@empty<p class="muted">Player stats will appear after the season refresh.</p>@endforelse
    </section>
    <section class="card home-section"><div class="eyebrow">Around the league</div><h2>Today's NHL Games</h2><p class="home-time-note muted">Times in Atlantic · Fantasy day follows Pacific time</p>
      @php $shownGames=[]; @endphp
      @forelse($games as $team=>$game)@php $pair=[$team,$game['opponent']];sort($pair);$key=implode('|',$pair); @endphp @if(isset($shownGames[$key])) @continue @endif @php $shownGames[$key]=true; @endphp
        <div class="home-nhl-game"><strong>{{ $game['away'] ? $team : $game['opponent'] }} <span class="muted">@</span> {{ $game['away'] ? $game['opponent'] : $team }}</strong><span>{{ $game['time'] ?: 'Time unavailable' }}</span></div>
      @empty<p class="muted">No games listed for today.</p>@endforelse
      <a class="home-target-link" href="/daily-targets">Find players for today's games →</a>
    </section>
  </div>
  <div class="home-history-link"><span>19 years of league history</span><a href="/seasons">Explore the archive →</a></div>
</div>
<link rel="stylesheet" href="/season-home.css?v=1">
@endsection
