@auth
 @php $messageOwner=\App\Models\TeamClaim::where('team_name',$messageTeamName)->where('user_id','!=',auth()->id())->value('user_id'); @endphp
 @if($messageOwner)<a class="button team-message-link" href="/messages?user_id={{ $messageOwner }}">✉ Message owner</a>@endif
@endauth
