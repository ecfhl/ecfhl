<fieldset class="owner-team-picker"><legend>Choose your team</legend>
<p class="subtle">Claim an available team. Each team can belong to one owner account.</p>
<div class="owner-team-grid">
@foreach($teams as $team)
@if(!$team['claimed'] && (!$team['reserved'] || $invited))
<label class="owner-team-choice"><input type="radio" name="team_id" value="{{ $team['id'] }}" required @checked(old('team_id')===$team['id'])>
<img src="{{ \App\Support\TeamImages::url($team['slug'],160) }}" width="64" height="64" loading="lazy" decoding="async" alt="{{ $team['name'] }} logo"><span>{{ $team['name'] }} @if($team['reserved'])<small>Administrator</small>@endif</span></label>
@endif
@endforeach
</div>
@if(!$teams->contains(fn($t)=>!$t['claimed'] && (!$t['reserved'] || $invited)))<p>All teams are currently claimed. Contact your league administrator.</p>@endif
@if(!$invited)<p class="subtle">Lone Tsar is reserved for the league administrator.</p>@endif
</fieldset>
