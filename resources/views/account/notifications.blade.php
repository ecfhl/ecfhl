@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">Stay in the game</div><h1>Notifications &amp; alerts</h1><p>Manage messages, browser notifications, scoring alerts and goalie watches.</p></div></div>
<div class="shell owner-page">@include('account.shared')
@unless($owner)<div class="card"><h2>Save alerts to your owner account</h2><p>Sign in or create an account and claim your team to set up notifications.</p><a class="button primary" href="/login">Sign in</a> <a class="button" href="/register">Create account</a></div>@endunless
<section class="card notification-settings-panel"><div class="settings-tabs" role="tablist" aria-label="Notification and alert settings"><button type="button" role="tab" id="settings-notifications-tab" aria-controls="settings-notifications" aria-selected="true" data-settings-tab="notifications">Notifications</button><button type="button" role="tab" id="settings-alerts-tab" aria-controls="settings-alerts" aria-selected="false" tabindex="-1" data-settings-tab="alerts">Alerts</button></div>
<form method="post" action="/notifications" class="owner-form" id="owner-preferences-form">@csrf
<fieldset @disabled(!$owner) class="owner-settings-grid owner-settings-fieldset">
<div id="settings-notifications" role="tabpanel" aria-labelledby="settings-notifications-tab">@if($owner)<section class="card owner-device" data-notification-device><div><h2>This device</h2><p id="owner-push-state" role="status">Checking browser notifications…</p></div><button class="button primary" id="owner-enable-push" type="button" disabled>Enable notifications</button><button class="button" id="owner-disable-push" type="button" hidden disabled>Disable on this device</button></section>@endif<section class="card"><h2>Browser notifications</h2>
@foreach(['notifications_enabled'=>'Enable push notifications','private_message_push'=>'Private message notifications','league_message_push'=>'League chat notifications'] as $key=>$label)
<input type="hidden" name="{{ $key }}" value="0"><label class="owner-setting"><span><strong>{{ $label }}</strong></span><input type="checkbox" name="{{ $key }}" value="1" @checked($preferences[$key])></label>
@endforeach</section>
<section class="card"><h2>Scoring push notifications</h2>@if($owner?->claim)<p class="subtle">{{ $owner->claim->team_name }}</p>@elseif($owner)<p><a href="/account/claim-team">Claim your team</a> to receive scoring alerts.</p>@endif
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
<div class="owner-goalie-grid">@foreach($goalies->where('day',$day) as $g)<label class="owner-goalie-choice"><input type="checkbox" name="goalies[]" value="{{ $g['key'] }}" @checked(in_array($g['key'],$preferences['goalies'],true))><span><strong>{{ \App\Support\PlayerName::display($g['name']) }}</strong><small>{{ $g['team'] }} · {{ $g['start_time'] }}</small><small>ECFHL*: {{ $g['projected_points']!==null ? number_format($g['projected_points'],2) : '—' }}</small></span></label>@endforeach</div>
</details>
@endforeach
@foreach(array_diff($preferences['goalies'],$goalies->pluck('key')->all()) as $key)<input type="hidden" name="goalies[]" value="{{ $key }}">@endforeach
</section>
</div>
<div id="settings-alerts" role="tabpanel" aria-labelledby="settings-alerts-tab" hidden>
<section class="card"><h2>Message popups</h2><p class="subtle">Show an on-screen popup when a new message arrives.</p>
@foreach(['private_message_popups'=>'Private message popups','league_message_popups'=>'League chat popups'] as $key=>$label)
<input type="hidden" name="{{ $key }}" value="0"><label class="owner-setting"><span><strong>{{ $label }}</strong></span><input type="checkbox" name="{{ $key }}" value="1" @checked($preferences[$key])></label>
@endforeach</section>
<section class="card"><h2>Scoring Updates</h2><label class="owner-setting"><span><strong>Scoring Updates panel</strong></span><input type="checkbox" id="scoring-panel-enabled" role="switch" aria-controls="scoring-settings-controls"></label><p class="subtle">Choose which teams appear in scoring updates. Changes save automatically in this browser.</p><div id="scoring-settings-controls"></div></section>
@if($owner)<section class="card"><h2>Test message notifications</h2><p class="subtle">Preview League or private-message popups, or send a browser notification to this device. Tests respect your notification settings and do not send messages to other people.</p><div class="message-test-actions"><button type="button" class="button" data-message-test="league" data-test-channel="popup">Preview League popup</button><button type="button" class="button" data-message-test="private" data-test-channel="popup">Preview private popup</button><button type="button" class="button" data-message-test="league" data-test-channel="push">Test League notification</button><button type="button" class="button" data-message-test="private" data-test-channel="push">Test private notification</button></div><p id="message-test-status" role="status" aria-live="polite"></p><p class="subtle">To test unread counts, send a message from a second account while this chat is closed. Its count appears beside League chat or that team in the dropdown and clears when you read the conversation.</p></section>@endif
</div>
@if($owner)<div class="owner-save"><button class="button primary" type="submit" id="owner-save-preferences" hidden>Retry saving</button><p id="owner-save-state" class="subtle" role="status" aria-live="polite">All changes saved.</p><p class="subtle">Preferences apply to every device enabled for your account. Today and tomorrow follow the league’s Pacific fantasy day.</p></div>@endif
</fieldset></form></section>
</div>
@if($owner)<script src="/owner-notifications.js?v={{ hash_file('sha256', base_path('public/owner-notifications.js')) }}" defer></script>@endif
<script src="/notification-settings.js?v={{ hash_file('sha256', base_path('public/notification-settings.js')) }}" defer></script>
@endsection

