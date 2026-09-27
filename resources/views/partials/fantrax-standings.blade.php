@if(!empty($season['league_id']))
<a class="fantrax-standings-link" href="https://www.fantrax.com/fantasy/league/{{ rawurlencode($season['league_id']) }}/standings" aria-label="{{ $season['season'] }} Fantrax standings">Fantrax ↗</a>
@endif
