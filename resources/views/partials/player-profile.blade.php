<article class="player-profile">
  <header>
    <div class="eyebrow">2026–27 Player Profile</div>
    <h1 id="player-profile-name">{{ \App\Support\PlayerName::display($player->player_name) }}</h1>
    <p class="player-profile-age">{{ $age!==null ? 'Age '.$age : 'Age unavailable' }}</p>
    <p class="player-profile-meta">{{ $player->position }} · {{ $player->nhl_team ?: 'No NHL team' }}@if($player->rookie) · <abbr class="player-profile-rookie" title="Rookie" aria-label="Rookie">R</abbr>@endif</p>
    <p class="player-profile-owner">@if($roster)<a href="/teams/current/{{ $teamSlug }}">{{ $roster->fantasy_team_name }}</a>@else Free Agent @endif</p>
  </header>
  <dl class="player-profile-overview">
    <div><dt>ECFHL*</dt><dd class="player-profile-projection">{{ $projection?->projected_fpts_per_game!==null ? number_format($projection->projected_fpts_per_game,2) : '—' }}</dd></div>
    @foreach(['Today'=>$todayGame,'Tomorrow'=>$tomorrowGame] as $label=>$game)
      <div><dt>{{ $label }}</dt><dd><strong>{{ $game ? (($game['away']?'@':'vs ').$game['opponent']) : 'Not playing' }}</strong>@if($game)<span>{{ $game['time'] ?: 'Time unavailable' }}</span>@endif</dd></div>
    @endforeach
  </dl>
  <p class="muted player-profile-time">Game times in Atlantic. Today and Tomorrow follow the Pacific fantasy day.</p>
  <div class="player-profile-table"><table><thead><tr><th>Stats period</th><th>GP</th><th>FPts</th><th>FPts / Game</th></tr></thead><tbody>
    @foreach($statRows as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['gp'] ?? '—' }}</td><td>{{ $row['fpts']!==null ? number_format($row['fpts'],0) : '—' }}</td><td>{{ $row['rate']!==null ? number_format($row['rate'],2) : '—' }}</td></tr>@endforeach
  </tbody></table></div>
  <h2>Stats</h2>
  <div class="player-profile-table player-profile-season-table"><table><thead><tr><th>Season</th>@foreach($categoryLabels as $key=>$label)<th title="{{ $label }}">{{ $key }}</th>@endforeach</tr></thead><tbody>
    @foreach($seasonRows as $seasonRow)<tr><th scope="row">{{ $seasonRow['season'] }}</th>@foreach($categoryLabels as $key=>$label)<td>{{ isset($seasonRow['stats'][$key]) && $seasonRow['stats'][$key]!=='' ? (in_array($key,['FPTS','FPTS/GP']) ? number_format((float)$seasonRow['stats'][$key],$key==='FPTS/GP'?2:0) : $seasonRow['stats'][$key]) : '—' }}</td>@endforeach</tr>@endforeach
  </tbody></table></div>
  <p class="muted player-profile-note">Stats through {{ \Carbon\CarbonImmutable::parse($player->stats_through)->format('M j, Y') }}. Recent rows show collected FPts and GP; a dash means the source is unavailable. Fantrax Proj uses the frozen season projection.</p>
  <div class="player-profile-actions"><a href="https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName={{ rawurlencode($player->player_name) }};positionOrGroup=ALL;" target="_blank" rel="noopener">View on Fantrax ↗</a>@if(request()->expectsJson())<a href="/players/{{ rawurlencode($player->player_id) }}">Open full player page →</a>@endif</div>
</article>
