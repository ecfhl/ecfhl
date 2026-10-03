@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Stay in the game</div><h1>Notifications</h1><p>Choose the scoring and goalie updates you want.</p></div></div>
<div class="shell owner-page">@include('account.shared')
@unless($owner)<div class="card"><h2>Save alerts to your owner account</h2><p>Sign in or create an account and claim your team to set up notifications.</p><a class="button primary" href="/login">Sign in</a> <a class="button" href="/register">Create account</a></div>@endunless
@if($owner)<section class="card owner-device"><div><h2>This device</h2><p id="owner-push-state" role="status">Checking browser notifications…</p></div><button class="button primary" id="owner-enable-push" type="button">Enable notifications</button><button class="button" id="owner-disable-push" type="button" hidden>Disable on this device</button></section>@endif
<form method="post" action="/notifications" class="owner-form">@csrf
<fieldset @disabled(!$owner) class="owner-settings-grid owner-settings-fieldset">
<section class="card"><h2>Scoring</h2>@if($owner?->claim)<p class="subtle">{{ $owner->claim->team_name }}</p>@elseif($owner)<p><a href="/account/claim-team">Claim your team</a> to receive scoring alerts.</p>@endif
<label class="owner-setting"><span><strong>My team scores</strong><small>When an active player adds fantasy points to your team.</small></span><input type="checkbox" name="team_scores" value="1" @checked($preferences['team_scores'])></label>
<label class="owner-setting"><span><strong>My opponent scores</strong><small>Follow the opponent in your current fantasy matchup.</small></span><input type="checkbox" name="opponent_scores" value="1" @checked($preferences['opponent_scores'])></label></section>
<section class="card"><h2>Goalie status changes</h2>
<label class="owner-setting"><span><strong>Goalies on my team</strong><small>Starting-status changes for your rostered goalies playing today or tomorrow, including bench, minors, and IR.</small></span><input type="checkbox" name="own_goalies" value="1" @checked($preferences['own_goalies'])></label>
@if($owner && !$owner->claim)<p class="subtle"><a href="/account/claim-team">Claim your team</a> to receive alerts for your goalies.</p>@endif
<label class="owner-setting"><span><strong>All goalies</strong><small>Every reported starting-status change for today and tomorrow.</small></span><input type="checkbox" name="all_goalies" value="1" @checked($preferences['all_goalies'])></label>
<label class="owner-setting"><span><strong>Available goalies · Today</strong><small>Status changes for available goalies with a game today.</small></span><input type="checkbox" name="available_today" value="1" @checked($preferences['available_today'])></label>
<label class="owner-setting"><span><strong>Available goalies · Tomorrow</strong><small>Status changes for available goalies with a game tomorrow.</small></span><input type="checkbox" name="available_tomorrow" value="1" @checked($preferences['available_tomorrow'])></label>
</section>
<section class="card owner-goalie-watch"><h2>Watch specific available goalies</h2><p class="subtle">Receive status changes for selected goalies while they are available and have a game today or tomorrow.</p>
@foreach(['Today','Tomorrow'] as $day)
<details class="owner-goalie-day"><summary>{{ $day }}</summary>
@if($goalies->where('day',$day)->isEmpty())<p class="subtle">No goalies awaiting a starting decision for upcoming games.</p>@endif
<div class="owner-goalie-grid">@foreach($goalies->where('day',$day) as $g)<label class="owner-goalie-choice"><input type="checkbox" name="goalies[]" value="{{ $g['key'] }}" @checked(in_array($g['key'],$preferences['goalies'],true))><span><strong>{{ $g['name'] }}</strong><small>{{ $g['team'] }} · {{ $g['start_time'] }}</small></span></label>@endforeach</div>
</details>
@endforeach
@foreach(array_diff($preferences['goalies'],$goalies->pluck('key')->all()) as $key)<input type="hidden" name="goalies[]" value="{{ $key }}">@endforeach
</section>
@if($owner)<div class="owner-save"><button class="button primary" type="submit">Save preferences</button><p class="subtle">Preferences apply to every device enabled for your account. Today and tomorrow follow the league’s Pacific fantasy day.</p></div>@endif
</fieldset></form>
</div>
@if($owner)<script src="/owner-notifications.js?v=2" defer></script>@endif
@endsection
