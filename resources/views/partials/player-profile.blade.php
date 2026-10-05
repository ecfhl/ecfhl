<article class="player-profile">
  <header><div class="eyebrow">2026–27 Player Profile</div><h1>{{ $player->player_name }}</h1><p>{{ $player->position }} · {{ $player->nhl_team ?: 'No NHL team' }}@if($player->rookie) · <span class="rookie-tag">Rookie</span>@endif</p><p class="player-profile-owner">@if($roster)<a href="/teams/current/{{ $teamSlug }}">{{ $roster->fantasy_team_name }}</a>@else Free Agent @endif</p></header>
  <div class="player-profile-tiles"><div><small>EC Proj / Game</small><strong>{{ $projection?->projected_fpts_per_game!==null ? number_format($projection->projected_fpts_per_game,2) : '—' }}</strong></div>
    @foreach(['Today'=>$todayGame,'Tomorrow'=>$tomorrowGame] as $label=>$game)<div><small>{{ $label }}</small><strong class="player-profile-game">{{ $game ? (($game['away']?'@':'vs ').$game['opponent']) : 'Not playing' }}</strong>@if($game)<span>{{ $game['time'] ?: 'Time unavailable' }}</span>@endif</div>@endforeach
  </div>
  <p class="muted player-profile-time">Game times in Atlantic. Today and Tomorrow follow the Pacific fantasy day.</p>
  <div class="player-profile-table"><table><thead><tr><th>Stats period</th><th>GP</th><th>FPts</th><th>FPts / Game</th></tr></thead><tbody>
    @foreach($statRows as $row)<tr><th scope="row">{{ $row['label'] }}</th><td>{{ $row['gp'] ?? '—' }}</td><td>{{ $row['fpts']!==null ? number_format($row['fpts'],0) : '—' }}</td><td>{{ $row['rate']!==null ? number_format($row['rate'],2) : '—' }}</td></tr>@endforeach
  </tbody></table></div>
  @if($categoryLabels)<h2>Current Season Stats</h2><div class="player-profile-categories">@foreach($categoryLabels as $key=>$label)<div><small title="{{ $label }}">{{ $key }}</small><strong>{{ $seasonStats[$key] }}</strong></div>@endforeach</div>@endif
  <p class="muted player-profile-note">Stats through {{ \Carbon\CarbonImmutable::parse($player->stats_through)->format('M j, Y') }}. Recent rows show collected FPts and GP; a dash means the source is unavailable. Fantrax Proj uses the frozen season projection.</p>
  <div class="player-profile-actions"><a href="https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName={{ rawurlencode($player->player_name) }};positionOrGroup=ALL;" target="_blank" rel="noopener">View on Fantrax ↗</a>@if(request()->expectsJson())<a href="/players/{{ rawurlencode($player->player_id) }}">Open full player page →</a>@endif</div>
</article>
