@extends('layouts.app')
@section('title', 'Teams · ECFHL')
@section('content')
@php
  $myTeamName = auth()->user()?->claim?->team_name;
  $myTeamSlug = $myTeamName ? \Illuminate\Support\Str::slug($myTeamName) : null;
  $directoryTeams = collect($teams)->sortBy(fn($team) => $team['slug'] === $myTeamSlug ? -1 : ($team['rank'] ?? PHP_INT_MAX))->values();
@endphp
@push('styles')
<link rel="stylesheet" href="/league-teams.css?v={{ hash_file('sha256', base_path('public/league-teams.css')) }}">
@endpush
<section class="shell league-teams" data-league-teams>
  <header class="league-teams-heading">
    <div><h1>Teams <span>{{ count($teams) }}</span></h1><p>Regular season · 2026–27</p></div>
    @if($myTeamSlug)
      <a class="league-teams-my-link" href="/teams/current/{{ $myTeamSlug }}"><span aria-hidden="true">★</span> My Team <span aria-hidden="true">↗</span></a>
    @endif
  </header>
  <div class="league-teams-panel">
    <div class="league-teams-toolbar">
      <label class="league-teams-search">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
        <span class="sr-only">Search teams</span>
        <input type="search" placeholder="Search teams" autocomplete="off" data-teams-search>
      </label>
      <label class="league-teams-sort">
        <span class="sr-only">Sort teams</span>
        <select data-teams-sort aria-label="Sort teams">
          @if($myTeamSlug)<option value="mine">My team first</option>@endif
          <option value="rank">Rank</option><option value="points">FPts</option><option value="name">Team A–Z</option>
        </select>
      </label>
    </div>
    <table class="league-teams-table">
      <caption class="sr-only">Current team standings. Select a team to view its roster.</caption>
      <colgroup><col class="league-teams-rank-col"><col><col class="league-teams-record-col"><col class="league-teams-points-col"></colgroup>
      <thead><tr><th scope="col">Rank</th><th scope="col">Team</th><th scope="col" class="league-teams-number">W–L–T</th><th scope="col" class="league-teams-number">FPts</th></tr></thead>
      <tbody data-teams-rows>
        @foreach($directoryTeams as $team)
          @php $isMine = $team['slug'] === $myTeamSlug; @endphp
          <tr class="league-team-row {{ $isMine ? 'league-team-row-mine' : '' }}" data-team-row data-team-name="{{ $team['team'] }}" data-team-rank="{{ $team['rank'] ?? '' }}" data-team-points="{{ $team['fantasy_points_for'] ?? '' }}" data-team-mine="{{ $isMine ? '1' : '0' }}">
            <td class="league-team-rank">{{ $team['rank'] ?? '—' }}</td>
            <td><div class="league-team-identity">
              <button type="button" class="league-team-logo" data-team-icon-viewer data-team-slug="{{ $team['slug'] }}" data-team-name="{{ $team['team'] }}" aria-label="View {{ $team['team'] }} logo">
                <img src="{{ \App\Support\TeamImages::url($team['slug'],160) }}" data-full-src="{{ \App\Support\TeamImages::url($team['slug']) }}" width="40" height="40" alt="" loading="lazy" decoding="async">
              </button>
              <div class="league-team-copy">
                <a class="league-team-open" href="/teams/current/{{ $team['slug'] }}" title="{{ $team['team'] }}">{{ $team['team'] }}</a>
                @if($isMine)<span class="league-team-mine-label">★ My Team</span>@else<span class="league-team-roster-label">View roster <span aria-hidden="true">›</span></span>@endif
              </div>
            </div></td>
            <td class="league-teams-number league-team-record">{{ $team['w'] === null ? '—' : $team['w'].'–'.$team['l'].'–'.$team['t'] }}</td>
            <td class="league-teams-number league-team-points">{{ $team['fantasy_points_for'] === null ? '—' : number_format($team['fantasy_points_for'],0) }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
    <div class="league-teams-empty" data-teams-empty @if(count($teams)) hidden @endif>
      <strong>{{ count($teams) ? 'No teams found' : 'Teams are not available yet' }}</strong>
      <p>{{ count($teams) ? 'Try another team name.' : 'Standings will appear after the next update.' }}</p>
      <button type="button" data-teams-reset hidden>Clear search</button>
    </div>
    <footer class="league-teams-footer"><span data-teams-count aria-live="polite">{{ count($teams) }} teams</span><span>Season FPts</span></footer>
  </div>
</section>
@endsection
@push('scripts')
<script src="/league-teams.js?v={{ hash_file('sha256', base_path('public/league-teams.js')) }}" defer></script>
@endpush
