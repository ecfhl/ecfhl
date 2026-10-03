@php
 $bellStatus=strtolower(trim((string)($goalie['starting_status']??'')));
 $bellDays=(new \App\Support\FantasyDay)->dates();
 $bellKey=\App\Support\OwnerNotificationPolicy::goalieKey($goalie['team'],$goalie['name']);
 $bellWatched=in_array($bellKey,auth()->user()?->notification_preferences['goalies']??[],true);
@endphp
@if(!in_array($bellStatus,['confirmed','starting','not starting','not_starting'],true) && empty($goalie['not_starting']) && in_array($date,[$bellDays['today'],$bellDays['tomorrow']],true))
@auth
<button type="button" class="goalie-watch-bell" data-goalie-watch="{{ $bellKey }}" data-goalie-name="{{ $goalie['name'] }}" aria-pressed="{{ $bellWatched?'true':'false' }}" aria-label="{{ $bellWatched?'Stop watching':'Watch' }} {{ $goalie['name'] }} status changes" title="{{ $bellWatched?'Notifications on · click to turn off':'Notify me when this goalie’s status changes' }}"><span aria-hidden="true">🔔</span></button>
@else
<a class="goalie-watch-bell" href="/login" aria-label="Sign in to watch {{ $goalie['name'] }} status changes" title="Sign in to watch this goalie"><span aria-hidden="true">🔔</span></a>
@endauth
@endif
