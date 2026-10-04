@foreach($players as $player)
<tr data-player-id="{{ $player->player_id }}">
  <th scope="row"><a class="player-name-link" href="https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName={{ rawurlencode($player->player_name) }};positionOrGroup=ALL;" target="_blank" rel="noopener">{{ $player->player_name }}</a><small>{{ $player->position }} · {{ $player->nhl_team ?: '—' }} @if($player->rookie)<span class="rookie-tag">Rookie</span>@endif</small></th>
  <td class="player-owner">@if($player->fantasy_team_name)<a class="player-team-link" href="/teams/current/{{ \Illuminate\Support\Str::slug($player->fantasy_team_name) }}"><img class="player-team-logo" src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($player->fantasy_team_name),64) }}" width="32" height="32" alt="" loading="lazy" decoding="async"><span>{{ $player->fantasy_team_name }}</span></a>@else<span class="muted">Free Agent</span>@endif</td>
  <td>{{ $player->season_gp }}</td>
  <td>{{ number_format($player->season_fpts, 2) }}</td>
  <td>{{ number_format($player->season_fpts_per_game, 2) }}</td>
  <td class="myproj">{{ $player->projected_fpts_per_game === null ? '—' : number_format($player->projected_fpts_per_game, 2) }}</td>
  @foreach($columns as $label=>$description)<td>{{ ($player->stats[$label] ?? '') !== '' ? $player->stats[$label] : '—' }}</td>@endforeach
</tr>
@endforeach
