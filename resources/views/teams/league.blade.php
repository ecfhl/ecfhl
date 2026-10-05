@extends('layouts.app')
@section('title', 'Teams · ECFHL')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">2026–27 season</div><h1>Teams</h1><p>Regular season rank, record and fantasy points for all {{ count($teams) }} teams.</p></div></div>
<div class="shell league-team-grid">
  @foreach($teams as $team)
    <article class="card league-team-card">
      <h2><a class="league-team-open" href="/teams/current/{{ $team['slug'] }}">{{ $team['team'] }}</a></h2>
      <button type="button" class="league-team-logo" data-team-icon-viewer data-team-slug="{{ $team['slug'] }}" data-team-name="{{ $team['team'] }}" aria-label="View {{ $team['team'] }} logo">
        <img src="{{ \App\Support\TeamImages::url($team['slug'],160) }}" srcset="{{ \App\Support\TeamImages::url($team['slug'],160) }} 1x, {{ \App\Support\TeamImages::url($team['slug'],640) }} 2x" data-full-src="{{ \App\Support\TeamImages::url($team['slug']) }}" width="200" height="200" alt="{{ $team['team'] }} logo" loading="lazy" decoding="async">
      </button>
      <dl class="league-team-stats">
        <div><dt>Rank</dt><dd>{{ $team['rank'] === null ? '—' : '#'.$team['rank'] }}</dd></div>
        <div><dt>W–L–T</dt><dd>{{ $team['w'] === null ? '—' : $team['w'].'–'.$team['l'].'–'.$team['t'] }}</dd></div>
        <div><dt>FPts</dt><dd>{{ $team['fantasy_points_for'] === null ? '—' : number_format($team['fantasy_points_for'],0) }}</dd></div>
      </dl>
    </article>
  @endforeach
</div>
<style>
.league-team-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:16px;padding-bottom:32px}.league-team-card{position:relative;display:flex;flex-direction:column;align-items:center;padding:18px 12px;text-align:center}.league-team-card h2{font-size:18px;line-height:1.25;margin:0 0 12px;min-height:2.5em;display:flex;align-items:center;justify-content:center;overflow-wrap:anywhere}.league-team-open{text-decoration:none}.league-team-open:after{content:"";position:absolute;inset:0;border-radius:12px}.league-team-open:hover:after{box-shadow:inset 0 0 0 2px var(--accent)}.league-team-open:focus-visible{outline:none}.league-team-open:focus-visible:after{outline:3px solid var(--accent);outline-offset:3px}.league-team-logo{position:relative;z-index:1;appearance:none;border:0;padding:0;background:transparent;cursor:zoom-in;width:min(200px,100%);aspect-ratio:1}.league-team-logo img{display:block;width:100%;height:100%;object-fit:contain}.league-team-logo:focus-visible{outline:3px solid var(--accent);outline-offset:4px;border-radius:8px}.league-team-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));width:100%;gap:8px;margin:16px 0 0;padding-top:12px;border-top:1px solid var(--line)}.league-team-stats dt{color:var(--muted);font-size:10px;font-weight:700}.league-team-stats dd{margin:3px 0 0;font-size:16px;font-weight:800;font-variant-numeric:tabular-nums;white-space:nowrap}.league-team-stats div:first-child dd{color:var(--text)}@media(max-width:1000px){.league-team-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:700px){.league-team-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}.league-team-card{padding:12px 8px}.league-team-card h2{font-size:15px}.league-team-stats{gap:4px}.league-team-stats dd{font-size:13px}}@media(max-width:340px){.league-team-grid{grid-template-columns:1fr}}
</style>
@endsection
