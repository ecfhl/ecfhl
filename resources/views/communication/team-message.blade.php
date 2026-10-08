@auth
 @php $messageOwner=\App\Models\TeamClaim::where('team_name',$messageTeamName)->where('user_id','!=',auth()->id())->value('user_id'); @endphp
 @if($messageOwner)<a class="team-message-link" aria-label="Message owner" title="Message owner" href="/messages?user_id={{ $messageOwner }}"><span aria-hidden="true">💬</span></a>@endif
@endauth

@once
@push('styles')
<style>.team-message-link{display:inline-flex;align-items:center;justify-content:center;width:48px;height:48px;font-size:32px;line-height:1;text-decoration:none;border-radius:10px}.team-message-link:focus-visible{outline:2px solid var(--focus);outline-offset:2px}.team-message-logo-stack{display:flex;flex-direction:column;align-items:center;gap:4px}</style>
@endpush
@endonce
