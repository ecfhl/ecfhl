<div class="shell standings-page">
  <div class="page-head">
    <div class="eyebrow">2026-27 season</div>
    <h1>🏆 Standings</h1>
    <p class="subtle standings-refresh-status" role="status">Fantrax regular-season standings. Data refreshes every minute during games and hourly when idle. Records follow finalized Fantrax results.</p>
    @if(auth()->user()?->is_admin)<p class="standings-collector-link"><a href="/job-status#collector-standings">Standings collector</a></p>@endif
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
            <tr class="{{ (int)($r['rank'] ?? 0) === 8 ? 'standings-playoff-cutoff' : '' }}">
              <td class="num">{{ $r['rank'] ?? '—' }}</td>
              <td><div class="standings-team-cell">@include('teams.partials.team-icon-uploader',['slug'=>\Illuminate\Support\Str::slug((string)$r['team']),'name'=>$r['team']])<strong><a href="/teams/current/{{ \Illuminate\Support\Str::slug($r['team']) }}" class="standings-team-link">{{ $r['team'] }}</a></strong></div></td>
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

  @if(!empty($awardRaces))
    <section class="standings-awards">
      <div class="section-title"><h2>Current Award Leaders</h2></div>
      <div class="standings-award-grid">
        @foreach($awardRaces as $award)
          <article class="standings-award-card">
            <div class="standings-award-head"><span class="standings-award-icon">{{ $award['icon'] }}</span><div><strong>{{ $award['label'] }}</strong><small>{{ $award['detail'] }}</small></div></div>
            <div class="standings-award-leaders">
              @foreach($award['leaders'] as $leader)
                <div class="standings-award-row">
                  <span class="standings-award-rank">{{ $loop->iteration }}</span>
                  <div class="standings-award-player">
                    <strong>@if(!empty($leader['player_id']))<a class="player-name-link" data-player-stats href="/players/{{ rawurlencode($leader['player_id']) }}">{{ \App\Support\PlayerName::display($leader['name']) }}</a>@else{{ !empty($award['team_award']) ? $leader['name'] : \App\Support\PlayerName::display($leader['name']) }}@endif @if(!empty($leader['nhl_team'])) <small>({{ $leader['nhl_team'] }})</small>@endif</strong>
                    @if(!empty($award['team_award']))<span>#{{ $leader['rank'] ?? '—' }} · {{ $leader['record'] }}</span>@else<span>{{ $leader['fantasy_team'] ?: 'Free Agent' }}</span>@endif
                  </div>
                  <div class="standings-award-score">
                    @unless(!empty($award['team_award']))<div class="standings-award-stat"><span>GP</span><strong>{{ $leader['gp'] }}</strong></div>
                    <div class="standings-award-stat"><span>FPTS/GP</span><strong>{{ rtrim(rtrim(number_format($leader['fpts_g'],2), '0'), '.') }}</strong></div>
                    @endunless<div class="standings-award-stat"><span>FPTS</span><strong>{{ number_format($leader['fpts'],0) }}</strong></div>
                  </div>
                </div>
              @endforeach
            </div>
          </article>
        @endforeach
      </div>
    </section>
  @endif

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
        <details class="standings-period" data-period-number="{{ $period['period_number'] }}" {{ $isCurrentPeriod ? 'open' : '' }}>
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
