@foreach($players as $player)
<tr class="player-position-{{ strtolower($player->position) }}{{ $ownedTeamId && (string)$player->fantasy_team_id === (string)$ownedTeamId ? ' player-on-my-team' : '' }}" data-player-id="{{ $player->player_id }}">
  <td class="player-frozen-rank">{{ $players->firstItem() + $loop->index }}</td>
  <th scope="row" class="player-frozen-player"><a class="player-name-link" href="https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName={{ rawurlencode($player->player_name) }};positionOrGroup=ALL;" target="_blank" rel="noopener">{{ $player->player_name }}@if($player->rookie)<span class="rookie-tag">Rookie</span>@endif</a><small>{{ $player->position }} · {{ $player->nhl_team ?: '—' }}@if($player->line_number) · {{ $player->position==='D'?'Pair':'L' }}{{ $player->line_number }}@endif @if($player->pp_unit) · PP{{ $player->pp_unit }}@endif</small></th>
  <td class="player-owner player-frozen-team">@if($player->fantasy_team_name)
    @php $teamSlug = \Illuminate\Support\Str::slug($player->fantasy_team_name); @endphp
    <div class="player-team-link" aria-label="{{ $player->fantasy_team_name }}" title="{{ $player->fantasy_team_name }}">
      <button class="team-logo-viewer player-team-viewer" type="button" data-team-icon-viewer data-team-slug="{{ $teamSlug }}" data-team-name="{{ $player->fantasy_team_name }}" aria-label="View {{ $player->fantasy_team_name }} logo"><img class="player-team-logo" src="{{ \App\Support\TeamImages::url($teamSlug,64) }}" data-full-src="{{ \App\Support\TeamImages::url($teamSlug) }}" width="32" height="32" alt="{{ $player->fantasy_team_name }} logo" loading="lazy" decoding="async"></button>
      <a class="player-team-name-link" href="/teams/current/{{ $teamSlug }}"><span class="player-team-name">{{ $player->fantasy_team_name }}</span></a>
    </div>
    @else<div class="player-unowned"><a class="player-add-icon" href="https://www.fantrax.com/fantasy/league/092zcn40molvao69/players;searchName={{ rawurlencode($player->player_name) }};statusOrTeamFilter=ALL_AVAILABLE;positionOrGroup=ALL;pageNumber=1;" target="_blank" rel="noopener noreferrer" aria-label="Find {{ $player->player_name }} in Fantrax to add or claim" title="Find in Fantrax to add or claim"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg></a><span class="muted">Free Agent</span></div>@endif</td>
  <td class="myproj">{{ $player->projected_fpts_per_game === null ? '—' : number_format($player->projected_fpts_per_game, 2) }}</td>
  <td>{{ number_format($player->dataset_fpts, 0) }}</td>
  <td>{{ number_format($player->dataset_fpts_per_game, 2) }}</td>
  @if(isset($headers['gp']))<td>{{ $player->dataset_gp }}</td>@endif
  @foreach($columns as $label=>$description)<td>{{ ($player->stats[$label] ?? '') !== '' ? $player->stats[$label] : '—' }}</td>@endforeach
</tr>
@endforeach
