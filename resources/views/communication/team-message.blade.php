@auth
 @php $messageOwner=\App\Models\TeamClaim::where('team_name',$messageTeamName)->where('user_id','!=',auth()->id())->value('user_id'); @endphp
 @if($messageOwner)<a class="team-message-link" data-message-user="{{ $messageOwner }}" aria-label="Message owner" title="Message owner" href="/messages?user_id={{ $messageOwner }}"><span aria-hidden="true">💬</span><span class="team-message-count" hidden></span></a>@endif
@endauth

