@extends('layouts.app')
@section('title','Overview: Home')
@section('content')
<section class="home-hero"><div class="shell home-hero-inner">
  <button type="button" class="league-logo-viewer home-league-logo" data-team-icon-viewer data-league-logo data-team-name="East Coast Fantasy Hockey League" aria-label="View East Coast Fantasy Hockey League logo"><img src="{{ \App\Support\TeamImages::url('league-logo',160) }}" data-full-src="{{ \App\Support\TeamImages::url('league-logo') }}" alt="East Coast Fantasy Hockey League logo" width="140" height="140"></button>
  <div><div class="eyebrow">Established in 2007</div><h1>East Coast Fantasy Hockey League</h1><p>Your 2026–27 season hub. Follow the matchups, track the standings, and build your next winning lineup.</p><span class="home-season-chip">2026–27 Season</span></div>
</div></section>
<div class="shell season-home">
  <div class="section-title"><h2>Overview: Home</h2><span class="muted">{{ \Carbon\CarbonImmutable::parse($today)->format('l, M j') }}</span></div>
  <div class="home-shortcuts"><a href="#" data-my-team-link><span>★</span><strong>My Team</strong><small>Roster & lineup advisor</small></a><a href="/teams/current"><span>●</span><strong>Live Scoring</strong><small>Today's matchup scores</small></a><a href="/players"><span>🏒</span><strong>Players</strong><small>Stats & projections</small></a><a href="/daily-targets"><span>🎯</span><strong>Daily Targets</strong><small>Available players to add</small></a></div>
  <div class="home-season-grid">
    <section class="card home-section home-this-week"><div class="section-title"><div class="eyebrow">This week</div><a href="/teams/current">Live Scoring →</a></div>
      @forelse($matchups as $matchup)
        @php
          $awaySlug=\Illuminate\Support\Str::slug($matchup->away_team_name);$homeSlug=\Illuminate\Support\Str::slug($matchup->home_team_name);
          $awayLive=collect($snapshot['teams']??[])->firstWhere('name',$matchup->away_team_name);$homeLive=collect($snapshot['teams']??[])->firstWhere('name',$matchup->home_team_name);
        @endphp
        <div class="home-matchup"><a href="/teams/current/{{ $awaySlug }}"><img src="{{ \App\Support\TeamImages::url($awaySlug,64) }}" alt="" width="32" height="32" loading="lazy"><span>{{ $matchup->away_team_name }}</span></a><strong>{{ number_format($awayLive['period_fpts'] ?? $matchup->away_score ?? 0,0) }} <small>–</small> {{ number_format($homeLive['period_fpts'] ?? $matchup->home_score ?? 0,0) }}</strong><a href="/teams/current/{{ $homeSlug }}"><span>{{ $matchup->home_team_name }}</span><img src="{{ \App\Support\TeamImages::url($homeSlug,64) }}" alt="" width="32" height="32" loading="lazy"></a></div>
      @empty<p class="muted">Current matchups will appear after the schedule collector refreshes.</p>@endforelse
    </section>
    <section class="card home-section home-player-watch"><div class="section-title"><div class="eyebrow">Player watch</div><a href="/players?availability=all">All players →</a></div>
      @forelse(['F'=>'Forwards','D'=>'Defensemen','G'=>'Goalies'] as $position=>$label)
        @php $positionLeaders=$scoringLeaders->get($position,collect()); @endphp
        @if($positionLeaders->isNotEmpty())<h3 class="home-player-position">{{ $label }}</h3>@endif
        @foreach($positionLeaders as $player)<a class="home-leader player-name-link" data-player-stats href="/players/{{ rawurlencode($player->player_id) }}"><span class="home-rank">{{ $loop->iteration }}</span><div><strong>{{ $player->player_name }}</strong><small>{{ $player->nhl_team }}</small></div><b>{{ number_format($player->season_fpts,0) }}<small>FPts</small></b></a>@endforeach
      @empty<p class="muted">Player stats will appear after the season refresh.</p>@endforelse
    </section>
    <section class="card home-section home-regular-season"><div class="section-title"><div class="eyebrow">Regular season</div><a href="/standings">Full standings →</a></div>
      @forelse(array_slice($standings,0,14) as $team)<a class="home-leader {{ $loop->iteration===8 ? 'home-playoff-cut' : '' }}" href="/teams/current/{{ $team['slug'] }}"><span class="home-rank">{{ $team['rank'] ?? '—' }}</span><img src="{{ \App\Support\TeamImages::url($team['slug'],64) }}" width="32" height="32" alt="" loading="lazy"><div><strong>{{ $team['team'] }}</strong><small>{{ $team['w'] ?? 0 }}–{{ $team['l'] ?? 0 }}–{{ $team['t'] ?? 0 }}</small></div><b>{{ isset($team['fantasy_points_for']) ? number_format($team['fantasy_points_for'],0) : '—' }}<small>FPts</small></b></a>@empty<p class="muted">Standings are awaiting the next refresh.</p>@endforelse
    </section>
    <section class="card home-section home-around-league"><div class="section-title"><div class="eyebrow">Around the league</div><a class="home-thescore-link" href="https://www.thescore.com/nhl/events/{{ $today }}" target="_blank" rel="noopener" aria-label="View today's NHL games on theScore"><span class="thescore-mark" aria-hidden="true">S</span></a></div><p class="home-time-note muted">Times in Atlantic · Fantasy day follows Pacific time</p>
      @php $shownGames=[]; @endphp
      @forelse($games as $team=>$game)@php $pair=[$team,$game['opponent']];sort($pair);$key=implode('|',$pair); @endphp @if(isset($shownGames[$key])) @continue @endif @php $shownGames[$key]=true; @endphp
        <div class="home-nhl-game"><strong>{{ $game['away'] ? $team : $game['opponent'] }} <span class="muted">@</span> {{ $game['away'] ? $game['opponent'] : $team }}</strong><span>{{ $game['time'] ?: 'Time unavailable' }}</span></div>
      @empty<p class="muted">No games listed for today.</p>@endforelse
      <a class="home-target-link" href="/daily-targets">Find players for today's games →</a>
    </section></div>
  </div>
  <div class="home-history-link"><span>19 years of league history</span><a href="/seasons">Explore the archive →</a></div>
</div>
<link rel="stylesheet" href="/season-home.css?v=1">
@endsection
