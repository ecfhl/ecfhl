@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">League owners</div><h1>Your account</h1><p>{{ $owner->name }} · {{ $owner->email }}</p></div></div>
<div class="shell owner-page">@include('account.shared')
<div class="card owner-account-team">
@if($owner->claim)<button type="button" class="team-logo-viewer owner-team-logo-viewer" data-team-icon-viewer data-team-slug="{{ \Illuminate\Support\Str::slug($owner->claim->team_name) }}" data-team-name="{{ $owner->claim->team_name }}" aria-label="View {{ $owner->claim->team_name }} logo"><img data-full-src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($owner->claim->team_name)) }}" src="{{ \App\Support\TeamImages::url(\Illuminate\Support\Str::slug($owner->claim->team_name),160) }}" decoding="async" alt="{{ $owner->claim->team_name }} logo"></button><div><h2>{{ $owner->claim->team_name }}</h2><p>{{ $owner->is_admin?'League administrator':'League owner' }}</p><a class="button" href="/teams/current/{{ \Illuminate\Support\Str::slug($owner->claim->team_name) }}">My Team</a> <a class="button primary" href="/account/settings">Profile &amp; preferences</a></div>
@else<div><h2>Choose your team</h2><a class="button primary" href="/account/claim-team">Claim an available team</a></div>@endif
</div>
<div class="owner-settings-grid"><section class="card"><h2>Sign-in methods</h2><p>Email & password: <strong>{{ $owner->password?'Enabled':'Not set' }}</strong></p><p>Google: <strong>{{ $owner->google_id?'Connected':'Not connected' }}</strong></p>
@if(!$owner->google_id && $googleReady)@include('account.google-button',['googleLabel'=>'Connect Google'])@elseif(!$googleReady)<p class="subtle">Google sign-in is awaiting league configuration.</p>@endif
<form method="post" action="/logout">@csrf<button class="button" type="submit">Sign out</button></form></section>
<form class="card owner-form" method="post" action="/account/password">@csrf<h2>{{ $owner->password?'Change':'Set' }} password</h2>
@if($owner->password)<label>Current password<input type="password" name="current_password" required autocomplete="current-password"></label>@endif
<label>New password<input type="password" name="password" required minlength="10" maxlength="128" autocomplete="new-password"></label>
<label>Confirm password<input type="password" name="password_confirmation" required autocomplete="new-password"></label><button class="button primary">Save password</button></form></div>
</div>
@endsection
