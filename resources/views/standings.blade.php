@extends('layouts.app')
@section('title','Standings · ECFHL')
@section('content')
<div class="shell standings-page">
  <div class="page-head">
    <div class="eyebrow">2026-27 season</div>
    <h1>🏆 Standings</h1>
    @if(!empty($standingsLastUpdate))
      <p class="subtle standings-updated">Updated @include('partials.updated-time',['value'=>$standingsLastUpdate])</p>
    @endif
    @include('partials.fantrax-standings',['season'=>$season])
  </div>

  <div class="table-card">
    <div class="table-scroll">
      <table class="data-table">
        <thead>
          <tr>
            <th class="num">Rank</th>
            <th>Team</th>
            <th class="num">W</th>
            <th class="num">L</th>
            <th class="num">T</th>
            <th class="num">Pts</th>
            <th class="num">Fpts</th>
            <th class="num standings-win-pct">Win %</th>
          </tr>
        </thead>
        <tbody>
          @foreach($standings as $r)
            @php
              $gp=($r['w']??0)+($r['l']??0)+($r['t']??0);
              $wp=$gp?(($r['w']??0)+0.5*($r['t']??0))/$gp:null;
            @endphp
            <tr>
              <td class="num">{{ $r['rank'] ?? '—' }}</td>
              <td><strong><a href="/teams/current/{{ \Illuminate\Support\Str::slug($r['team']) }}" class="standings-team-link">{{ $r['team'] }}</a></strong></td>
              <td class="num">{{ $r['w'] ?? '—' }}</td>
              <td class="num">{{ $r['l'] ?? '—' }}</td>
              <td class="num">{{ $r['t'] ?? '—' }}</td>
              <td class="num">{{ $r['standings_points']!==null ? number_format($r['standings_points'],0) : '—' }}</td>
              <td class="num">{{ $r['fantasy_points_for']!==null ? number_format($r['fantasy_points_for'],0) : '—' }}</td>
              <td class="num standings-win-pct">{{ $wp!==null ? number_format($wp*100,1).'%' : '—' }}</td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>

  @if(!empty($scoringPeriods))
    <div class="standings-scoring-periods">
      @foreach($scoringPeriods as $period)
        @php
          $periodStart=!empty($period['start'])?\Carbon\CarbonImmutable::parse($period['start'],'America/Halifax'):null;
          $periodEnd=!empty($period['end'])?\Carbon\CarbonImmutable::parse($period['end'],'America/Halifax'):null;
          $periodDates=$periodStart&&$periodEnd ? $periodStart->format('M j').' – '.$periodEnd->format('M j, Y') : null;
          $periodTitle=trim((string)($period['caption']??'Scoring Period'));
          $isCurrentPeriod=(int)($period['period_number']??0)===(int)($currentPeriodNumber??0);
        @endphp
        <details class="standings-period" {{ $isCurrentPeriod ? 'open' : '' }}>
          <summary class="matchup-period-label standings-period-label">
            <span>{{ $periodTitle }}@if($periodDates) ({{ $periodDates }})@endif</span>
            <span class="standings-period-chevron" aria-hidden="true">▾</span>
          </summary>

          <div class="standings-period-body">
          <div class="current-matchup-list standings-matchup-list">
            @foreach($period['matchups'] as $matchup)
              @php
                $awayName=$matchup['away_display']??$matchup['away_name']??'Away';
                $homeName=$matchup['home_display']??$matchup['home_name']??'Home';
                $awayScore=$matchup['away_score'];
                $homeScore=$matchup['home_score'];
                $awayWinning=$awayScore!==null&&$homeScore!==null&&$awayScore>$homeScore;
                $homeWinning=$awayScore!==null&&$homeScore!==null&&$homeScore>$awayScore;
              @endphp
              <div class="matchup-card standings-matchup-card">
                <div class="matchup-summary">
                  <div class="matchup-summary-side matchup-summary-away">
                    <div class="matchup-summary-name">
                      <a href="/teams/current/{{ \Illuminate\Support\Str::slug($awayName) }}">{{ $awayName }}</a>
                      <div class="matchup-summary-meta matchup-summary-meta-away">
                        <span class="matchup-side-pill away-pill">AWAY</span>
                      </div>
                    </div>
                    <span class="matchup-summary-score">
                      <strong class="matchup-week-score {{ $awayWinning?'score-winning':'' }}">{{ $awayScore!==null?number_format($awayScore,0):'—' }}</strong>
                    </span>
                  </div>

                  <div class="matchup-summary-vs">VS</div>

                  <div class="matchup-summary-side matchup-summary-home">
                    <span class="matchup-summary-score">
                      <strong class="matchup-week-score {{ $homeWinning?'score-winning':'' }}">{{ $homeScore!==null?number_format($homeScore,0):'—' }}</strong>
                    </span>
                    <div class="matchup-summary-name">
                      <a href="/teams/current/{{ \Illuminate\Support\Str::slug($homeName) }}">{{ $homeName }}</a>
                      <div class="matchup-summary-meta matchup-summary-meta-home">
                        <span class="matchup-side-pill home-pill">HOME</span>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          </div>
        </details>
      @endforeach
    </div>
  @endif
