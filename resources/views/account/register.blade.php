@extends('layouts.app')
@section('content')
<div class="page-head"><div class="shell"><div class="eyebrow">League owners</div><h1>Create your account</h1><p>Save your team and choose your alerts. You can always browse without signing in.</p></div></div>
<div class="shell owner-page">@include('account.shared')
@if($invited)<p class="owner-notice">Your administrator invitation is active. Choose Lone Tsar to set up your admin account.</p>@endif
@if($googleReady)@include('account.google-button')<p class="subtle">Or use your email and a password.</p>@else<p class="subtle">Google sign-in is coming once league configuration is complete.</p>@endif
<form method="post" action="/register" class="card owner-form">@csrf
<div class="owner-fields"><label>Your name<input name="name" value="{{ old('name') }}" required maxlength="100" autocomplete="name"></label>
<label>Email<input name="email" type="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email"></label>
<label>Password<input name="password" type="password" required minlength="10" maxlength="128" autocomplete="new-password"><small>At least 10 characters.</small></label>
<label>Confirm password<input name="password_confirmation" type="password" required autocomplete="new-password"></label></div>
@include('account.team-picker')
<button class="button primary" type="submit">Create account & claim team</button>
</form><p>Already have an account? <a href="/login">Sign in</a> · <a href="/teams/current">Continue browsing</a></p></div>
@endsection
