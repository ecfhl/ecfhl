@if(!empty($season['league_id']))
<a class="fantrax-standings-link" href="https://www.fantrax.com/fantasy/league/{{ rawurlencode($season['league_id']) }}/standings" aria-label="{{ $season['season'] }} Fantrax standings"><img src="/fantrax-icon.png" alt="" width="18" height="18" style="vertical-align:middle;margin-right:5px;border-radius:3px">Fantrax</a>
@endif