</div>
<style>
.standings-page{padding-bottom:26px}
.standings-updated{margin:4px 0 6px;font-size:12px}.standings-team-link{color:inherit;text-decoration:none}.standings-team-link:hover{text-decoration:underline}.standings-scoring-periods{margin-top:28px}.standings-period{margin-top:16px}.standings-period:first-child{margin-top:0}.standings-period summary{list-style:none}.standings-period summary::-webkit-details-marker{display:none}.matchup-period-label{display:flex;align-items:center;justify-content:space-between;margin:0;padding:9px 12px;border-radius:9px;background:#082f4f;color:#fff;font-weight:900;font-size:14px;cursor:pointer}.standings-period[open]>.matchup-period-label{border-radius:9px 9px 0 0}.standings-period-chevron{transition:transform .18s ease}.standings-period[open] .standings-period-chevron{transform:rotate(180deg)}.standings-period-body{padding:10px;border:1px solid var(--line);border-top:0;border-radius:0 0 12px 12px;background:var(--surface-2,#f3f6fa)}.current-matchup-list{display:flex;flex-direction:column;gap:10px}.standings-matchup-card{border:1px solid var(--line);border-radius:12px;background:#fff;overflow:hidden}.matchup-summary{display:grid;grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);align-items:center;gap:12px;padding:13px 14px}.matchup-summary-side{display:flex;align-items:center;gap:10px;min-width:0}.matchup-summary-away{justify-content:space-between}.matchup-summary-home{justify-content:space-between}.matchup-summary-name{min-width:0;font-weight:900}.matchup-summary-name a{color:inherit;text-decoration:none}.matchup-summary-name a:hover{text-decoration:underline}.matchup-summary-meta{margin-top:5px}.matchup-summary-meta-home{text-align:right}.matchup-side-pill{display:inline-flex;align-items:center;border-radius:999px;padding:2px 7px;font-size:9px;font-weight:900;letter-spacing:.04em}.away-pill{background:#e5e7eb;color:#374151}.home-pill{background:#dbeafe;color:#1d4ed8}.matchup-summary-score{display:inline-flex;align-items:flex-start}.matchup-week-score{font-size:24px;line-height:1;font-weight:900}.score-winning{color:#16a34a}.matchup-summary-vs{font-size:10px;font-weight:900;opacity:.55}html[data-theme="dark"] .standings-period-body{background:var(--surface-2,#172033)}html[data-theme="dark"] .standings-matchup-card{background:var(--surface,#111827)}@media(max-width:700px){.standings-page .table-scroll{overflow-x:hidden}.standings-page .data-table{width:100%;min-width:0;table-layout:fixed}.standings-page .data-table th,.standings-page .data-table td{padding-left:5px;padding-right:5px;font-size:12px}.standings-page .data-table th:nth-child(1),.standings-page .data-table td:nth-child(1){width:11%}.standings-page .data-table th:nth-child(2),.standings-page .data-table td:nth-child(2){width:35%}.standings-page .data-table th:nth-child(n+3),.standings-page .data-table td:nth-child(n+3){width:9%}.standings-page .standings-team-link{overflow-wrap:anywhere}.standings-win-pct{display:none}.matchup-summary{grid-template-columns:minmax(0,1fr) auto minmax(0,1fr);gap:7px;padding:11px 9px}.matchup-summary-side{gap:6px}.matchup-week-score{font-size:20px}.matchup-summary-name{font-size:12px}}
</style>
@endsection
