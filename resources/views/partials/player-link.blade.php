@php($playerName = trim((string)($name ?? '')))
@if($playerName !== '')
<a class="player-history-link" href="/players/history?q={{ urlencode($playerName) }}#player-results">{{ $playerName }}</a>
@endif