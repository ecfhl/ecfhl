@foreach($players as $player)
<tr data-player-id="{{ $player->player_id }}">
  <th scope="row"><a href="https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName={{ rawurlencode($player->player_name) }};positionOrGroup=ALL;" target="_blank" rel="noopener">{{ $player->player_name }}</a><small>{{ $player->position }} · {{ $player->nhl_team ?: '—' }} @if($player->rookie)<span class="rookie-tag">Rookie</span>@endif</small></th>
  <td class="player-owner">@if($player->fantasy_team_name)<a href="/teams/current/{{ \Illuminate\Support\Str::slug($player->fantasy_team_name) }}">{{ $player->fantasy_team_name }}</a>@else<span class="muted">Free Agent</span>@endif</td>
  <td>{{ $player->season_gp }}</td>
  <td>{{ number_format($player->season_fpts, 2) }}</td>
  <td>{{ number_format($player->season_fpts_per_game, 2) }}</td>
  <td class="myproj">{{ $player->projected_fpts_per_game === null ? '—' : number_format($player->projected_fpts_per_game, 2) }}</td>
  @foreach($columns as $label=>$description)<td>{{ ($player->stats[$label] ?? '') !== '' ? $player->stats[$label] : '—' }}</td>@endforeach
</tr>
@endforeach
